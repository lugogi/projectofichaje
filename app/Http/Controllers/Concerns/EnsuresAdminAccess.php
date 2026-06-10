<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait EnsuresAdminAccess
{
    protected function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}
