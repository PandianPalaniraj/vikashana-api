<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Admission extends Model {
    protected $fillable = ['school_id','enquiry_id','student_id','academic_year_id',
                           'admission_no','class_id','section_id','admitted_at'];
    protected $casts    = ['admitted_at'=>'datetime'];
    public function student() { return $this->belongsTo(Student::class); }
    public function enquiry() { return $this->belongsTo(AdmissionEnquiry::class,'enquiry_id'); }
}
