<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Api\Exceptions\ApiNotConfigured;
use App\Api\Exceptions\ApiRejected;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\TillNotAuthorised;
use App\Api\VendaApi;
use App\Till\Till;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The window's Echo client asks here to join the till's private channel. The
 * till signs it with the server using its device token, which never reaches
 * the window. Only this till's own channel is ever asked for.
 */
final class AuthoriseRealtimeChannel
{
    public function __invoke(Request $request, Till $till, VendaApi $api): JsonResponse
    {
        $input = $request->validate([
            'socket_id' => ['required', 'string', 'max:64', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string', 'max:128'],
        ]);

        $identity = $till->isPaired() ? $till->identity() : null;

        if ($identity === null || ! hash_equals("private-till.{$identity->device_id}", $input['channel_name'])) {
            return response()->json(['message' => 'Not this till’s channel.'], Response::HTTP_FORBIDDEN);
        }

        try {
            return response()->json($api->authoriseChannel($input['socket_id'], $input['channel_name']));
        } catch (TillNotAuthorised|ApiRejected) {
            return response()->json(['message' => 'Refused.'], Response::HTTP_FORBIDDEN);
        } catch (ApiUnavailable|ApiNotConfigured) {
            // Echo retries on its own; the 60-second sync covers the meantime.
            return response()->json(['message' => 'Server unavailable.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
