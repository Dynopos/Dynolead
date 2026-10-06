<?php

namespace App\Livewire;

use App\Models\Workspace;
use App\Services\Admin\AdminService;
use App\Services\Billing\CreditService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Admin')]
class AdminPage extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    public ?int $managing = null;

    public string $mode = 'grant';

    public string $amount = '5';

    public string $pack = '';

    public string $note = '';

    public ?string $flash = null;

    public function boot(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function manage(int $id): void
    {
        $this->managing = $this->managing === $id ? null : $id;
        $this->note = '';
    }

    public function grant(int $id, AdminService $admin): void
    {
        $this->validate(['amount' => 'required|integer|min:1|max:10000', 'note' => 'nullable|string|max:190']);
        $workspace = Workspace::query()->findOrFail($id);
        $admin->grantCredits($workspace, (int) $this->amount, $this->note, auth()->id());
        $this->flash = "{$workspace->name}: +{$this->amount} kredit.";
        $this->managing = null;
    }

    public function recordPayment(int $id, AdminService $admin, CreditService $credits): void
    {
        $this->validate(['pack' => 'required|in:'.implode(',', array_keys($credits->packs())), 'note' => 'nullable|string|max:190']);
        $workspace = Workspace::query()->findOrFail($id);
        $admin->recordPackPayment($workspace, $this->pack, $this->note);
        $this->flash = "{$workspace->name}: bayaran {$credits->pack($this->pack)->name} direkod.";
        $this->managing = null;
    }

    public function toggleSuspend(int $id, AdminService $admin): void
    {
        $workspace = Workspace::query()->findOrFail($id);
        $admin->setSuspended($workspace, $workspace->suspended_at === null);
        $this->flash = $workspace->name.($workspace->refresh()->suspended_at ? ' digantung.' : ' diaktifkan semula.');
    }

    public function render(AdminService $admin, CreditService $credits)
    {
        $this->pack = $this->pack ?: (string) array_key_first($credits->packs());

        return view('livewire.admin-page', [
            'totals' => $admin->totals(),
            'rows' => $admin->workspaces(trim($this->search)),
            'packs' => $credits->packs(),
        ]);
    }
}
