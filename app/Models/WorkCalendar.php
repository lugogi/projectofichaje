<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkCalendar extends Model
{
    use SoftDeletes;

    protected $table = 'work_calendars';
    public $incrementing = false;
    protected $keyType = 'string';
    const UPDATED_AT = null; // la tabla solo tiene created_at

    protected $fillable = ['name', 'timezone', 'company_id'];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->id ??= (string) Str::ulid());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'work_calendar_id');
    }
}
