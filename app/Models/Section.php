<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Section extends Model {
    use SoftDeletes;
    protected $fillable = ['school_id','class_id','name','capacity'];
    public function schoolClass() { return $this->belongsTo(SchoolClass::class,'class_id'); }
    public function students()    { return $this->hasMany(Student::class); }
}
