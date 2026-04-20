<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AdmissionEnquiry;
use App\Models\Exam;
use App\Models\FeeInvoice;
use App\Models\Homework;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentLeave;
use App\Models\StudentParent;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $schoolId = $user->school_id;
        $role     = $user->role;
        $notifs   = collect();

        // ── ADMIN / SUPER ADMIN ───────────────────────────────────
        if (in_array($role, ['admin', 'super_admin'])) {

            // 1. Fee overdue count
            $overdueCount = FeeInvoice::where('school_id', $schoolId)
                ->where('status', 'Unpaid')
                ->where('due_date', '<', today())
                ->count();
            if ($overdueCount > 0) {
                $notifs->push([
                    'id'     => 'fee_overdue',
                    'icon'   => '💰',
                    'color'  => '#EF4444',
                    'title'  => "Fee overdue — {$overdueCount} students",
                    'body'   => "Monthly fees pending for {$overdueCount} students",
                    'time'   => 'Today',
                    'unread' => true,
                    'type'   => 'fee',
                    'link'   => '/fees',
                ]);
            }

            // 2. Low attendance classes (below 75% today)
            $today  = today()->toDateString();
            $lowAtt = DB::table('attendance')
                ->where('school_id', $schoolId)
                ->whereDate('date', $today)
                ->select(
                    'class_id',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present')
                )
                ->groupBy('class_id')
                ->havingRaw('total > 0 AND (present / total * 100) < 75')
                ->get();
            foreach ($lowAtt as $att) {
                $class = SchoolClass::find($att->class_id);
                $pct   = $att->total > 0 ? round(($att->present / $att->total) * 100) : 0;
                $notifs->push([
                    'id'     => "low_att_{$att->class_id}",
                    'icon'   => '📅',
                    'color'  => '#F59E0B',
                    'title'  => "Low attendance — Class {$class?->name}",
                    'body'   => "{$pct}% present today in Class {$class?->name}",
                    'time'   => 'Today',
                    'unread' => true,
                    'type'   => 'attendance',
                    'link'   => '/attendance',
                ]);
            }

            // 3. New admission enquiries today
            $newEnquiries = AdmissionEnquiry::where('school_id', $schoolId)
                ->whereDate('enquiry_date', today())
                ->count();
            if ($newEnquiries > 0) {
                $latest = AdmissionEnquiry::where('school_id', $schoolId)
                    ->whereDate('enquiry_date', today())
                    ->latest()->first();
                $notifs->push([
                    'id'     => 'new_enquiries',
                    'icon'   => '🎓',
                    'color'  => '#6366F1',
                    'title'  => 'New admission enquier' . ($newEnquiries > 1 ? "ies ({$newEnquiries})" : 'y'),
                    'body'   => $latest
                        ? "{$latest->student_name} enquiry for Class {$latest->apply_class}"
                        : "{$newEnquiries} new enquiries today",
                    'time'   => 'Today',
                    'unread' => true,
                    'type'   => 'admission',
                    'link'   => '/admissions',
                ]);
            }

            // 4. Follow-ups due today
            $followUps = AdmissionEnquiry::where('school_id', $schoolId)
                ->whereDate('follow_up_date', today())
                ->whereNotIn('stage', ['enrolled', 'rejected'])
                ->count();
            if ($followUps > 0) {
                $notifs->push([
                    'id'     => 'follow_ups',
                    'icon'   => '📞',
                    'color'  => '#8B5CF6',
                    'title'  => "{$followUps} admission follow-up" . ($followUps > 1 ? 's' : '') . ' due today',
                    'body'   => 'Pending follow-ups need attention',
                    'time'   => 'Today',
                    'unread' => false,
                    'type'   => 'admission',
                    'link'   => '/admissions',
                ]);
            }

            // 5. Upcoming exams in next 7 days
            $upcomingExams = Exam::where('school_id', $schoolId)
                ->whereBetween('start_date', [today(), today()->addDays(7)])
                ->count();
            if ($upcomingExams > 0) {
                $nextExam = Exam::where('school_id', $schoolId)
                    ->where('start_date', '>=', today())
                    ->orderBy('start_date')->first();
                $notifs->push([
                    'id'     => 'upcoming_exams',
                    'icon'   => '📝',
                    'color'  => '#3B82F6',
                    'title'  => 'Exam starting soon',
                    'body'   => $nextExam
                        ? "{$nextExam->name} on " . Carbon::parse($nextExam->start_date)->format('d M')
                        : "{$upcomingExams} exams this week",
                    'time'   => 'This week',
                    'unread' => false,
                    'type'   => 'exam',
                    'link'   => '/exams',
                ]);
            }

            // 6. Overdue homework
            $overdueHw = Homework::where('school_id', $schoolId)
                ->where('status', 'Active')
                ->where('due_date', '<', today())
                ->count();
            if ($overdueHw > 0) {
                $notifs->push([
                    'id'     => 'overdue_hw',
                    'icon'   => '📚',
                    'color'  => '#F59E0B',
                    'title'  => "{$overdueHw} overdue homework" . ($overdueHw > 1 ? 's' : ''),
                    'body'   => 'Past due date — mark as completed or extend',
                    'time'   => 'Today',
                    'unread' => false,
                    'type'   => 'homework',
                    'link'   => '/homework',
                ]);
            }

            // 7. Recent pinned announcements (last 7 days)
            $ann = Announcement::where('school_id', $schoolId)
                ->where('is_pinned', true)
                ->whereDate('created_at', '>=', today()->subDays(7))
                ->latest()->first();
            if ($ann) {
                $notifs->push([
                    'id'     => 'pinned_ann',
                    'icon'   => '📣',
                    'color'  => '#10B981',
                    'title'  => $ann->title ?? 'New announcement',
                    'body'   => 'Pinned announcement',
                    'time'   => Carbon::parse($ann->created_at)->diffForHumans(),
                    'unread' => false,
                    'type'   => 'announcement',
                    'link'   => '/communications',
                ]);
            }

            // 8. Pending leave requests
            $pendingLeaves = DB::table('student_leaves')
                ->where('school_id', $schoolId)
                ->where('status', 'Pending')
                ->count();
            if ($pendingLeaves > 0) {
                $notifs->push([
                    'id'     => 'pending_leaves',
                    'icon'   => '📋',
                    'color'  => '#D97706',
                    'title'  => "{$pendingLeaves} leave request" . ($pendingLeaves > 1 ? 's' : '') . ' pending',
                    'body'   => 'Awaiting your review and approval',
                    'time'   => 'Today',
                    'unread' => true,
                    'type'   => 'leave',
                    'link'   => '/leaves',
                ]);
            }
        }

        // ── TEACHER ───────────────────────────────────────────────
        if ($role === 'teacher') {
            $teacher = Teacher::where('user_id', $user->id)
                ->where('school_id', $schoolId)->first();

            if ($teacher) {
                // Overdue homework assigned by this teacher
                $overdueHw = Homework::where('teacher_id', $teacher->id)
                    ->where('status', 'Active')
                    ->where('due_date', '<', today())
                    ->count();
                if ($overdueHw > 0) {
                    $notifs->push([
                        'id'     => 'teacher_overdue_hw',
                        'icon'   => '📚',
                        'color'  => '#EF4444',
                        'title'  => "{$overdueHw} homework past due date",
                        'body'   => 'Your assigned homework needs attention',
                        'time'   => 'Today',
                        'unread' => true,
                        'type'   => 'homework',
                        'link'   => '/homework',
                    ]);
                }

                // Homework due today
                $hwToday = Homework::where('teacher_id', $teacher->id)
                    ->where('status', 'Active')
                    ->whereDate('due_date', today())
                    ->count();
                if ($hwToday > 0) {
                    $notifs->push([
                        'id'     => 'teacher_hw_today',
                        'icon'   => '⏰',
                        'color'  => '#F59E0B',
                        'title'  => "{$hwToday} homework due today",
                        'body'   => 'Students should submit today',
                        'time'   => 'Today',
                        'unread' => true,
                        'type'   => 'homework',
                        'link'   => '/homework',
                    ]);
                }

                // Upcoming exams this week
                $upcomingExams = Exam::where('school_id', $schoolId)
                    ->where('status', 'Upcoming')
                    ->whereBetween('start_date', [today(), today()->addDays(7)])
                    ->count();
                if ($upcomingExams > 0) {
                    $notifs->push([
                        'id'     => 'teacher_exams',
                        'icon'   => '📝',
                        'color'  => '#3B82F6',
                        'title'  => 'Exam coming up this week',
                        'body'   => "{$upcomingExams} exam(s) scheduled",
                        'time'   => 'This week',
                        'unread' => false,
                        'type'   => 'exam',
                        'link'   => '/exams',
                    ]);
                }

                // Pending leave requests for teacher's assigned classes only
                $classIds = $teacher->classes_list ?? [];
                $pendingLeaves = 0;
                if (!empty($classIds)) {
                    $pendingLeaves = StudentLeave::where('school_id', $schoolId)
                        ->where('status', 'Pending')
                        ->whereHas('student', fn($q) => $q->whereIn('class_id', $classIds))
                        ->count();
                }
                if ($pendingLeaves > 0) {
                    $notifs->push([
                        'id'     => 'teacher_pending_leaves',
                        'icon'   => '📋',
                        'color'  => '#D97706',
                        'title'  => "{$pendingLeaves} leave request" . ($pendingLeaves > 1 ? 's' : '') . ' pending',
                        'body'   => 'Awaiting review',
                        'time'   => 'Today',
                        'unread' => true,
                        'type'   => 'leave',
                        'link'   => '/leaves',
                        'data'   => ['filter' => 'pending'],
                    ]);
                }
            }

            // School announcements for teachers
            $ann = Announcement::where('school_id', $schoolId)
                ->whereIn('audience', ['all', 'staff'])
                ->whereDate('created_at', '>=', today()->subDays(3))
                ->latest()->first();
            if ($ann) {
                $notifs->push([
                    'id'     => 'teacher_ann',
                    'icon'   => '📣',
                    'color'  => '#10B981',
                    'title'  => $ann->title,
                    'body'   => 'School announcement',
                    'time'   => Carbon::parse($ann->created_at)->diffForHumans(),
                    'unread' => false,
                    'type'   => 'announcement',
                    'link'   => '/communications',
                ]);
            }
        }

        // ── STAFF ─────────────────────────────────────────────────
        if ($role === 'staff') {

            // 1. New admission enquiries today
            $newEnquiries = AdmissionEnquiry::where('school_id', $schoolId)
                ->whereDate('created_at', today())
                ->count();
            if ($newEnquiries > 0) {
                $latest = AdmissionEnquiry::where('school_id', $schoolId)
                    ->whereDate('created_at', today())
                    ->latest()->first();
                $notifs->push([
                    'id'     => 'staff_new_enquiries',
                    'icon'   => '🎓',
                    'color'  => '#6366F1',
                    'title'  => 'New admission ' . ($newEnquiries > 1 ? "enquiries ({$newEnquiries})" : 'enquiry'),
                    'body'   => $latest
                        ? "{$latest->student_name} — Class {$latest->apply_class}"
                        : "{$newEnquiries} new enquiries today",
                    'time'   => 'Today',
                    'unread' => true,
                    'type'   => 'admission',
                    'link'   => '/admissions',
                ]);
            }

            // 2. Follow-ups due today
            $followUps = AdmissionEnquiry::where('school_id', $schoolId)
                ->whereDate('follow_up_date', today())
                ->whereNotIn('stage', ['enrolled', 'rejected'])
                ->count();
            if ($followUps > 0) {
                $notifs->push([
                    'id'     => 'staff_follow_ups',
                    'icon'   => '📞',
                    'color'  => '#8B5CF6',
                    'title'  => "{$followUps} follow-up" . ($followUps > 1 ? 's' : '') . ' due today',
                    'body'   => 'Pending admission follow-ups need attention',
                    'time'   => 'Today',
                    'unread' => true,
                    'type'   => 'admission',
                    'link'   => '/admissions',
                ]);
            }

            // 3. Pending leave requests
            $pendingLeaves = DB::table('student_leaves')
                ->where('school_id', $schoolId)
                ->where('status', 'Pending')
                ->count();
            if ($pendingLeaves > 0) {
                $notifs->push([
                    'id'     => 'staff_pending_leaves',
                    'icon'   => '📋',
                    'color'  => '#D97706',
                    'title'  => "{$pendingLeaves} leave request" . ($pendingLeaves > 1 ? 's' : '') . ' pending',
                    'body'   => 'Student leave requests awaiting review',
                    'time'   => 'Today',
                    'unread' => true,
                    'type'   => 'leave',
                    'link'   => '/leaves',
                ]);
            }

            // 4. Pinned announcements (last 14 days)
            $anns = Announcement::where('school_id', $schoolId)
                ->where('is_pinned', true)
                ->whereDate('created_at', '>=', today()->subDays(14))
                ->latest()->take(3)->get();
            foreach ($anns as $ann) {
                $notifs->push([
                    'id'     => "staff_ann_{$ann->id}",
                    'icon'   => '📣',
                    'color'  => '#10B981',
                    'title'  => $ann->title,
                    'body'   => 'Pinned announcement',
                    'time'   => Carbon::parse($ann->created_at)->diffForHumans(),
                    'unread' => false,
                    'type'   => 'announcement',
                    'link'   => '/communications',
                ]);
            }
        }

        // ── PARENT ────────────────────────────────────────────────
        if ($role === 'parent') {
            $children = StudentParent::where('user_id', $user->id)
                ->with('student')->get();

            foreach ($children as $parentRecord) {
                $student = $parentRecord->student;
                if (! $student) continue;

                $sName = $student->name;

                // Absent today
                $todayAtt = Attendance::where('student_id', $student->id)
                    ->whereDate('date', today())
                    ->first();
                if ($todayAtt && $todayAtt->status === 'Absent') {
                    $notifs->push([
                        'id'     => "absent_{$student->id}",
                        'icon'   => '📅',
                        'color'  => '#EF4444',
                        'title'  => "{$sName} marked absent today",
                        'body'   => 'Your child was absent on ' . today()->format('d M Y'),
                        'time'   => 'Today',
                        'unread' => true,
                        'type'   => 'attendance',
                        'link'   => '/parent/attendance',
                    ]);
                }

                // Pending fees
                $pendingFees = FeeInvoice::where('student_id', $student->id)
                    ->whereIn('status', ['Unpaid', 'Partial'])
                    ->sum(DB::raw('total - paid'));
                if ($pendingFees > 0) {
                    $notifs->push([
                        'id'     => "fees_{$student->id}",
                        'icon'   => '💰',
                        'color'  => '#F59E0B',
                        'title'  => "Fee due for {$sName}",
                        'body'   => 'Rs.' . number_format($pendingFees, 0) . ' pending',
                        'time'   => 'Today',
                        'unread' => true,
                        'type'   => 'fee',
                        'link'   => '/parent/fees',
                    ]);
                }

                // Homework due soon (next 2 days)
                $hwDue = Homework::where('class_id', $student->class_id)
                    ->where('status', 'Active')
                    ->whereBetween('due_date', [today(), today()->addDays(2)])
                    ->count();
                if ($hwDue > 0) {
                    $notifs->push([
                        'id'     => "hw_{$student->id}",
                        'icon'   => '📚',
                        'color'  => '#6366F1',
                        'title'  => "{$hwDue} homework due soon",
                        'body'   => "Due within 2 days for {$sName}",
                        'time'   => 'This week',
                        'unread' => false,
                        'type'   => 'homework',
                        'link'   => '/parent/homework',
                    ]);
                }

                // Upcoming exams (next 7 days)
                $exam = Exam::where('class_id', $student->class_id)
                    ->where('start_date', '>=', today())
                    ->orderBy('start_date')->first();
                if ($exam) {
                    $days = today()->diffInDays(Carbon::parse($exam->start_date));
                    if ($days <= 7) {
                        $notifs->push([
                            'id'     => "exam_{$student->id}",
                            'icon'   => '📝',
                            'color'  => '#3B82F6',
                            'title'  => "Exam in {$days} day" . ($days !== 1 ? 's' : ''),
                            'body'   => "{$exam->name} for {$sName}",
                            'time'   => 'This week',
                            'unread' => false,
                            'type'   => 'exam',
                            'link'   => '/parent/exams',
                        ]);
                    }
                }

                // Approved leave this week
                $approvedLeave = DB::table('student_leaves')
                    ->where('student_id', $student->id)
                    ->where('status', 'Approved')
                    ->whereDate('reviewed_at', '>=', today()->subDays(3))
                    ->first();
                if ($approvedLeave) {
                    $notifs->push([
                        'id'     => "leave_approved_{$student->id}",
                        'icon'   => '📋',
                        'color'  => '#10B981',
                        'title'  => "Leave approved for {$sName}",
                        'body'   => ucfirst($approvedLeave->leave_type) . ' leave from ' . Carbon::parse($approvedLeave->from_date)->format('d M') . ' to ' . Carbon::parse($approvedLeave->to_date)->format('d M'),
                        'time'   => Carbon::parse($approvedLeave->reviewed_at)->diffForHumans(),
                        'unread' => false,
                        'type'   => 'leave',
                        'link'   => '/parent/leaves',
                    ]);
                }
            }

            // School announcements for parents (up to 5 recent)
            $anns = Announcement::where('school_id', $schoolId)
                ->whereIn('audience', ['all', 'parents', 'students'])
                ->whereDate('created_at', '>=', today()->subDays(14))
                ->latest()->take(5)->get();
            foreach ($anns as $ann) {
                $notifs->push([
                    'id'     => "parent_ann_{$ann->id}",
                    'icon'   => '📣',
                    'color'  => '#10B981',
                    'title'  => $ann->title,
                    'body'   => 'School announcement',
                    'time'   => Carbon::parse($ann->created_at)->diffForHumans(),
                    'unread' => false,
                    'type'   => 'announcement',
                    'link'   => '/parent/notifications',
                ]);
            }
        }

        // If user recently marked all as read, zero-out the unread flag
        $lastSeen = $user->notifications_last_seen_at;
        if ($lastSeen && $lastSeen->greaterThanOrEqualTo(today())) {
            $notifs = $notifs->map(function ($n) {
                $n['unread'] = false;
                return $n;
            });
            $unreadCount = 0;
        } else {
            $unreadCount = $notifs->where('unread', true)->count();
        }

        return response()->json([
            'success'      => true,
            'data'         => $notifs->values(),
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        // Notifications are computed in-memory; a per-item read marker isn't
        // persisted, so this endpoint just updates the "last seen" timestamp
        // so the badge decrements on the next index() call.
        $user = $request->user();
        $user->update(['notifications_last_seen_at' => now()]);

        return response()->json([
            'success'      => true,
            'message'      => 'Notification marked as read',
            'unread_count' => 0,
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['notifications_last_seen_at' => now()]);

        return response()->json([
            'success'      => true,
            'message'      => 'All notifications marked as read',
            'unread_count' => 0,
        ]);
    }

    public function getUnreadCount(Request $request): JsonResponse
    {
        $user     = $request->user();
        $lastSeen = $user->notifications_last_seen_at;

        // If the user marked all as read today, the badge should stay at 0
        // until the next calendar day (when new notifications roll in).
        if ($lastSeen && $lastSeen->greaterThanOrEqualTo(today())) {
            return response()->json(['success' => true, 'count' => 0]);
        }

        // Otherwise re-run index() logic to count unread items
        $response = $this->index($request);
        $payload  = $response->getData(true);

        return response()->json([
            'success' => true,
            'count'   => $payload['unread_count'] ?? 0,
        ]);
    }
}
