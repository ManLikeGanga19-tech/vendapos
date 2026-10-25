<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Requests;

final class PairDeviceRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            // As typed on the till: "ABCD-EFGH", any case, spaces allowed.
            'code' => ['required', 'string', 'max:20'],
        ];
    }
}
