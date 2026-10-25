<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rejects API bodies above the configured size (architecture §5: 64 KB) before any parsing work. */
final class LimitRequestBody
{
    public function handle(Request $request, Closure $next): Response
    {
        $limit = (int) config('vendapos.api_body_limit_kb') * 1024;

        if (strlen($request->getContent()) > $limit) {
            return response()->json(['message' => 'Request body too large.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $next($request);
    }
}
