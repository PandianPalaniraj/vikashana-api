<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SubscriptionInvoice::with(['school:id,name,phone,email', 'payments']);

        if ($request->filled('status'))    $query->where('status', $request->status);
        if ($request->filled('school_id')) $query->where('school_id', $request->school_id);
        if ($request->filled('from'))      $query->whereDate('created_at', '>=', $request->from);
        if ($request->filled('to'))        $query->whereDate('created_at', '<=', $request->to);

        $invoices = $query->orderByDesc('created_at')->paginate(20);

        $summary = SubscriptionInvoice::selectRaw('
            SUM(total) as total_billed,
            SUM(CASE WHEN status = "paid" THEN total ELSE 0 END) as total_collected,
            SUM(CASE WHEN status IN ("sent","partial","overdue") THEN total ELSE 0 END) as total_pending,
            COUNT(*) as total_count,
            COUNT(CASE WHEN status = "overdue" THEN 1 END) as overdue_count
        ')->first();

        $items = $invoices->items();
        foreach ($items as &$inv) {
            $inv->total_paid = $inv->total_paid;
            $inv->balance    = $inv->balance;
        }

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'page'      => $invoices->currentPage(),
                'total'     => $invoices->total(),
                'last_page' => $invoices->lastPage(),
                'per_page'  => $invoices->perPage(),
            ],
            'summary' => $summary,
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id'     => 'required|exists:schools,id',
            'billing_cycle' => 'required|in:monthly,annual',
            'period_from'   => 'nullable|date',
            'due_date'      => 'nullable|date',
            'notes'         => 'nullable|string',
            'send_now'      => 'boolean',
        ]);

        $sub = Subscription::where('school_id', $data['school_id'])->first();
        if (!$sub) {
            return response()->json(['success' => false, 'message' => 'No subscription found for this school. Please set up a subscription first.'], 422);
        }

        $studentCount = Student::where('school_id', $data['school_id'])
            ->where('status', 'Active')->count();
        $sub->update(['student_count' => $studentCount]);

        $calc   = SubscriptionInvoice::calculate($sub->plan, $data['billing_cycle'], max($studentCount, 1));
        $period = SubscriptionInvoice::periodDates($data['billing_cycle'], $data['period_from'] ?? null);

        $sendNow = $data['send_now'] ?? true;

        $invoice = SubscriptionInvoice::create([
            'school_id'        => $data['school_id'],
            'subscription_id'  => $sub->id,
            'invoice_no'       => SubscriptionInvoice::generateInvoiceNo(),
            'period_label'     => $period['label'],
            'period_start'     => $period['start'],
            'period_end'       => $period['end'],
            'student_count'    => max($studentCount, 1),
            'rate_per_student' => $calc['rate'],
            'billing_cycle'    => $data['billing_cycle'],
            'subtotal'         => $calc['subtotal'],
            'gst_percent'      => 18,
            'gst_amount'       => $calc['gstAmt'],
            'total'            => $calc['total'],
            'status'           => $sendNow ? 'sent' : 'draft',
            'sent_at'          => $sendNow ? now() : null,
            'due_date'         => isset($data['due_date']) ? Carbon::parse($data['due_date']) : $period['due'],
            'notes'            => $data['notes'] ?? null,
            'created_by'       => $request->user()->id,
        ]);

        $sub->update(['billing_cycle' => $data['billing_cycle']]);

        ActivityLog::create([
            'school_id'   => $data['school_id'],
            'user_id'     => $request->user()->id,
            'module'      => 'invoices',
            'action'      => 'invoice_generated',
            'description' => "Invoice {$invoice->invoice_no} for {$invoice->period_label}",
        ]);

        $invoice->load('school');

        return response()->json([
            'success'           => true,
            'message'           => 'Invoice generated',
            'data'              => $invoice,
            'whatsapp_message'  => $this->buildWhatsAppMessage($invoice, $sub->plan),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $invoice = SubscriptionInvoice::with(['school', 'subscription', 'payments', 'createdBy:id,name'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => array_merge($invoice->toArray(), [
                'total_paid' => $invoice->total_paid,
                'balance'    => $invoice->balance,
                'is_overdue' => $invoice->isOverdue(),
            ]),
        ]);
    }

    public function markSent(int $id): JsonResponse
    {
        $invoice = SubscriptionInvoice::findOrFail($id);
        abort_if($invoice->status === 'paid', 422, 'Invoice already paid');
        $invoice->update(['status' => 'sent', 'sent_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Invoice marked as sent', 'data' => $invoice]);
    }

    public function cancel(int $id): JsonResponse
    {
        $invoice = SubscriptionInvoice::findOrFail($id);
        abort_if($invoice->status === 'paid', 422, 'Cannot cancel a paid invoice');
        $invoice->update(['status' => 'cancelled']);
        return response()->json(['success' => true, 'message' => 'Invoice cancelled']);
    }

    public function recordPayment(Request $request, int $id): JsonResponse
    {
        $invoice = SubscriptionInvoice::with('subscription')->findOrFail($id);
        abort_if($invoice->status === 'cancelled', 422, 'Cannot record payment on cancelled invoice');

        $data = $request->validate([
            'amount'       => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'method'       => 'required|in:cash,upi,bank_transfer,cheque,online,other',
            'reference_no' => 'nullable|string|max:100',
            'notes'        => 'nullable|string',
        ]);

        SubscriptionPayment::create([
            'school_id'       => $invoice->school_id,
            'subscription_id' => $invoice->subscription_id,
            'invoice_id'      => $invoice->id,
            'amount'          => $data['amount'],
            'payment_date'    => $data['payment_date'],
            'method'          => $data['method'],
            'period_label'    => $invoice->period_label,
            'reference_no'    => $data['reference_no'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'recorded_by'     => $request->user()->id,
        ]);

        $totalPaid = $invoice->payments()->sum('amount');
        $newStatus = match(true) {
            $totalPaid >= $invoice->total => 'paid',
            $totalPaid > 0               => 'partial',
            default                      => 'sent',
        };

        $invoice->update([
            'status'  => $newStatus,
            'paid_at' => $newStatus === 'paid' ? now() : null,
        ]);

        $activated   = false;
        $renewalDate = null;
        if ($newStatus === 'paid') {
            $sub         = $invoice->subscription;
            $nextRenewal = Carbon::parse($invoice->period_end)->addDay();
            $sub->update([
                'status'         => 'active',
                'trial_ends_at'  => null,
                'renewal_date'   => $nextRenewal,
                'paid_until'     => $invoice->period_end,
                'mobile_enabled' => in_array($sub->plan, ['pro', 'premium', 'enterprise']),
                'monthly_amount' => $invoice->billing_cycle === 'annual'
                    ? round($invoice->subtotal / 12, 2)
                    : $invoice->subtotal,
            ]);
            $activated   = true;
            $renewalDate = $nextRenewal->format('d M Y');
        }

        ActivityLog::create([
            'school_id'   => $invoice->school_id,
            'user_id'     => $request->user()->id,
            'module'      => 'invoices',
            'action'      => 'invoice_payment',
            'description' => "₹{$data['amount']} on invoice {$invoice->invoice_no}",
        ]);

        $freshPaid   = (float) $invoice->payments()->sum('amount');
        $freshBal    = max(0, $invoice->total - $freshPaid);

        return response()->json([
            'success'      => true,
            'message'      => $newStatus === 'paid' ? 'Invoice fully paid. Subscription activated.' : 'Payment recorded.',
            'data'         => [
                'invoice'      => $invoice->fresh(),
                'total_paid'   => $freshPaid,
                'balance'      => $freshBal,
                'status'       => $newStatus,
                'activated'    => $activated,
                'renewal_date' => $renewalDate,
            ],
        ]);
    }

    // PUT /invoices/{id} — edit non-financial fields
    public function update(Request $request, int $id): JsonResponse
    {
        $invoice = SubscriptionInvoice::findOrFail($id);

        $data = $request->validate([
            'password'     => 'required|string',
            'period_label' => 'sometimes|string|max:100',
            'due_date'     => 'sometimes|date',
            'status'       => 'sometimes|in:draft,sent,partial,paid,overdue,cancelled',
            'notes'        => 'nullable|string',
        ]);

        if (!Hash::check($data['password'], $request->user()->password)) {
            return response()->json(['success' => false, 'message' => 'Incorrect password.'], 403);
        }

        unset($data['password']);
        $invoice->update($data);

        ActivityLog::create([
            'school_id'   => $invoice->school_id,
            'user_id'     => $request->user()->id,
            'module'      => 'invoices',
            'action'      => 'invoice_updated',
            'description' => "Invoice {$invoice->invoice_no} updated",
        ]);

        return response()->json(['success' => true, 'message' => 'Invoice updated', 'data' => $invoice->fresh()]);
    }

    // DELETE /invoices/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $invoice = SubscriptionInvoice::with('payments')->findOrFail($id);

        $data = $request->validate([
            'password' => 'required|string',
        ]);

        if (!Hash::check($data['password'], $request->user()->password)) {
            return response()->json(['success' => false, 'message' => 'Incorrect password.'], 403);
        }

        $invoiceNo = $invoice->invoice_no;
        $invoice->payments()->delete();
        $invoice->delete();

        ActivityLog::create([
            'school_id'   => $invoice->school_id,
            'user_id'     => $request->user()->id,
            'module'      => 'invoices',
            'action'      => 'invoice_deleted',
            'description' => "Invoice {$invoiceNo} deleted",
        ]);

        return response()->json(['success' => true, 'message' => "Invoice {$invoiceNo} deleted."]);
    }

    // GET /invoices/schools — for dropdown
    public function schools(): JsonResponse
    {
        $schools = School::select('id', 'name')->with('subscription:id,school_id,plan,status,student_count,billing_cycle')->orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $schools]);
    }

    private function buildWhatsAppMessage(SubscriptionInvoice $invoice, string $plan): string
    {
        $school = $invoice->school;
        $lines = [
            "🏫 *Vikashana Subscription Invoice*",
            "━━━━━━━━━━━━━━",
            "",
            "📌 Invoice No: *{$invoice->invoice_no}*",
            "🏫 School: {$school->name}",
            "📅 Period: {$invoice->period_label}",
            "👨‍🎓 Students: {$invoice->student_count}",
            "📋 Plan: " . ucfirst($plan) . " Plan",
            "",
            "💰 *Amount Details:*",
            "Rate: ₹{$invoice->rate_per_student}/student",
            "Subtotal: ₹{$invoice->subtotal}",
            "GST (18%): ₹{$invoice->gst_amount}",
            "*Total: ₹{$invoice->total}*",
            "",
            "📅 Due Date: " . Carbon::parse($invoice->due_date)->format('d M Y'),
            "",
            "💳 *Payment Details:*",
            "UPI: payments@vikashana.com",
            "",
            "After payment, please share the transaction screenshot.",
            "",
            "Thank you! 🙏",
            "Team Vikashana",
        ];
        return implode("\n", $lines);
    }
}
