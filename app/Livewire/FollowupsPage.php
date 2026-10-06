<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Follow-up')]
class FollowupsPage extends Component
{
    public function render()
    {
        return view('livewire.followups-page');
    }
}
