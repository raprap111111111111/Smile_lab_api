<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreserveFloatInJson
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $content = $response->getContent();
            // Re-encode with JSON_PRESERVE_ZERO_FRACTION to ensure floats like 0.0 are preserved
            $data = json_decode($content, true);
            $content = json_encode($data, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES);
            $response->setContent($content);
        }

        return $response;
    }
}
