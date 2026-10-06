<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Produk')]
class ProductsPage extends Component
{
    public function render()
    {
        return view('livewire.products-page');
    }
}
