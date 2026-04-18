<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use SoftDeletes;
    protected static function booted(): void
    {
        static::creating(function (School $school) {
            if (!$school->school_code) {
                $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $school->name), 0, 3));
                if (strlen($prefix) < 2) $prefix = 'SCH';
                do {
                    $code = $prefix . rand(1000, 9999);
                } while (static::where('school_code', $code)->exists());
                $school->school_code = $code;
            }
        });

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
        'name', 'school_code', 'address', 'phone', 'email',
        'logo', 'website', 'affiliation_no', 'settings', 'is_active',
        'admin_plain_password',
    ];

    protected $casts = [
        'settings'  => 'array',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany              { return $this->hasMany(User::class); }
    public function academicYears(): HasMany      { return $this->hasMany(AcademicYear::class); }
    public function classes(): HasMany            { return $this->hasMany(SchoolClass::class); }
    public function sections(): HasMany           { return $this->hasMany(Section::class); }
    public function subjects(): HasMany           { return $this->hasMany(Subject::class); }
    public function students(): HasMany           { return $this->hasMany(Student::class); }
    public function teachers(): HasMany           { return $this->hasMany(Teacher::class); }
    public function timetables(): HasMany         { return $this->hasMany(Timetable::class); }
    public function attendance(): HasMany         { return $this->hasMany(Attendance::class); }
    public function teacherAttendance(): HasMany  { return $this->hasMany(TeacherAttendance::class); }
    public function homework(): HasMany           { return $this->hasMany(Homework::class); }
    public function exams(): HasMany              { return $this->hasMany(Exam::class); }
    public function feeInvoices(): HasMany        { return $this->hasMany(FeeInvoice::class); }
    public function feeStructures(): HasMany      { return $this->hasMany(FeeStructure::class); }
    public function admissionEnquiries(): HasMany { return $this->hasMany(AdmissionEnquiry::class); }
    public function announcements(): HasMany      { return $this->hasMany(Announcement::class); }
    public function broadcasts(): HasMany         { return $this->hasMany(Broadcast::class); }
    public function messageTemplates(): HasMany   { return $this->hasMany(MessageTemplate::class); }
    public function pushTokens(): HasMany         { return $this->hasMany(PushToken::class); }
    public function activityLogs(): HasMany       { return $this->hasMany(ActivityLog::class); }
    public function feedback(): HasMany           { return $this->hasMany(Feedback::class); }
    public function quizQuestions(): HasMany      { return $this->hasMany(QuizQuestion::class); }
    public function subscription(): HasOne        { return $this->hasOne(Subscription::class); }
    public function subscriptions(): HasMany      { return $this->hasMany(Subscription::class); }

    public function currentYear(): ?AcademicYear {
        return $this->academicYears()->where('is_current', true)->first();
    }
}
