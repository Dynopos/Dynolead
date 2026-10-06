<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Kos')]
class CostsPage extends Component
{
    public function render()
    {
        return view('livewire.costs-page');
    }
}
