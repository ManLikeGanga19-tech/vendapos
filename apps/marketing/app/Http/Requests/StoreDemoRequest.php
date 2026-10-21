<?php

namespace App\Http\Requests;

use App\Enums\ContactWindow;
use App\Enums\OutletRange;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Rules\PhoneNumber;

class StoreDemoRequest extends FormRequest
{
    /** Humans take longer than this to fill the form; bots usually don't. */
    public const int MIN_SECONDS_TO_FILL = 3;

    /** Hidden field real visitors never see or fill. */
    public const string HONEYPOT = 'website';

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
            'form_token' => ['required', 'string'],
            self::HONEYPOT => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'phone' => ['required', 'string', new PhoneNumber(__('demo.validation.phone'))],
            'business_name' => ['required', 'string', 'max:160'],
            // Rule::in (not Rule::enum) so the translated "choice" message applies.
            'business_type' => ['required', Rule::in(array_column(BusinessType::cases(), 'value'))],
            'outlets' => ['required', Rule::in(array_column(OutletRange::cases(), 'value'))],
            'contact_window' => ['nullable', Rule::in(array_column(ContactWindow::cases(), 'value'))],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => __('demo.validation.required'),
            'accepted' => __('demo.validation.consent'),
            'email' => __('demo.validation.email'),
            'max' => __('demo.validation.max'),
            'in' => __('demo.validation.choice'),
            'uuid' => __('demo.validation.expired'),
            'form_token.required' => __('demo.validation.expired'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('demo.fields');
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $unknown = array_diff(array_keys($this->except('_token')), array_keys($this->rules()));

                if ($unknown !== []) {
                    $validator->errors()->add('form', __('demo.validation.unknown'));
                }

                if ($this->filled('form_token') && $this->formIssuedAt() === null) {
                    $validator->errors()->add('form_token', __('demo.validation.expired'));
                }
            },
        ];
    }

    /**
     * Honeypot filled in, or submitted faster than a person could type.
     * Spam is dropped quietly so bots get no signal to adapt to.
     */
    public function isLikelySpam(): bool
    {
        $issuedAt = $this->formIssuedAt();

        return $this->filled(self::HONEYPOT)
            || ($issuedAt !== null && now()->getTimestamp() - $issuedAt < self::MIN_SECONDS_TO_FILL);
    }

    public static function issueFormToken(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (is_string($phone)) {
            $this->merge(['phone' => PhoneNumber::normalize($phone) ?? $phone]);
        }
    }

    private function formIssuedAt(): ?int
    {
        try {
            $issuedAt = Crypt::decryptString((string) $this->string('form_token'));
        } catch (DecryptException) {
            return null;
        }

        return ctype_digit($issuedAt) ? (int) $issuedAt : null;
    }
}
