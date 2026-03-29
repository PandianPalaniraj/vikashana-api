<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $fillable = [
        'school_id','user_id','employee_id','name','phone','email','photo',
        'dob','gender','blood_group','designation','department','qualification',
        'joining_date','salary','address','city','state','pincode','status',
        'subjects_list','classes_list','sections_list','docs',
    ];

    protected $casts = [
        'dob'           => 'date',
        'joining_date'  => 'date',
        'salary'        => 'decimal:2',
        'subjects_list' => 'array',
        'classes_list'  => 'array',
        'sections_list' => 'array',
        'docs'          => 'array',
    ];

    public function school()     { return $this->belongsTo(School::class); }
    public function user()       { return $this->belongsTo(User::class); }
    public function subjects()   { return $this->belongsToMany(Subject::class,'teacher_subjects')
                                       ->withPivot('class_id','section_id','academic_year_id'); }
    public function homework()   { return $this->hasMany(Homework::class); }
    public function attendance() { return $this->hasMany(TeacherAttendance::class); }
}
