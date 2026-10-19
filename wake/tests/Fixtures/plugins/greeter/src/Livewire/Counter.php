<?php

namespace Greeter\Livewire;

use Livewire\Component;

class Counter extends Component
{
    public int $count = 0;

    public function add(): void
    {
        $this->count++;
    }

    public function render()
    {
        return view('greeter::livewire.counter');
    }
}
