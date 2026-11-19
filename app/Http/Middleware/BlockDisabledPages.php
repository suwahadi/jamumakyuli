<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AdminMenu;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockDisabledPages
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(AdminMenu::isDisabled((string) $request->route()?->getName()), 404);

        return $next($request);
    }
}
