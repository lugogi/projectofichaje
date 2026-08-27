<?php

use App\Http\Controllers\AbsenceRequestController;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\AdminClockZoneController;
use App\Http\Controllers\AdminEmployeeController;
use App\Http\Controllers\AdminManualClockController;
use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminScheduleController;
use App\Http\Controllers\AttendanceExportController;
use App\Http\Controllers\ManagerPanelController;
use App\Http\Controllers\RecentClocksController;
use App\Http\Controllers\SolicitudesReviewController;
use App\Http\Controllers\TeamAbsenceController;
use App\Http\Controllers\TeamExportController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FichajeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OvertimeRateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SolicitudesController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Fichaje del empleado: ver pantalla (GET) y registrar entrada/salida (POST)
    Route::get('/fichaje', [FichajeController::class, 'index'])->name('fichaje.index');
    Route::post('/fichaje', [FichajeController::class, 'store'])->name('fichaje.store');

    // Calendario de la empresa
    Route::get('/calendario', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/api/calendar-events', [CalendarController::class, 'events'])->name('calendar.events');
    Route::get('/api/calendar-day-events', [CalendarController::class, 'dayEvents'])->name('calendar.day-events');

    Route::get('/api/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/api/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/api/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    Route::get('/api/push/config', [PushSubscriptionController::class, 'config'])->name('push.config');
    Route::post('/api/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/api/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    Route::post('/api/push/test', [PushSubscriptionController::class, 'test'])->middleware('throttle:6,1')->name('push.test');

    Route::get('/api/recent-clocks', [RecentClocksController::class, 'index'])->name('api.recent-clocks');

    Route::post('/api/correction-requests', [SolicitudesController::class, 'storeApi'])->name('correction-requests.store');

    Route::get('/solicitudes', [AbsenceRequestController::class, 'index'])->name('solicitudes.index');
    Route::post('/solicitudes', [AbsenceRequestController::class, 'store'])->name('solicitudes.store');
    Route::get('/solicitudes/adjuntos/{storedFile}', [AbsenceRequestController::class, 'downloadAttachment'])
        ->name('solicitudes.attachment');

    // El empleado solo puede VER su perfil. El cambio de contraseña se gestiona aparte (PasswordController).
    // Los datos personales (nombre, email, etc.) los modifica el admin/manager, no el empleado.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/export/{format}', [AttendanceExportController::class, 'download'])
        ->where('format', 'excel|pdf|json')
        ->name('profile.export');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/', [AdminPanelController::class, 'index'])->name('index');
        Route::get('/exportaciones', [TeamExportController::class, 'index'])->name('exports.index');
        Route::post('/exportaciones/equipo', [TeamExportController::class, 'exportTeam'])->name('exports.team');
        Route::post('/exportaciones/equipo/laboral', [TeamExportController::class, 'sendToLaboral'])->name('exports.laboral');
        Route::get('/tarifas', [OvertimeRateController::class, 'index'])->name('overtime-rates.index');
        Route::put('/tarifas', [OvertimeRateController::class, 'update'])->name('overtime-rates.update');
        Route::get('/solicitudes', [SolicitudesReviewController::class, 'index'])->name('solicitudes.index');
        Route::patch('/solicitudes/alta/{employeeApplication}', [SolicitudesReviewController::class, 'reviewEmployeeApplication'])
            ->name('solicitudes.application.review');
        Route::patch('/solicitudes/ausencia/{absenceRequest}', [SolicitudesReviewController::class, 'reviewAbsence'])
            ->name('solicitudes.absence.review');
        Route::patch('/solicitudes/correccion/{correctionRequest}', [SolicitudesReviewController::class, 'reviewCorrection'])
            ->name('solicitudes.correction.review');

        Route::get('/empleados', [AdminEmployeeController::class, 'index'])->name('employees.index');
        Route::get('/empleados/crear', [AdminEmployeeController::class, 'create'])->name('employees.create');
        Route::post('/empleados', [AdminEmployeeController::class, 'store'])->name('employees.store');
        Route::get('/empleados/{employee}/editar', [AdminEmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/empleados/{employee}', [AdminEmployeeController::class, 'update'])->name('employees.update');
        Route::delete('/empleados/{employee}', [AdminEmployeeController::class, 'destroy'])->name('employees.destroy');

        Route::get('/fichada-manual', [AdminManualClockController::class, 'index'])->name('manual-clock.index');
        Route::post('/fichada-manual', [AdminManualClockController::class, 'store'])->name('manual-clock.store');

        Route::get('/horarios', [AdminScheduleController::class, 'index'])->name('schedules.index');
        Route::put('/horarios/{employee}', [AdminScheduleController::class, 'update'])->name('schedules.update');

        Route::get('/salas', [AdminClockZoneController::class, 'index'])->name('zones.index');
        Route::post('/salas', [AdminClockZoneController::class, 'store'])->name('zones.store');
        Route::put('/salas/{clockZone}', [AdminClockZoneController::class, 'update'])->name('zones.update');
        Route::delete('/salas/{clockZone}', [AdminClockZoneController::class, 'destroy'])->name('zones.destroy');

        Route::get('/informes', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('/auditoria', [AdminAuditLogController::class, 'index'])->name('audit.index');

        Route::get('/ausencias', [TeamAbsenceController::class, 'index'])->name('absences.index');
        Route::post('/ausencias', [TeamAbsenceController::class, 'store'])->name('absences.store');
        Route::delete('/ausencias/{absenceRequest}', [TeamAbsenceController::class, 'destroy'])->name('absences.destroy');
    });

    Route::prefix('encargado')->name('manager.')->middleware('manager')->group(function () {
        Route::get('/', [ManagerPanelController::class, 'index'])->name('index');
        Route::get('/exportaciones', [TeamExportController::class, 'index'])->name('exports.index');
        Route::post('/exportaciones/equipo', [TeamExportController::class, 'exportTeam'])->name('exports.team');
        Route::post('/exportaciones/equipo/laboral', [TeamExportController::class, 'sendToLaboral'])->name('exports.laboral');
        Route::get('/tarifas', [OvertimeRateController::class, 'index'])->name('overtime-rates.index');
        Route::put('/tarifas', [OvertimeRateController::class, 'update'])->name('overtime-rates.update');
        Route::get('/solicitudes', [SolicitudesReviewController::class, 'index'])->name('solicitudes.index');
        Route::patch('/solicitudes/alta/{employeeApplication}', [SolicitudesReviewController::class, 'reviewEmployeeApplication'])
            ->name('solicitudes.application.review');
        Route::patch('/solicitudes/ausencia/{absenceRequest}', [SolicitudesReviewController::class, 'reviewAbsence'])
            ->name('solicitudes.absence.review');
        Route::patch('/solicitudes/correccion/{correctionRequest}', [SolicitudesReviewController::class, 'reviewCorrection'])
            ->name('solicitudes.correction.review');

        Route::get('/ausencias', [TeamAbsenceController::class, 'index'])->name('absences.index');
        Route::post('/ausencias', [TeamAbsenceController::class, 'store'])->name('absences.store');
        Route::delete('/ausencias/{absenceRequest}', [TeamAbsenceController::class, 'destroy'])->name('absences.destroy');
    });

    Route::get('/equipo/exportaciones', function (\Illuminate\Http\Request $request) {
        if ($request->user()->isAdmin()) {
            return redirect()->route('admin.exports.index');
        }
        if ($request->user()->isManager()) {
            return redirect()->route('manager.exports.index');
        }

        abort(403);
    })->name('team.exports.index');
});

require __DIR__.'/auth.php';


