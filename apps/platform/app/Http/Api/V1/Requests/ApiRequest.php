<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Base for every terminal/mobile API request: authorisation is done by the
 * device middleware, and any top-level key the rules don't name is rejected.
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $allowed = array_map(
                    fn (string $rule) => explode('.', $rule)[0],
                    array_keys($this->rules()),
                );

                foreach (array_diff(array_keys($this->all()), $allowed) as $unknown) {
                    $validator->errors()->add((string) $unknown, 'Unknown field.');
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    abstract public function rules(): array;
}
