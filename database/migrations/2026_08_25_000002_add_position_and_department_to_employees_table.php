<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('position', 120)->nullable()->after('role');
            $table->string('department', 120)->nullable()->after('position');

            $table->index('department');
            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['department']);
            $table->dropIndex(['position']);
            $table->dropColumn(['position', 'department']);
        });
    }
};
