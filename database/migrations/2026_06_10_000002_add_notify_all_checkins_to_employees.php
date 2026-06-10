<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'notify_all_checkins')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->boolean('notify_all_checkins')->default(false)->after('role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'notify_all_checkins')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('notify_all_checkins');
            });
        }
    }
};
