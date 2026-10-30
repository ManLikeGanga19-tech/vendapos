<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Requests;

final class HoldTicketRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            // The sale the till will record when the ticket is paid.
            'sale_uuid' => ['required', 'string', 'uuid'],
        ];
    }
}
