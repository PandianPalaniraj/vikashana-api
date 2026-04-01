<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicYear extends Model
{
    use SoftDeletes;
    protected $fillable = ['school_id','name','start_date','end_date','is_current'];
    protected $casts    = ['is_current'=>'boolean','start_date'=>'date','end_date'=>'date'];

    public function school()   { return $this->belongsTo(School::class); }
    public function students() { return $this->hasMany(Student::class); }
    public function exams()    { return $this->hasMany(Exam::class); }
}
