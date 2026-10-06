<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Cari')]
class SearchPage extends Component
{
    public function render()
    {
        return view('livewire.search-page');
    }
}
