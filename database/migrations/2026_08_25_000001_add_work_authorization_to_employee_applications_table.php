<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_applications', function (Blueprint $table) {
            // Quien no tenga NAF de titular debe acreditar su habilitación para trabajar.
            $table->boolean('has_social_security')->default(true)->after('document_ocr_verified');
            $table->string('work_permit_type', 30)->nullable()->after('social_security_number');
            $table->string('work_permit_number', 30)->nullable()->after('work_permit_type');
            $table->date('work_permit_expiry')->nullable()->after('work_permit_number');
            $table->string('passport_number', 30)->nullable()->after('work_permit_expiry');
            $table->date('passport_expiry')->nullable()->after('passport_number');

            // Deja constancia de qué versión del texto de consentimiento se aceptó.
            $table->string('gdpr_version', 20)->nullable()->after('gdpr_accepted_at');
        });

        // El NAF pasa a ser opcional: puede sustituirse por el permiso de trabajo.
        Schema::table('employee_applications', function (Blueprint $table) {
            $table->string('social_security_number', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employee_applications', function (Blueprint $table) {
            $table->dropColumn([
                'has_social_security',
                'work_permit_type',
                'work_permit_number',
                'work_permit_expiry',
                'passport_number',
                'passport_expiry',
                'gdpr_version',
            ]);
        });
    }
};
