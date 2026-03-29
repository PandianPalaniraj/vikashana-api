<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ExamSubject extends Model {
    protected $fillable = ['exam_id','subject_id','date','start_time','duration_minutes',
                           'max_marks','pass_marks','venue'];
    protected $casts    = ['date'=>'date','max_marks'=>'decimal:2','pass_marks'=>'decimal:2'];
    public function exam()    { return $this->belongsTo(Exam::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function marks()   { return $this->hasMany(Mark::class,'exam_subject_id'); }
}
