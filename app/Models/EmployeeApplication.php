<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmployeeApplication extends Model
{
    use SoftDeletes;

    protected $table = 'employee_applications';

    public $incrementing = false;

    protected $keyType = 'string';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'candidate_name',
        'candidate_surname',
        'birth_date',
        'nationality',
        'marital_status',
        'dependents_count',
        'disability_recognized',
        'address',
        'street',
        'postal_code',
        'city',
        'province',
        'phone',
        'phone_verified_at',
        'email',
        'document_type',
        'document_number',
        'document_expiry_date',
        'document_ocr_verified',
        'has_social_security',
        'social_security_number',
        'work_permit_type',
        'work_permit_number',
        'work_permit_expiry',
        'passport_number',
        'passport_expiry',
        'position',
        'department',
        'start_date',
        'contract_type',
        'work_schedule',
        'iban',
        'bank_name',
        'irpf_data',
        'notes',
        'gdpr_accepted_at',
        'gdpr_version',
        'signature_path',
        'status',
        'reviewed_by',
        'review_comment',
        'reviewed_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'document_expiry_date' => 'date',
        'start_date' => 'date',
        'work_permit_expiry' => 'date',
        'passport_expiry' => 'date',
        'dependents_count' => 'integer',
        'disability_recognized' => 'boolean',
        'document_ocr_verified' => 'boolean',
        'has_social_security' => 'boolean',
        'irpf_data' => 'array',
        'phone_verified_at' => 'datetime',
        'gdpr_accepted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
        });
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StoredFile::class, 'entity_id')
            ->where('entity_type', 'employee_application');
    }
}
