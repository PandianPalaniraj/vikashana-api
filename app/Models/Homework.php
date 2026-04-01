<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Homework extends Model {
    use SoftDeletes;
    protected $table    = 'homework';
    protected $fillable = ['school_id','subject_id','class_id','section_id','teacher_id',
                           'academic_year_id','title','description','assigned_date','due_date',
                           'attachments','status'];
    protected $casts    = ['attachments'=>'array','assigned_date'=>'date','due_date'=>'date'];

    public function subject() { return $this->belongsTo(Subject::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class,'class_id'); }
    public function section() { return $this->belongsTo(Section::class); }
}
