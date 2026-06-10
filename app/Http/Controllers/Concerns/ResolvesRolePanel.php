<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ResolvesRolePanel
{
    /**
     * @return array{panel: string, home_route: string, exports_route: string, solicitudes_route: string, review_absence_route: string, review_correction_route: string}
     */
    protected function resolvePanel(Request $request): array
    {
        $routeName = (string) $request->route()->getName();
        $user = $request->user();

        if (str_starts_with($routeName, 'admin.')) {
            abort_unless($user->isAdmin(), 403);

            return [
                'panel' => 'admin',
                'home_route' => 'admin.index',
                'exports_route' => 'admin.exports.index',
                'solicitudes_route' => 'admin.solicitudes.index',
                'review_absence_route' => 'admin.solicitudes.absence.review',
                'review_correction_route' => 'admin.solicitudes.correction.review',
            ];
        }

        if (str_starts_with($routeName, 'manager.')) {
            abort_unless($user->isManager(), 403);

            return [
                'panel' => 'manager',
                'home_route' => 'manager.index',
                'exports_route' => 'manager.exports.index',
                'solicitudes_route' => 'manager.solicitudes.index',
                'review_absence_route' => 'manager.solicitudes.absence.review',
                'review_correction_route' => 'manager.solicitudes.correction.review',
            ];
        }

        abort(403);
    }
}
