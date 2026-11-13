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
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $serial = $request->input('serial_no');
        $punches = $request->input('punches');

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

        return response()->json($result->toArray());
    }
}
