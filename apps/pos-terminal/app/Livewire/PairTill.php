<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\PicksLanguage;
use App\Till\PairingFailed;
use App\Till\PairThisTill;
use App\Till\Till;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class PairTill extends Component
{
    use PicksLanguage;

    /** Normalised: upper case, no dash, at most 8 allowed characters. */
    public string $code = '';

    public ?string $error = null;

    public function mount(Till $till): void
    {
        if ($till->isPaired()) {
            $this->redirectRoute('ready');
        }
    }

    /** Typed on a physical keyboard: keep only characters a code can have. */
    public function updatedCode(string $value): void
    {
        $allowed = array_filter(
            str_split(PairThisTill::normalise($value)),
            fn (string $char) => str_contains(PairThisTill::ALPHABET, $char),
        );

        $this->code = substr(implode('', $allowed), 0, PairThisTill::LENGTH);
        $this->error = null;
    }

    public function press(string $key): void
    {
        $key = strtoupper($key);

        // 0, 1, I, L, O and U from a physical keyboard: say why nothing appeared.
        if (! str_contains(PairThisTill::ALPHABET, $key)) {
            $this->error = __('terminal.pair.not_used');

            return;
        }

        $this->updatedCode($this->code.$key);
    }

    public function backspace(): void
    {
        $this->code = substr($this->code, 0, -1);
        $this->error = null;
    }

    public function clear(): void
    {
        $this->code = '';
        $this->error = null;
    }

    public function pair(PairThisTill $pairThisTill): void
    {
        try {
            $downloaded = $pairThisTill->handle($this->code);
        } catch (PairingFailed $e) {
            $this->error = __("terminal.pair.{$e->reason}");

            return;
        }

        session()->flash('download_pending', ! $downloaded);
        $this->redirectRoute('ready');
    }

    public function render(Till $till): View
    {
        $keys = str_split(PairThisTill::ALPHABET);

        return view('livewire.pair-till', [
            'removed' => $till->isRemoved(),
            'digits' => array_values(array_filter($keys, ctype_digit(...))),
            'letters' => array_values(array_filter($keys, ctype_alpha(...))),
            // One box per character, typed or still empty, in two groups of four. (Not "slots": Livewire reserves it.)
            'boxes' => array_chunk(array_pad(str_split($this->code), PairThisTill::LENGTH, ''), 4),
            'complete' => PairThisTill::isWellFormed($this->code),
        ]);
    }
}
