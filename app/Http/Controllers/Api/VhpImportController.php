<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Import;
use App\Models\Property;
use App\Services\WeeklyImportPipeline;
use App\Services\WeeklyReportImporter;
use App\Services\WeeklyReportParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Secure import endpoint for the VHP robot (docs/PLAN.md §4 & §8).
 *
 * The robot downloads a weekly-report workbook from VHP and POSTs it here,
 * signing the request with a shared secret so no login/session is needed.
 * The file is then processed exactly like a manual upload.
 *
 * Auth: HMAC-SHA256 over "{timestamp}\n{property}\n{sha256(file)}" using
 * VHP_IMPORT_SECRET, sent in the X-Vhp-Signature header with X-Vhp-Timestamp.
 * The timestamp must be recent (replay protection).
 */
class VhpImportController extends Controller
{
    public function __invoke(Request $request, WeeklyReportParser $parser, WeeklyReportImporter $importer, WeeklyImportPipeline $pipeline): JsonResponse
    {
        $secret = (string) config('services.vhp.import_secret');
        if ($secret === '') {
            return response()->json(['message' => 'VHP import is not configured on the server.'], 503);
        }

        $validated = $request->validate([
            'report' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
            'property' => ['required', 'string', 'max:20'],
            'start_date' => ['nullable', 'date'],
        ]);

        $file = $request->file('report');
        $timestamp = (string) $request->header('X-Vhp-Timestamp', '');
        $signature = (string) $request->header('X-Vhp-Signature', '');

        // --- Authenticate the request ---------------------------------------
        if (! $this->timestampFresh($timestamp)) {
            return response()->json(['message' => 'Stale or invalid timestamp.'], 401);
        }

        $contents = (string) file_get_contents($file->getRealPath());
        $canonical = $timestamp."\n".$validated['property']."\n".hash('sha256', $contents);
        $expected = hash_hmac('sha256', $canonical, $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        // --- Resolve the property -------------------------------------------
        $property = Property::where('code', $validated['property'])->first();
        if (! $property) {
            return response()->json(['message' => "Unknown property code [{$validated['property']}]."], 422);
        }

        // --- Parse the workbook ---------------------------------------------
        try {
            $parsed = $parser->parse($file->getRealPath(), $file->getClientOriginalName());
        } catch (\Throwable $e) {
            Import::create([
                'property_id' => $property->id,
                'source' => 'vhp',
                'original_filename' => $file->getClientOriginalName(),
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Could not read the workbook: '.$e->getMessage()], 422);
        }

        $startDate = $validated['start_date'] ?? ($parsed['meta']['start_date'] ?? null);
        if (! $startDate) {
            return response()->json(['message' => 'Could not determine the report week (no start_date in the request or the file name).'], 422);
        }

        // --- Find/create the week and apply ---------------------------------
        $resolved = $pipeline->findOrCreateWeek($property->id, $startDate);
        $week = $resolved['week'];
        if ($resolved['locked']) {
            return response()->json(['message' => 'That week is locked (approved/exported) and cannot be overwritten.'], 409);
        }

        $stored = $file->store('imports');
        $summary = $importer->applyToWeek($parsed, $week);

        Import::create([
            'property_id' => $property->id,
            'report_week_id' => $week->id,
            'source' => 'vhp',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $stored,
            'status' => 'applied',
            'period_label' => $parsed['meta']['period_label'] ?? $week->label,
            'summary' => $summary,
            'warnings' => $parsed['warnings'] ?? [],
            'applied_at' => now(),
        ]);

        ActivityLog::record('imported', $week, 'VHP robot import: '.$file->getClientOriginalName(), ['source' => 'vhp']);

        return response()->json([
            'status' => 'applied',
            'property' => $property->code,
            'week_id' => $week->id,
            'label' => $week->label,
            'created_week' => $resolved['created'],
            'summary' => $summary,
            'warnings' => $parsed['warnings'] ?? [],
        ]);
    }

    /** The signed timestamp (unix seconds) must be within the tolerance window. */
    private function timestampFresh(string $timestamp): bool
    {
        if ($timestamp === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        $tolerance = (int) config('services.vhp.timestamp_tolerance', 300);

        return abs(now()->getTimestamp() - (int) $timestamp) <= $tolerance;
    }
}
