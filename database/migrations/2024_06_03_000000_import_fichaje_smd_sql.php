<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sqlPath = database_path('fichaje_smd.sql');

        if (!file_exists($sqlPath)) {
            throw new \Exception("SQL file not found at: {$sqlPath}");
        }

        $sql = file_get_contents($sqlPath);

        if (empty($sql)) {
            throw new \Exception("SQL file is empty.");
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::unprepared('SET FOREIGN_KEY_CHECKS = 0;');
        } elseif ($driver === 'sqlite') {
            DB::unprepared('PRAGMA foreign_keys = OFF;');
        }

        try {
            DB::unprepared($sql);
        } finally {
            if ($driver === 'mysql') {
                DB::unprepared('SET FOREIGN_KEY_CHECKS = 1;');
            } elseif ($driver === 'sqlite') {
                DB::unprepared('PRAGMA foreign_keys = ON;');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Since this migration imports a full schema, 
        // 'down' should ideally drop all tables defined in the SQL.
    }
};
