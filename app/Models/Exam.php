<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model {
    protected $fillable = ['school_id','academic_year_id','class_id','name','type','start_date','end_date','status'];
    protected $casts    = ['start_date'=>'date','end_date'=>'date'];
    public function subjects()     { return $this->hasMany(ExamSubject::class); }
    public function schoolClass()  { return $this->belongsTo(SchoolClass::class,'class_id'); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function marks()        { return $this->hasMany(Mark::class); }
}
