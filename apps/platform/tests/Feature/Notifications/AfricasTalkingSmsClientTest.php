<?php

declare(strict_types=1);

use App\Infrastructure\Sms\AfricasTalkingSmsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['vendapos.sms.africastalking' => ['username' => 'vendapos', 'api_key' => 'atsk_test', 'sender_id' => 'VENDAPOS']]);
});

/** AT's answer for one recipient. */
function atRecipient(int $statusCode, string $status, ?string $messageId = 'ATXid_1'): array
{
    return ['SMSMessageData' => [
        'Message' => 'Sent to 1/1 Total Cost: KES 0.8000',
        'Recipients' => [['statusCode' => $statusCode, 'number' => '+254712345678', 'status' => $status, 'cost' => 'KES 0.8000', 'messageId' => $messageId]],
    ]];
}

it('posts the message form-encoded with the API key header', function () {
    Http::fake(['api.africastalking.com/version1/messaging' => Http::response(atRecipient(101, 'Success'), 201)]);

    $result = app(AfricasTalkingSmsClient::class)->send('+254712345678', 'Habari');

    expect($result->accepted)->toBeTrue()->and($result->messageId)->toBe('ATXid_1');
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://api.africastalking.com/version1/messaging'
        && $request->hasHeader('apikey', 'atsk_test')
        && $request->hasHeader('Accept', 'application/json')
        && $request->isForm()
        && $request['username'] === 'vendapos'
        && $request['to'] === '+254712345678'
        && $request['message'] === 'Habari'
        && $request['from'] === 'VENDAPOS');
});

it('uses the sandbox for the sandbox user and AT’s sender ID until ours is registered', function () {
    config(['vendapos.sms.africastalking.username' => 'sandbox', 'vendapos.sms.africastalking.sender_id' => null]);
    Http::fake(['api.sandbox.africastalking.com/version1/messaging' => Http::response(atRecipient(100, 'Processed'), 201)]);

    expect(app(AfricasTalkingSmsClient::class)->send('+254712345678', 'Habari')->accepted)->toBeTrue();
    Http::assertSent(fn (Request $request) => ! isset($request['from']));
});

it('records a refusal without retrying', function (array $answer, string $error) {
    Http::fake(['api.africastalking.com/*' => Http::response($answer, 201)]);

    $result = app(AfricasTalkingSmsClient::class)->send('+254712345678', 'Habari');

    expect($result->accepted)->toBeFalse()->and($result->error)->toBe($error);
})->with([
    'invalid number' => [atRecipient(403, 'InvalidPhoneNumber', null), 'InvalidPhoneNumber (403)'],
    'no balance' => [atRecipient(405, 'InsufficientBalance', null), 'InsufficientBalance (405)'],
    'whole request refused' => [['SMSMessageData' => ['Message' => 'InvalidSenderId', 'Recipients' => []]], 'InvalidSenderId'],
]);

it('throws so the notification is retried when AT is down or refuses the request', function (Closure $answer) {
    Http::fake(['api.africastalking.com/*' => $answer]);

    app(AfricasTalkingSmsClient::class)->send('+254712345678', 'Habari');
})->with([
    'server error' => [fn () => Http::response('Bad gateway', 502)],
    'bad API key' => [fn () => Http::response('The supplied authentication is invalid', 401)],
    'unreachable' => [fn () => throw new ConnectionException('timed out')],
])->throws(RuntimeException::class);

it('refuses to send without credentials', function () {
    config(['vendapos.sms.africastalking.api_key' => null]);

    app(AfricasTalkingSmsClient::class)->send('+254712345678', 'Habari');
})->throws(RuntimeException::class, 'not configured');
