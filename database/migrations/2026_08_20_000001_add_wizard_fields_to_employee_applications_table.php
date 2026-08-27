<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_applications', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('candidate_surname');
            $table->string('nationality', 80)->nullable()->after('birth_date');
            $table->string('marital_status', 30)->nullable()->after('nationality');
            $table->unsignedTinyInteger('dependents_count')->default(0)->after('marital_status');
            $table->boolean('disability_recognized')->default(false)->after('dependents_count');
            $table->string('street')->nullable()->after('address');
            $table->string('postal_code', 10)->nullable()->after('street');
            $table->string('city')->nullable()->after('postal_code');
            $table->string('province')->nullable()->after('city');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->date('document_expiry_date')->nullable()->after('document_number');
            $table->boolean('document_ocr_verified')->default(false)->after('document_expiry_date');
            $table->string('position')->nullable()->after('social_security_number');
            $table->string('department')->nullable()->after('position');
            $table->date('start_date')->nullable()->after('department');
            $table->string('contract_type', 30)->nullable()->after('start_date');
            $table->string('work_schedule', 30)->nullable()->after('contract_type');
            $table->string('iban', 34)->nullable()->after('work_schedule');
            $table->string('bank_name')->nullable()->after('iban');
            $table->json('irpf_data')->nullable()->after('bank_name');
            $table->timestamp('gdpr_accepted_at')->nullable()->after('notes');
            $table->string('signature_path')->nullable()->after('gdpr_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('employee_applications', function (Blueprint $table) {
            $table->dropColumn([
                'birth_date',
                'nationality',
                'marital_status',
                'dependents_count',
                'disability_recognized',
                'street',
                'postal_code',
                'city',
                'province',
                'phone_verified_at',
                'document_expiry_date',
                'document_ocr_verified',
                'position',
                'department',
                'start_date',
                'contract_type',
                'work_schedule',
                'iban',
                'bank_name',
                'irpf_data',
                'gdpr_accepted_at',
                'signature_path',
            ]);
        });
    }
};
