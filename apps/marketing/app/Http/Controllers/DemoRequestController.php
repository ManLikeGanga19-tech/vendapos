<?php

namespace App\Http\Controllers;

use App\Actions\SubmitDemoRequest;
use App\Http\Requests\StoreDemoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DemoRequestController
{
    public function create(): View
    {
        return view('pages.demo', [
            'requestId' => old('request_id', (string) Str::uuid()),
            'formToken' => StoreDemoRequest::issueFormToken(),
        ]);
    }

    public function store(StoreDemoRequest $request, SubmitDemoRequest $submit): RedirectResponse
    {
        if (! $request->isLikelySpam()) {
            $submit->handle($request->validated(), app()->getLocale());
        }

        return redirect()->route('demo.thanks');
    }

    public function thanks(): View
    {
        return view('pages.demo-thanks');
    }
}
