<?php

namespace App\Console\Commands;

use App\Models\AdmissionEnquiry;
use App\Models\FeeInvoice;
use App\Models\Homework;
use App\Models\Student;
use App\Models\StudentLeave;
use App\Models\SubscriptionInvoice;
use App\Models\Subscription;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendScheduledNotifications extends Command
{
    protected $signature   = 'notifications:send';
    protected $description = 'Send scheduled push notifications (fee reminders, homework, exams, follow-ups)';

    public function handle(PushNotificationService $push): void
    {
        $today    = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $this->info('[Push] Running scheduled notifications — ' . $today->toDateString());

        // ── 1. Fee reminders: invoices due tomorrow ──────────────────────────
        $dueTomorrow = FeeInvoice::whereDate('due_date', $tomorrow)
            ->where('status', 'Unpaid')
            ->with('student.parents')
            ->get();

        foreach ($dueTomorrow as $inv) {
            $parentUserIds = $inv->student->parents
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->toArray();

            if (!empty($parentUserIds)) {
                $push->sendToUsers($parentUserIds,
                    '💰 Fee Due Tomorrow',
                    "₹{$inv->total} fee for {$inv->student->name} is due tomorrow",
                    ['screen' => 'Fees']
                );
            }
        }
        $this->info("[Push] Fee reminders sent: {$dueTomorrow->count()}");

        // ── 2. Homework due today ────────────────────────────────────────────
        $hwDueToday = Homework::whereDate('due_date', $today)
            ->where('status', 'Active')
            ->get();

        foreach ($hwDueToday as $hw) {
            $students = Student::where('class_id', $hw->class_id)
                ->with('parents');
            if ($hw->section_id) {
                $students->where('section_id', $hw->section_id);
            }
            $parentUserIds = $students->get()
                ->flatMap(fn($s) => $s->parents->whereNotNull('user_id')->pluck('user_id'))
                ->unique()
                ->toArray();

            if (!empty($parentUserIds)) {
                $push->sendToUsers($parentUserIds,
                    '📚 Homework Due Today',
                    "{$hw->title} is due today",
                    ['screen' => 'Homework']
                );
            }
        }
        $this->info("[Push] Homework reminders sent: {$hwDueToday->count()}");

        // ── 3. Admission follow-ups due today (staff) ────────────────────────
        $followUps = AdmissionEnquiry::whereDate('follow_up_date', $today)
            ->whereNotIn('stage', ['Enrolled', 'Rejected'])
            ->with('school')
            ->get();

        foreach ($followUps as $enq) {
            $push->sendToSchool(
                $enq->school_id,
                '🎓 Follow-up Due Today',
                "Follow up with {$enq->student_name} ({$enq->stage})",
                ['screen' => 'Admissions'],
                ['staff', 'teacher']
            );
        }
        $this->info("[Push] Follow-up reminders sent: {$followUps->count()}");

        // ── Mark overdue subscription invoices & apply grace period ─────────

        // Step 1: Mark invoices overdue (past due date)
        \App\Models\SubscriptionInvoice::whereIn('status', ['sent', 'partial'])
            ->whereDate('due_date', '<', today())
            ->update(['status' => 'overdue']);

        // Step 2: Mark subscriptions overdue + set grace_period_ends_at
        \App\Models\SubscriptionInvoice::where('status', 'overdue')
            ->with('subscription')
            ->get()
            ->each(function ($inv) {
                if ($inv->subscription && $inv->subscription->status !== 'overdue') {
                    $inv->subscription->update([
                        'status' => 'overdue',
                        'grace_period_ends_at' => \Carbon\Carbon::parse($inv->due_date)->addDays(15),
                    ]);
                }
            });

        // Step 3: After grace period → mark expired
        \App\Models\Subscription::where('status', 'overdue')
            ->whereNotNull('grace_period_ends_at')
            ->whereDate('grace_period_ends_at', '<', today())
            ->update(['status' => 'expired']);

        $overdueCount = \App\Models\Subscription::where('status', 'overdue')->count();
        if ($overdueCount > 0) {
            $this->info("[Invoices] {$overdueCount} subscriptions currently overdue (in grace period)");
        }

        $this->info('[Push] Done.');
    }
}
