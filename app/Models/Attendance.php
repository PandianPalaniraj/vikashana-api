<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model {
    use SoftDeletes;
    protected $table    = 'attendance';
    protected $fillable = ['school_id','student_id','class_id','section_id',
                           'academic_year_id','date','status','note','marked_by'];
    protected $casts    = ['date'=>'date'];
    public function student() { return $this->belongsTo(Student::class); }
    public function markedBy(){ return $this->belongsTo(User::class,'marked_by'); }
}
