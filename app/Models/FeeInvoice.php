<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeInvoice extends Model {
    use SoftDeletes;
    protected $fillable = ['school_id','student_id','academic_year_id','invoice_no',
                           'month','items','total','paid','discount','status',
                           'due_date','notes','wa_sent','receipt_sent'];
    protected $casts    = ['items'=>'array','due_date'=>'date',
                           'total'=>'decimal:2','paid'=>'decimal:2','discount'=>'decimal:2',
                           'wa_sent'=>'boolean','receipt_sent'=>'boolean'];

    public function student()  { return $this->belongsTo(Student::class); }
    public function payments() { return $this->hasMany(FeePayment::class,'invoice_id'); }

    public function getBalanceAttribute(): float {
        return (float)$this->total - (float)$this->paid - (float)$this->discount;
    }
}
