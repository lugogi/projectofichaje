<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HolidayTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $calendarId = DB::table('work_calendars')->value('id');

        if (!$calendarId) {
            throw new Exception('No existe ningún work_calendar');
        }

        $holidays = [
            [
                'id' => (string) Str::ulid(),
                'name' => 'Dia Festivo de Prueba 1',
                'date' => '2026-06-05',
                'type' => 'national',
                'mandatory' => true,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::ulid(),
                'name' => 'Dia Festivo de Prueba 2',
                'date' => '2026-06-15',
                'type' => 'regional',
                'mandatory' => false,
                'created_at' => now(),
            ],
        ];

        foreach ($holidays as $holiday) {
            DB::table('holidays')->insert([
                'id' => $holiday['id'],
                'work_calendar_id' => $calendarId,
                'name' => $holiday['name'],
                'date' => $holiday['date'],
                'type' => $holiday['type'],
                'mandatory' => $holiday['mandatory'],
                'created_at' => $holiday['created_at'],
            ]);
        }
    }
}
