<?php

namespace Modules\Attendance\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Attendance\Services\DeviceService;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateDevice
{
    public function __construct(private readonly DeviceService $devices) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');
        if (! preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $device = $this->devices->findByToken($matches[1]);
        if ($device === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (RateLimiter::tooManyAttempts('device-ingest:'.$device->id, 120)) {
            return response()->json(['message' => 'Too Many Attempts.'], 429);
        }
        RateLimiter::hit('device-ingest:'.$device->id, 60);

        $request->attributes->set('device', $device);
        $request->setUserResolver(fn () => null);

        return $next($request);
    }
}
