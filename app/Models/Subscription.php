<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'school_id', 'plan', 'status', 'trial_ends_at',
        'renewal_date', 'amount', 'billing_cycle', 'notes',
        'student_count', 'amount_per_student', 'monthly_amount',
        'mobile_enabled', 'features', 'paid_until',
    ];

    protected $casts = [
        'trial_ends_at'      => 'datetime',
        'renewal_date'       => 'date',
        'paid_until'         => 'date',
        'amount'             => 'float',
        'amount_per_student' => 'float',
        'monthly_amount'     => 'float',
        'mobile_enabled'     => 'boolean',
        'features'           => 'array',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    // ── Plan limits ────────────────────────────────────────────────────────────

    public static function getLimits(string $plan): array {
        return match($plan) {
            'starter' => [
                'max_students' => 999999,
                'max_teachers' => 999999,
                'mobile'       => false,
                'modules'      => 'all',
            ],
            'pro' => [
                'max_students' => 999999,
                'max_teachers' => 999999,
                'mobile'       => true,
                'modules'      => 'all',
            ],
            'premium' => [
                'max_students' => 999999,
                'max_teachers' => 999999,
                'mobile'       => true,
                'modules'      => 'all',
                'extras'       => ['payroll','transport_gps'],
            ],
            'enterprise' => [
                'max_students' => 999999,
                'max_teachers' => 999999,
                'mobile'       => true,
                'modules'      => 'all',
                'extras'       => 'custom',
            ],
            default => [
                'max_students' => 999999,
                'max_teachers' => 999999,
                'mobile'       => true,
                'modules'      => 'all',
            ],
        };
    }

    // ── Pricing ────────────────────────────────────────────────────────────────

    public static function getPricing(): array {
        return [
            'starter'    => [
                'per_student_monthly' => 15,
                'per_student_annual'  => 12.50,
            ],
            'pro'        => [
                'per_student_monthly' => 25,
                'per_student_annual'  => 20.83,
            ],
            'premium'    => [
                'per_student_monthly' => 40,
                'per_student_annual'  => 33.33,
            ],
            'enterprise' => [
                'per_student_monthly' => 'custom',
                'per_student_annual'  => 'custom',
            ],
        ];
    }

    // ── Computed monthly amount ────────────────────────────────────────────────

    public function getCalculatedAmountAttribute(): float
    {
        $pricing = static::getPricing();
        $rate    = $pricing[$this->plan]['per_student_monthly'] ?? 0;
        if (!is_numeric($rate) || $rate == 0) return 0;

        $count = $this->student_count ?: 0;
        $base  = $count * $rate;

        if ($this->billing_cycle === 'annual') {
            // Pay 10 months, get 12 (2 months free)
            return round($base * 10 / 12, 2);
        }

        return $base;
    }

    // ── Trial helpers ──────────────────────────────────────────────────────────

    public function isTrialActive(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function getTrialDaysLeftAttribute(): int
    {
        if (!$this->isTrialActive()) return 0;
        return (int) now()->diffInDays($this->trial_ends_at, false);
    }

    // ── Mobile access ──────────────────────────────────────────────────────────

    public function isMobileAllowed(): bool
    {
        return (bool) $this->mobile_enabled;
    }

    // ── Status helpers ─────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial']);
    }

    public function isExpiringSoon(): bool
    {
        return $this->renewal_date
            && $this->renewal_date->isFuture()
            && $this->renewal_date->diffInDays(now()) <= 7;
    }

    // ── Sync student count ─────────────────────────────────────────────────────

    public function syncStudentCount(): void
    {
        $count   = Student::where('school_id', $this->school_id)
            ->where('status', 'Active')
            ->count();

        $pricing = static::getPricing();
        $rate    = $pricing[$this->plan]['per_student_monthly'] ?? 0;
        $monthly = is_numeric($rate) ? $count * $rate : 0;

        $this->update([
            'student_count'     => $count,
            'amount_per_student'=> is_numeric($rate) ? $rate : 0,
            'monthly_amount'    => $monthly,
        ]);
    }

    // ── Grace period & block helpers ───────────────────────────────────────────

    // Grace period: 15 days after due date before expiry
    const GRACE_DAYS = 15;

    public function isInGracePeriod(): bool
    {
        if ($this->status !== 'overdue') return false;
        $oldestOverdue = \App\Models\SubscriptionInvoice
            ::where('school_id', $this->school_id)
            ->where('status', 'overdue')
            ->orderBy('due_date')
            ->value('due_date');
        if (!$oldestOverdue) return true;
        return \Carbon\Carbon::parse($oldestOverdue)
            ->addDays(static::GRACE_DAYS)
            ->isFuture();
    }

    public function getGraceDaysLeftAttribute(): int
    {
        if ($this->status !== 'overdue') return 0;
        $oldestOverdue = \App\Models\SubscriptionInvoice
            ::where('school_id', $this->school_id)
            ->where('status', 'overdue')
            ->orderBy('due_date')
            ->value('due_date');
        if (!$oldestOverdue) return static::GRACE_DAYS;
        $expiry = \Carbon\Carbon::parse($oldestOverdue)
            ->addDays(static::GRACE_DAYS);
        return max(0, (int) now()->diffInDays($expiry, false));
    }

    public function isBlocked(): bool
    {
        if ($this->status === 'cancelled') return true;
        if ($this->status === 'expired') return true;
        if ($this->status === 'overdue' && !$this->isInGracePeriod()) return true;
        return false;
    }
}
