<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model {
    protected $fillable = ['school_id','audience_type','audience_label','message','reach','status','sent_by','sent_at'];
    protected $casts    = ['sent_at'=>'datetime'];
    public function sentBy() { return $this->belongsTo(User::class,'sent_by'); }
}
