<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Lead')]
class LeadsPage extends Component
{
    public function render()
    {
        return view('livewire.leads-page');
    }
}
