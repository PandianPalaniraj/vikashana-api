<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuizQuestion extends Model {
    use SoftDeletes;
    protected $table    = 'quiz_questions';
    protected $fillable = ['school_id','class_id','subject_id','question',
                           'option_a','option_b','option_c','option_d',
                           'correct_answer','explanation','difficulty','status','created_by'];

    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function subject()     { return $this->belongsTo(Subject::class); }
    public function creator()     { return $this->belongsTo(User::class, 'created_by'); }
}
