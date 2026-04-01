<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageTemplate extends Model {
    use SoftDeletes;
    protected $fillable = ['school_id','name','category','body','tags','created_by'];
    protected $casts    = ['tags'=>'array'];
    public function createdBy() { return $this->belongsTo(User::class,'created_by'); }
}
