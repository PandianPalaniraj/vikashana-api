<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolBillingController extends Controller
{
    public function myInvoices(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        if (!$schoolId) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $invoices = SubscriptionInvoice::where('school_id', $schoolId)
            ->whereIn('status', ['sent', 'partial', 'paid', 'overdue'])
            ->with('payments')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($inv) => [
                'id'            => $inv->id,
                'invoice_no'    => $inv->invoice_no,
                'period_label'  => $inv->period_label,
                'period_start'  => $inv->period_start?->toDateString(),
                'period_end'    => $inv->period_end?->toDateString(),
                'student_count' => $inv->student_count,
                'billing_cycle' => $inv->billing_cycle,
                'subtotal'      => $inv->subtotal,
                'gst_amount'    => $inv->gst_amount,
                'total'         => $inv->total,
                'total_paid'    => $inv->total_paid,
                'balance'       => $inv->balance,
                'status'        => $inv->status,
                'due_date'      => $inv->due_date?->toDateString(),
                'paid_at'       => $inv->paid_at?->toISOString(),
                'payments'      => $inv->payments->map(fn($p) => [
                    'id'           => $p->id,
                    'amount'       => $p->amount,
                    'payment_date' => $p->payment_date?->toDateString(),
                    'method'       => $p->method,
                    'reference_no' => $p->reference_no,
                ]),
            ]);

        return response()->json(['success' => true, 'data' => $invoices]);
    }
}
