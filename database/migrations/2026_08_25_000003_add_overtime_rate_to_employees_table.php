<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('employees', 'overtime_rate')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            // Precio en euros de cada hora extra. Lo fija el encargado.
            $table->decimal('overtime_rate', 8, 2)->nullable()->after('department');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('overtime_rate');
        });
    }
};
