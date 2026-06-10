<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CorrectionRequest extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'correction_requests';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'time_record_id',
        'requested_by',
        'new_datetime',
        'reason',
        'status',
        'reviewed_by',
        'applied_at',
        'corrected_record_id',
        'review_note',
    ];

    protected $casts = [
        'new_datetime' => 'datetime',
        'applied_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
        });
    }

    public function timeRecord(): BelongsTo
    {
        return $this->belongsTo(TimeRecord::class, 'time_record_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by');
    }
}
