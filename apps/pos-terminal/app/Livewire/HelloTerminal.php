<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class HelloTerminal extends Component
{
    public function render(): View
    {
        return view('livewire.hello-terminal');
    }
}
