<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TeacherAttendance extends Model {
    protected $table    = 'teacher_attendance';
    protected $fillable = ['school_id','teacher_id','date','status','note','marked_by'];
    protected $casts    = ['date'=>'date'];
    public function teacher()  { return $this->belongsTo(Teacher::class); }
    public function markedBy() { return $this->belongsTo(User::class,'marked_by'); }
}
