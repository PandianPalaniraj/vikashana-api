<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeePayment extends Model {
    use SoftDeletes;
    protected $fillable = ['invoice_id','amount','method','reference','paid_at','received_by','remarks'];
    protected $casts    = ['paid_at'=>'datetime','amount'=>'decimal:2'];
    public function invoice()    { return $this->belongsTo(FeeInvoice::class,'invoice_id'); }
    public function receivedBy() { return $this->belongsTo(User::class,'received_by'); }
}
