<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApplyActiveRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            $originalRole = (int) ($user->funcao_original ?? $user->funcao ?? 0);
            $activeRole = $request->session()->get('active_role');
            $activeRole = is_numeric($activeRole) ? (int) $activeRole : $originalRole;

            if ($activeRole < $originalRole || $activeRole > 6) {
                $activeRole = $originalRole;
                $request->session()->put('active_role', $activeRole);
            }

            $user->setAttribute('funcao', $activeRole);
        }

        return $next($request);
    }
}
