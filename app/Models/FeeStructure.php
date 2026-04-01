<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeStructure extends Model {
    use SoftDeletes;
    protected $fillable = ['school_id','academic_year_id','class_id','name','items','total','due_date'];
    protected $casts    = ['items'=>'array','due_date'=>'date','total'=>'decimal:2'];
    public function schoolClass() { return $this->belongsTo(SchoolClass::class,'class_id'); }
}
