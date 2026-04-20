<?php namespace App\Models;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model {
    use SoftDeletes;
    protected $fillable = ['school_id','academic_year_id','class_id','name','type','start_date','end_date','status'];
    protected $casts    = ['start_date'=>'date','end_date'=>'date'];

    /**
     * Status is auto-derived from start_date/end_date.
     * The stored `status` column is ignored in favour of this accessor so
     * upcoming/ongoing/completed is always in sync with the calendar.
     */
    public function getStatusAttribute(): string
    {
        $today = Carbon::now()->startOfDay();
        $start = $this->start_date ? Carbon::parse($this->start_date)->startOfDay() : null;
        $end   = $this->end_date   ? Carbon::parse($this->end_date)->endOfDay()     : null;

        if (!$start || !$end) return 'Upcoming';
        if ($today->lt($start))            return 'Upcoming';
        if ($today->between($start, $end)) return 'Ongoing';
        return 'Completed';
    }

    /**
     * Date-based filter: status is not a stored column for filtering purposes.
     * Pass 'Upcoming', 'Ongoing', or 'Completed'.
     */
    public function scopeWithStatus($query, string $status)
    {
        $today = Carbon::now()->toDateString();
        return match (strtolower($status)) {
            'upcoming'  => $query->whereNotNull('start_date')->where('start_date', '>', $today),
            'ongoing'   => $query->whereNotNull('start_date')->where('start_date', '<=', $today)->where('end_date', '>=', $today),
            'completed' => $query->whereNotNull('end_date')->where('end_date', '<', $today),
            default     => $query,
        };
    }

    public function subjects()     { return $this->hasMany(ExamSubject::class); }
    public function schoolClass()  { return $this->belongsTo(SchoolClass::class,'class_id'); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function marks()        { return $this->hasMany(Mark::class); }
}
