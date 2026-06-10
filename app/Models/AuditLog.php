<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    protected $table = 'audit_log';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'changes',
        'reason',
        'request_id',
        'origin',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->id ??= (string) Str::ulid();
            $model->created_at ??= now();
        });

        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'actor_id');
    }
}
