<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Services\BookingLifecycle;
class SyncBookingLifecycle
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET')) app(BookingLifecycle::class)->completePaid();
        return $next($request);
    }
}
