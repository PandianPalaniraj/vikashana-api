<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAnnouncement extends Model
{
    protected $fillable = [
        'title', 'body', 'target', 'target_value',
        'scheduled_at', 'sent_at', 'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
