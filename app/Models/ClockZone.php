<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ClockZone extends Model
{
    use SoftDeletes;

    protected $table = 'clock_zones';
    public $incrementing = false;
    protected $keyType = 'string';
    const UPDATED_AT = null; // la tabla solo tiene created_at

    protected $fillable = ['company_id', 'name', 'ip', 'type', 'active'];

    protected $casts = [
        'active'     => 'boolean',
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
}
