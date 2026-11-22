<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Staff\TillSession;
use App\Till\Till;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Till screens behind sign-in: an unpaired till goes to pairing, nobody signed in goes to sign-in. */
final class EnsureStaffSignedIn
{
    public function __construct(private readonly Till $till, private readonly TillSession $session) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->till->isPaired()) {
            return redirect()->route('pair');
        }

        if ($this->session->staff() === null) {
            return redirect()->route('sign-in');
        }

        $this->session->touch();

        return $next($request);
    }
}
