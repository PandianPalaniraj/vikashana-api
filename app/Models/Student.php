<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'school_id','user_id','academic_year_id','class_id','section_id',
        'admission_no','name','dob','gender','blood_group','photo',
        'address','religion','nationality','status','admission_date',
    ];

    protected $casts = [
        'dob'            => 'date',
        'admission_date' => 'date',
    ];

    public function school(): BelongsTo       { return $this->belongsTo(School::class); }
    public function user(): BelongsTo         { return $this->belongsTo(User::class); }
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function schoolClass(): BelongsTo  { return $this->belongsTo(SchoolClass::class,'class_id'); }
    public function section(): BelongsTo      { return $this->belongsTo(Section::class); }

    public function parents(): HasMany        { return $this->hasMany(StudentParent::class); }
    public function documents(): HasMany      { return $this->hasMany(StudentDocument::class); }
    public function attendance(): HasMany     { return $this->hasMany(Attendance::class); }
    public function feeInvoices(): HasMany    { return $this->hasMany(FeeInvoice::class); }
    public function marks(): HasMany          { return $this->hasMany(Mark::class); }

    public function primaryParent(): ?StudentParent {
        return $this->parents()->where('is_primary', true)->first()
            ?? $this->parents()->first();
    }

    // Attendance % for current month
    public function attendancePercentage(string $month = null): float {
        $query = $this->attendance();
        if ($month) $query->whereRaw("DATE_FORMAT(date,'%Y-%m') = ?", [$month]);
        $total   = $query->count();
        if ($total === 0) return 0;
        $present = $query->whereIn('status',['Present','Late'])->count();
        return round(($present / $total) * 100, 1);
    }
}
