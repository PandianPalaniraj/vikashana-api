<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'school_id', 'name', 'email', 'phone',
        'password', 'role', 'avatar', 'status', 'last_login', 'settings',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password'   => 'hashed',
        'last_login' => 'datetime',
        'settings'   => 'array',
    ];

    // Roles
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_ADMIN       = 'admin';
    const ROLE_TEACHER     = 'teacher';
    const ROLE_STAFF       = 'staff';
    const ROLE_PARENT      = 'parent';
    const ROLE_STUDENT     = 'student';

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function teacher()           { return $this->hasOne(Teacher::class); }
    public function student()           { return $this->hasOne(Student::class); }

    public function isAdmin(): bool   { return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]); }
    public function isTeacher(): bool { return $this->role === self::ROLE_TEACHER; }
    public function isParent(): bool  { return $this->role === self::ROLE_PARENT; }
}
