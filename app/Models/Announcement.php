<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model {
    protected $fillable = ['school_id','title','body','audience','class_id','section_id','is_pinned','created_by'];
    protected $casts    = ['is_pinned'=>'boolean'];
    public function createdBy()   { return $this->belongsTo(User::class,'created_by'); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class,'class_id'); }
    public function section()     { return $this->belongsTo(Section::class); }
}
