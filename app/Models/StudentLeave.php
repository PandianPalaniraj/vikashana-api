<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentLeave extends Model
{
    protected $table = 'student_leaves';

    protected $fillable = [
        'school_id', 'student_id', 'applied_by',
        'leave_type', 'from_date', 'to_date', 'reason',
        'status', 'remarks', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'from_date'   => 'date',
        'to_date'     => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function appliedBy()
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getTotalDaysAttribute(): int
    {
        if (!$this->from_date || !$this->to_date) return 0;
        return $this->from_date->diffInDays($this->to_date) + 1;
    }
}
