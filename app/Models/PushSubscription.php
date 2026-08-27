<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PushSubscription extends Model
{
    protected $table = 'push_subscriptions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
        'last_used_at',
        'failure_count',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'failure_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }

            if (empty($model->endpoint_hash) && ! empty($model->endpoint)) {
                $model->endpoint_hash = hash('sha256', $model->endpoint);
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'user_id');
    }
}
