<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class School extends Model
{
    protected static function booted(): void
    {
        static::created(function (School $school) {
            Subscription::create([
                'school_id'      => $school->id,
                'plan'           => 'pro',
                'status'         => 'trial',
                'trial_ends_at'  => now()->addDays(30),
                'mobile_enabled' => true,
                'student_count'  => 0,
                'monthly_amount' => 0,
            ]);
        });
    }

    protected $fillable = [
        'name', 'address', 'phone', 'email',
        'logo', 'website', 'affiliation_no', 'settings', 'is_active',
    ];

    protected $casts = [
        'settings'  => 'array',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany           { return $this->hasMany(User::class); }
    public function academicYears(): HasMany   { return $this->hasMany(AcademicYear::class); }
    public function classes(): HasMany         { return $this->hasMany(SchoolClass::class); }
    public function students(): HasMany        { return $this->hasMany(Student::class); }
    public function teachers(): HasMany        { return $this->hasMany(Teacher::class); }
    public function enquiries(): HasMany       { return $this->hasMany(AdmissionEnquiry::class); }
    public function announcements(): HasMany   { return $this->hasMany(Announcement::class); }
    public function broadcasts(): HasMany      { return $this->hasMany(Broadcast::class); }
    public function templates(): HasMany       { return $this->hasMany(MessageTemplate::class); }
    public function subscription(): HasOne    { return $this->hasOne(Subscription::class); }
    public function feedback(): HasMany       { return $this->hasMany(Feedback::class); }

    public function currentYear(): ?AcademicYear {
        return $this->academicYears()->where('is_current', true)->first();
    }
}
