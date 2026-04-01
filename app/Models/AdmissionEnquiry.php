<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdmissionEnquiry extends Model {
    use SoftDeletes;
    protected $fillable = ['school_id','student_name','dob','gender','apply_class',
                           'parent_name','parent_phone','parent_email','address',
                           'source','stage','notes','follow_up_date','assigned_to','enquiry_date'];
    protected $casts    = ['dob'=>'date','follow_up_date'=>'date','enquiry_date'=>'date'];
    public function assignedTo() { return $this->belongsTo(User::class,'assigned_to'); }
    public function admission()  { return $this->hasOne(Admission::class,'enquiry_id'); }
}
