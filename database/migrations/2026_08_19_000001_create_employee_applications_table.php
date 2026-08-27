<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('employee_applications')) {
            return;
        }

        Schema::create('employee_applications', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('candidate_name');
            $table->string('candidate_surname');
            $table->string('address');
            $table->string('phone');
            $table->string('email');
            $table->string('document_type');
            $table->string('document_number');
            $table->string('social_security_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->char('reviewed_by', 26)->nullable();
            $table->text('review_comment')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_applications');
    }
};
