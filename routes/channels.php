<?php

use App\Models\Employee;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['web', 'auth']]);

Broadcast::channel('App.Models.Employee.{id}', function (Employee $employee, string $id) {
    return $employee->id === $id;
});

Broadcast::channel('fichaje.staff', function (Employee $employee) {
    return $employee->isAdmin() || $employee->isManager();
});
