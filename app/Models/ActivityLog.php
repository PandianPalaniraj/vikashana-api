<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'school_id', 'action', 'module',
        'description', 'icon', 'ip_address', 'user_agent', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Static helper — call from any controller to record an action.
     */
    public static function log(
        int    $userId,
        ?int   $schoolId,
        string $action,
        string $module,
        string $description,
        string $icon = '📝',
        ?array $meta = null
    ): void {
        static::create([
            'user_id'     => $userId,
            'school_id'   => $schoolId,
            'action'      => $action,
            'module'      => $module,
            'description' => $description,
            'icon'        => $icon,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
            'meta'        => $meta,
        ]);
    }
}
