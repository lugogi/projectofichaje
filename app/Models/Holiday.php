<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Holiday extends Model
{
    protected $table = 'holidays';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // table only has created_at

    protected $fillable = ['id', 'work_calendar_id', 'name', 'date', 'type', 'mandatory'];

    protected $casts = [
        'date' => 'date',
        'mandatory' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
    	static::creating(fn ($m) => $m->id ??= (string) Str::ulid());
    }

    public function workCalendar(): BelongsTo
    {
        return $this->belongsTo(WorkCalendar::class, 'work_calendar_id');
    }
}
