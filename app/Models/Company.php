<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Company extends Model
{
    use SoftDeletes;

    protected $table = 'companies';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['name', 'address', 'province', 'active'];

    protected $casts = [
        'active'     => 'boolean',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->id ??= (string) Str::ulid());
    }

    public function clockZones(): HasMany
    {
        return $this->hasMany(ClockZone::class, 'company_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'company_id');
    }

    public function workCalendars(): HasMany
    {
        return $this->hasMany(WorkCalendar::class, 'company_id');
    }
}
