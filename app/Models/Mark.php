<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Mark extends Model {
    protected $fillable = ['exam_id','exam_subject_id','student_id',
                           'marks_obtained','grade','result','remarks','entered_by'];
    protected $casts    = ['marks_obtained'=>'decimal:2'];
    public function student()     { return $this->belongsTo(Student::class); }
    public function examSubject() { return $this->belongsTo(ExamSubject::class,'exam_subject_id'); }
    public function exam()        { return $this->belongsTo(Exam::class); }
}
