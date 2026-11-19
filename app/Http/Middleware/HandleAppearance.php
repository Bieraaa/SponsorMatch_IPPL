<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Desain SponsorMatch adalah tema terang. Cookie `appearance` tetap
        // dipakai bila pengguna memilih tema lain di halaman Pengaturan,
        // tetapi default-nya terang agar dropdown, dialog, dan halaman tidak
        // bercampur terang/gelap di perangkat bermode gelap.
        View::share('appearance', $request->cookie('appearance') ?? 'light');

        return $next($request);
    }
}
