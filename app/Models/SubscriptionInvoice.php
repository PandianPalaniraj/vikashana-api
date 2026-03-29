<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SubscriptionInvoice extends Model
{
    protected $fillable = [
        'school_id','subscription_id','invoice_no',
        'period_label','period_start','period_end',
        'student_count','rate_per_student','billing_cycle',
        'subtotal','gst_percent','gst_amount','total',
        'status','due_date','sent_at','paid_at',
        'notes','created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'due_date'     => 'date',
        'sent_at'      => 'datetime',
        'paid_at'      => 'datetime',
        'subtotal'     => 'float',
        'gst_amount'   => 'float',
        'total'        => 'float',
        'rate_per_student' => 'float',
    ];

    public function school()      { return $this->belongsTo(School::class); }
    public function subscription(){ return $this->belongsTo(Subscription::class); }
    public function payments()    { return $this->hasMany(SubscriptionPayment::class, 'invoice_id'); }
    public function createdBy()   { return $this->belongsTo(User::class, 'created_by'); }

    public static function generateInvoiceNo(): string
    {
        $year  = now()->format('Y');
        $count = static::whereYear('created_at', $year)->count() + 1;
        return 'VID-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    public static function calculate(string $plan, string $cycle, int $students): array
    {
        $rates = [
            'starter'    => ['monthly' => 15.00,    'annual' => 12.50],
            'pro'        => ['monthly' => 25.00,    'annual' => 20.83],
            'premium'    => ['monthly' => 40.00,    'annual' => 33.33],
            'enterprise' => ['monthly' => 0.00,     'annual' => 0.00],
            'free'       => ['monthly' => 0.00,     'annual' => 0.00],
        ];
        $rate     = $rates[$plan][$cycle] ?? 0;
        $months   = $cycle === 'annual' ? 12 : 1;
        $subtotal = round($rate * $students * $months, 2);
        $gstPct   = 18;
        $gstAmt   = round($subtotal * $gstPct / 100, 2);
        $total    = $subtotal + $gstAmt;
        return compact('rate', 'subtotal', 'gstPct', 'gstAmt', 'total', 'months');
    }

    public static function periodDates(string $cycle, ?string $from = null): array
    {
        $start = $from ? Carbon::parse($from)->startOfMonth() : now()->startOfMonth();
        $end   = $cycle === 'annual'
            ? $start->copy()->addYear()->subDay()
            : $start->copy()->endOfMonth();
        $due   = $start->copy()->addDays(15);
        $label = $cycle === 'annual'
            ? $start->format('Y') . '-' . $start->copy()->addYear()->format('y') . ' Annual'
            : $start->format('F Y');
        return compact('start', 'end', 'due', 'label');
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return max(0, $this->total - $this->total_paid);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid'
            && $this->status !== 'cancelled'
            && $this->due_date
            && $this->due_date->isPast();
    }
}
