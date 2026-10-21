<?php

namespace App\Http\Controllers;

use App\Actions\SubmitDemoRequest;
use App\Http\Requests\StoreDemoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use VendaPOS\Core\Enums\BusinessType;

class DemoRequestController
{
    /**
     * Solution pages link here with ?type= and pricing cards with ?plan=, so
     * the form opens already pointed at what the visitor was looking at.
     * Unknown values are ignored; the visitor can still change everything.
     */
    public function create(Request $request): View
    {
        $plan = $request->string('plan')->value();

        return view('pages.demo', [
            'requestId' => old('request_id', (string) Str::uuid()),
            'formToken' => StoreDemoRequest::issueFormToken(),
            'businessType' => old('business_type', BusinessType::tryFrom($request->string('type')->value())?->value),
            'planInterest' => old('plan_interest', array_key_exists($plan, config('marketing.plans')) ? $plan : null),
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
