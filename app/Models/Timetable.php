<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timetable extends Model
{
    protected $fillable = [
        'school_id', 'class_id', 'section_id', 'academic_year_id',
        'day', 'period', 'subject_id', 'teacher_id', 'start_time', 'end_time',
    ];

    public function subject() { return $this->belongsTo(Subject::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function section() { return $this->belongsTo(Section::class); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
}
