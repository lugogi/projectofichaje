<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AbsenceRequest extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const TYPE_VACATION = 'vacation';
    public const TYPE_MEDICAL_LEAVE = 'medical_leave';
    public const TYPE_FREE_DAY = 'free_day';

    protected $table = 'absence_requests';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'request_reason',
        'status',
        'reviewed_by',
        'review_comment',
        'document_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
            if (empty($model->status)) {
                $model->status = self::STATUS_PENDING;
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'document_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_VACATION => 'Vacaciones',
            self::TYPE_MEDICAL_LEAVE => 'Baja médica',
            self::TYPE_FREE_DAY => 'Día libre',
            default => $this->type,
        };
    }
}