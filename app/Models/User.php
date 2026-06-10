<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'employees';

    // PKs son ULIDs (char 26), no auto-increment
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'role',
        'employee_code',
        'employment_status',
        'hire_date',
        'company_id',
        'work_calendar_id',
    ];

    public const ROLE_ADMIN    = 'admin';
    public const ROLE_MANAGER  = 'manager';
    public const ROLE_EMPLOYEE = 'employee';

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected $casts = [
        'hire_date'        => 'date',
        'termination_date' => 'date',
        'deleted_at'       => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
        });
    }

    // Laravel Auth usa este método para saber dónde está el hash de la contraseña
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    // ─── Helpers de rol ───────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isEmployee(): bool
    {
        return $this->role === self::ROLE_EMPLOYEE;
    }

    // ─── Relaciones ───────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function workCalendar(): BelongsTo
    {
        return $this->belongsTo(WorkCalendar::class, 'work_calendar_id');
    }

    public function timeRecords(): HasMany
    {
        return $this->hasMany(TimeRecord::class, 'employee_id');
    }

    public function workSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class, 'employee_id');
    }
}
