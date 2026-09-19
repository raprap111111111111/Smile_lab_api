<?php

declare(strict_types=1);

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Unauthenticated liveness probe for clients: can the API reach its database?
 * The login screen uses it to show a real connection status.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            return $this->errorResponse('Service unavailable.', JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->successResponse(['status' => 'ok'], 'API is reachable.');
    }
}
