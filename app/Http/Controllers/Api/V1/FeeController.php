<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Student;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeController extends Controller
{
    // ── Shared formatter ──────────────────────────────────────────────────────

    private function formatInvoice(FeeInvoice $inv, bool $withPayments = false): array
    {
        $s = $inv->student;

        $data = [
            'id'               => $inv->id,
            'student_id'       => $inv->student_id,
            'academic_year_id' => $inv->academic_year_id,
            'invoice_no'       => $inv->invoice_no,
            'month'            => $inv->month,
            'items'            => $inv->items ?? [],
            'total'            => (float) $inv->total,
            'paid'             => (float) $inv->paid,
            'discount'         => (float) $inv->discount,
            'status'           => $inv->status,
            'due_date'         => $inv->due_date?->toDateString(),
            'notes'            => $inv->notes,
            'wa_sent'          => (bool) $inv->wa_sent,
            'rcpt_sent'        => (bool) $inv->receipt_sent,
            'student'          => $s ? [
                'id'           => $s->id,
                'name'         => $s->name,
                'admission_no' => $s->admission_no,
                'class_id'     => $s->class_id,
                'section_id'   => $s->section_id,
                'class'        => $s->schoolClass
                                    ? ['id' => $s->schoolClass->id, 'name' => $s->schoolClass->name]
                                    : null,
                'section'      => $s->section
                                    ? ['id' => $s->section->id, 'name' => $s->section->name]
                                    : null,
                'parents'      => $s->parents
                                    ->map(fn($p) => ['name' => $p->name, 'phone' => $p->phone])
                                    ->values()
                                    ->all(),
            ] : null,
        ];

        if ($withPayments) {
            $data['payments'] = $inv->payments
                ->map(fn($p) => [
                    'id'        => $p->id,
                    'amount'    => (float) $p->amount,
                    'method'    => $p->method,
                    'reference' => $p->reference,
                    'paid_at'   => $p->paid_at?->toDateTimeString(),
                    'remarks'   => $p->remarks,
                ])
                ->values()
                ->all();
        }

        return $data;
    }

    private function generateInvoiceNo(int $schoolId): string
    {
        $prefix = 'INV' . now()->format('ym');
        $count  = FeeInvoice::where('school_id', $schoolId)->count() + 1;
        return $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    // ── GET /fees/invoices ────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        // Parents must use GET /students/{id}/fees — this endpoint lists all school invoices
        abort_if($request->user()->role === 'parent', 403, 'Use /students/{id}/fees instead');

        $schoolId = $request->user()->school_id;
        $perPage  = min((int) $request->input('per_page', 20), 100);

        $withPayments = $request->boolean('with_payments');
        $relations    = ['student.schoolClass', 'student.section', 'student.parents'];
        if ($withPayments) $relations[] = 'payments';

        $query = FeeInvoice::where('school_id', $schoolId)
            ->with($relations)
            ->orderByDesc('created_at');

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('class_id')) $query->whereHas('student', fn($q) => $q->where('class_id', $request->class_id));
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn($sq) =>
                $sq->where('invoice_no', 'like', "%{$q}%")
                   ->orWhereHas('student', fn($sq2) => $sq2->where('name', 'like', "%{$q}%")
                                                           ->orWhere('admission_no', 'like', "%{$q}%"))
            );
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => collect($paginated->items())->map(fn($i) => $this->formatInvoice($i, $withPayments)),
            'meta'    => [
                'page'      => $paginated->currentPage(),
                'total'     => $paginated->total(),
                'per_page'  => $paginated->perPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    // ── POST /fees/invoices ───────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.label'    => 'required|string|max:100',
            'items.*.amount'   => 'required|numeric|min:0',
            'academic_year_id' => 'required|exists:academic_years,id',
            'due_date'         => 'nullable|date',
            'month'            => 'nullable|string|max:30',
            'notes'            => 'nullable|string|max:500',
        ]);

        $items = collect($request->items)->map(fn($i) => [
            'label'  => $i['label'],
            'amount' => (float) $i['amount'],
        ]);
        $total = $items->sum('amount');

        // ── Bulk by class ────────────────────────────────────────────────────
        if ($request->filled('class_id')) {
            $studentQuery = Student::where('school_id', $schoolId)
                ->where('class_id', $request->class_id)
                ->where('status', 'Active');

            if ($request->filled('section_id')) {
                $studentQuery->where('section_id', $request->section_id);
            }

            $students = $studentQuery->get();

            if ($students->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active students found in this class/section',
                ], 422);
            }

            $created = 0;
            DB::transaction(function () use ($students, $request, $schoolId, $items, $total, &$created) {
                foreach ($students as $student) {
                    FeeInvoice::create([
                        'school_id'        => $schoolId,
                        'student_id'       => $student->id,
                        'academic_year_id' => $request->academic_year_id,
                        'invoice_no'       => $this->generateInvoiceNo($schoolId),
                        'month'            => $request->month,
                        'items'            => $items->all(),
                        'total'            => $total,
                        'paid'             => 0,
                        'discount'         => 0,
                        'status'           => 'Unpaid',
                        'due_date'         => $request->due_date,
                        'notes'            => $request->notes,
                    ]);
                    $created++;
                }
            });

            return response()->json([
                'success' => true,
                'message' => "{$created} invoices created",
                'data'    => ['count' => $created],
            ], 201);
        }

        // ── Individual ───────────────────────────────────────────────────────
        $request->validate(['student_id' => 'required|exists:students,id']);

        $student = Student::where('school_id', $schoolId)
            ->where('id', $request->student_id)
            ->firstOrFail();

        $inv = FeeInvoice::create([
            'school_id'        => $schoolId,
            'student_id'       => $student->id,
            'academic_year_id' => $request->academic_year_id,
            'invoice_no'       => $this->generateInvoiceNo($schoolId),
            'month'            => $request->month,
            'items'            => $items->all(),
            'total'            => $total,
            'paid'             => 0,
            'discount'         => 0,
            'status'           => 'Unpaid',
            'due_date'         => $request->due_date,
            'notes'            => $request->notes,
        ]);

        $inv->load(['student.schoolClass', 'student.section', 'student.parents']);

        // Push notification: notify parent of new fee invoice
        try {
            $parentUserIds = $student->parents
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->toArray();
            if (!empty($parentUserIds)) {
                $dueStr = $request->due_date ? ' — Due: ' . \Carbon\Carbon::parse($request->due_date)->format('d M') : '';
                app(PushNotificationService::class)->sendToUsers($parentUserIds,
                    '💰 New Fee Invoice',
                    "₹{$total} fee invoice created for {$student->name}{$dueStr}",
                    ['screen' => 'Fees']
                );
            }
        } catch (\Throwable $e) {
            // Non-fatal
        }

        return response()->json([
            'success' => true,
            'message' => "Invoice {$inv->invoice_no} created for {$student->name}",
            'data'    => $this->formatInvoice($inv),
        ], 201);
    }

    // ── GET /fees/invoices/{id} ───────────────────────────────────────────────

    public function show(Request $request, FeeInvoice $invoice): JsonResponse
    {
        abort_if($invoice->school_id !== $request->user()->school_id, 403, 'Forbidden');

        $invoice->load(['student.schoolClass', 'student.section', 'student.parents', 'payments']);

        return response()->json(['success' => true, 'data' => $this->formatInvoice($invoice, true)]);
    }

    // ── PUT /fees/invoices/{id} ───────────────────────────────────────────────

    public function update(Request $request, FeeInvoice $invoice): JsonResponse
    {
        abort_if($invoice->school_id !== $request->user()->school_id, 403, 'Forbidden');

        $validated = $request->validate([
            'items'          => 'sometimes|array|min:1',
            'items.*.label'  => 'required_with:items|string|max:100',
            'items.*.amount' => 'required_with:items|numeric|min:0',
            'due_date'       => 'nullable|date',
            'notes'          => 'nullable|string|max:500',
        ]);

        if (isset($validated['items'])) {
            $validated['total'] = collect($validated['items'])->sum('amount');
        }

        $invoice->update($validated);

        return response()->json(['success' => true, 'message' => 'Invoice updated']);
    }

    // ── DELETE /fees/invoices/{id} ────────────────────────────────────────────

    public function destroy(Request $request, FeeInvoice $invoice): JsonResponse
    {
        abort_if($invoice->school_id !== $request->user()->school_id, 403, 'Forbidden');

        $invoice->delete();

        return response()->json(['success' => true, 'message' => 'Invoice deleted']);
    }

    // ── POST /fees/invoices/{id}/pay ──────────────────────────────────────────

    public function recordPayment(Request $request, FeeInvoice $invoice): JsonResponse
    {
        abort_if($invoice->school_id !== $request->user()->school_id, 403, 'Forbidden');

        $validated = $request->validate([
            'amount'    => 'required|numeric|min:0.01',
            'method'    => 'required|in:Cash,Online,Cheque,DD,UPI',
            'reference' => 'nullable|string|max:100',
            'paid_at'   => 'nullable|date',
        ]);

        $balance = (float)$invoice->total - (float)$invoice->paid - (float)$invoice->discount;

        if ($validated['amount'] > $balance + 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Amount exceeds outstanding balance of ' . number_format($balance, 2),
            ], 422);
        }

        DB::transaction(function () use ($invoice, $validated, $request) {
            FeePayment::create([
                'invoice_id'  => $invoice->id,
                'amount'      => $validated['amount'],
                'method'      => $validated['method'],
                'reference'   => $validated['reference'] ?? null,
                'paid_at'     => $validated['paid_at'] ?? now(),
                'received_by' => $request->user()->id,
            ]);

            $newPaid = (float)$invoice->paid + (float)$validated['amount'];
            $dueAmt  = (float)$invoice->total - (float)$invoice->discount;

            $invoice->update([
                'paid'   => $newPaid,
                'status' => $newPaid >= $dueAmt ? 'Paid' : 'Partial',
            ]);
        });

        ActivityLog::log(
            $request->user()->id, $invoice->school_id,
            'payment', 'fees',
            "Recorded fee payment of Rs.{$validated['amount']} for student ID {$invoice->student_id}",
            '💰'
        );

        return response()->json(['success' => true, 'message' => 'Payment recorded']);
    }

    // ── GET /fees/invoices/{id}/receipt ──────────────────────────────────────

    public function receipt(Request $request, FeeInvoice $invoice): JsonResponse
    {
        abort_if($invoice->school_id !== $request->user()->school_id, 403, 'Forbidden');

        $invoice->load(['student.schoolClass', 'student.section', 'student.parents', 'payments']);

        return response()->json(['success' => true, 'data' => $this->formatInvoice($invoice, true)]);
    }

    // ── GET /fees/summary ─────────────────────────────────────────────────────

    public function summary(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $stats = FeeInvoice::where('school_id', $schoolId)
            ->selectRaw("
                COALESCE(SUM(total), 0)                         as billed,
                COALESCE(SUM(paid), 0)                          as collected,
                COALESCE(SUM(total - paid - discount), 0)       as due,
                COUNT(*)                                        as invoices,
                SUM(status = 'Unpaid')                          as unpaid,
                SUM(status = 'Partial')                         as partial,
                SUM(status = 'Paid')                            as paid
            ")
            ->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'billed'    => (float) ($stats->billed    ?? 0),
                'collected' => (float) ($stats->collected ?? 0),
                'due'       => (float) ($stats->due       ?? 0),
                'invoices'  => (int)   ($stats->invoices  ?? 0),
                'unpaid'    => (int)   ($stats->unpaid    ?? 0),
                'partial'   => (int)   ($stats->partial   ?? 0),
                'paid'      => (int)   ($stats->paid      ?? 0),
            ],
        ]);
    }

    // ── POST /fees/bulk (alias for bulk store) ────────────────────────────────

    public function createBulk(Request $request): JsonResponse
    {
        return $this->store($request);
    }
}
