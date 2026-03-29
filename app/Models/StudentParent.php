<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class StudentParent extends Model {
    protected $fillable = ['student_id','name','relation','phone','email',
                           'occupation','annual_income','is_primary','user_id'];
    protected $casts    = ['is_primary'=>'boolean'];
    public function student() { return $this->belongsTo(Student::class); }
    public function user()    { return $this->belongsTo(User::class); }
}
