<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Requests;

final class ChangeStaffPinRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'current_pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
            // The exact length depends on the person's role; ChangeOwnPin checks it.
            'new_pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
        ];
    }
}
