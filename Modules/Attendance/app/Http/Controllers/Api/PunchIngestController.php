<?php

namespace Modules\Attendance\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\Models\Device;
use Modules\Attendance\Services\PunchIngestService;

final class PunchIngestController
{
    public function __invoke(Request $request, PunchIngestService $ingest): JsonResponse
    {
        /** @var Device|null $device */
        $device = $request->attributes->get('device');

        $legacy = $this->legacyRequest($request);
        $serial = $this->serial($request);
        $punches = $this->punches($request);

        if (! $device instanceof Device && $legacy) {
            $device = $this->deviceFromLegacy($serial, $request->input('device_ip'));
        }

        if (! $device instanceof Device) {
            if ($legacy) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['deviceSerial' => ['No active device matches this serial number and IP.']],
                ], 422);
            }

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! is_string($serial) || $serial === '' || $serial !== $device->serial_no) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['serial_no' => ['Serial number does not match this device.']],
            ], 422);
        }

        if (! is_array($punches) || $punches === [] || count($punches) > 1000) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['punches' => ['Punches must be a non-empty array of at most 1000 items.']],
            ], 422);
        }

        $result = $ingest->ingest($device, $punches);
        $body = $result->toArray();

        if ($legacy) {
            $body = [
                'status' => true,
                'message' => 'Attendance data has been imported successfully',
                ...$body,
            ];
        }

        return response()->json($body);
    }

    private function legacyRequest(Request $request): bool
    {
        return ! is_array($request->input('punches'))
            && ($request->exists('attendance') || $request->exists('deviceSerial'));
    }

    private function deviceFromLegacy(mixed $serial, mixed $ip): ?Device
    {
        if (! is_string($serial) || $serial === '') {
            return null;
        }

        $device = Device::query()
            ->where('serial_no', $serial)
            ->where('is_active', true)
            ->first();

        if ($device === null) {
            return null;
        }

        if (is_string($device->ip_address) && $device->ip_address !== ''
            && is_string($ip) && $ip !== ''
            && $device->ip_address !== $ip) {
            return null;
        }

        return $device;
    }

    private function serial(Request $request): mixed
    {
        $serial = $request->input('serial_no');
        if (is_string($serial) && $serial !== '') {
            return $serial;
        }

        return $request->input('deviceSerial');
    }

    /**
     * New clients send punches[]. Terminals send attendance as a JSON string
     * (or an already decoded list) of id, timestamp, state and type.
     */
    private function punches(Request $request): mixed
    {
        $punches = $request->input('punches');
        if (is_array($punches)) {
            return $punches;
        }

        $attendance = $request->input('attendance');
        if (is_string($attendance)) {
            $decoded = json_decode($attendance, true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($attendance) ? $attendance : $punches;
    }
}
