<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model {
    use SoftDeletes;
    protected $fillable = ['school_id','class_id','name','code','is_optional'];
    protected $casts    = ['is_optional'=>'boolean'];

    public function schoolClass()  { return $this->belongsTo(SchoolClass::class,'class_id'); }
    public function teachers()     { return $this->belongsToMany(Teacher::class,'teacher_subjects'); }
    public function examSubjects() { return $this->hasMany(ExamSubject::class); }
}
