<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    protected $table    = 'classes';
    protected $fillable = ['school_id','name','display_order'];

    public function school()    { return $this->belongsTo(School::class); }
    public function sections()  { return $this->hasMany(Section::class, 'class_id'); }
    public function subjects()  { return $this->hasMany(Subject::class, 'class_id'); }
    public function students()  { return $this->hasMany(Student::class, 'class_id'); }
}
