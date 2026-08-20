<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Rules\LocationCode;
use App\Services\LocationLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live preview for the Saudi National Address location-code field. This is
 * preview-only, used by public/js/location-lookup.js to show a resolved
 * address before the shopper submits — the checkout/account controllers that
 * actually persist an address always call LocationLookupService themselves
 * server-side and never trust a client-supplied result.
 */
class LocationLookupController extends Controller
{
    public function lookup(Request $request, LocationLookupService $service): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', new LocationCode()],
        ]);

        return response()->json($service->lookup($data['code']));
    }
}
