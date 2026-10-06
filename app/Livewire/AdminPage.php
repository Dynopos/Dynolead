<?php

namespace App\Livewire;

use App\Models\Workspace;
use App\Services\Admin\AdminService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Admin')]
class AdminPage extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    public ?int $managing = null;

    /** balance|topup|activation */
    public string $mode = 'balance';

    public string $amount = '10';

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

    public function save(int $id, AdminService $admin): void
    {
        $this->validate([
            'mode' => 'required|in:balance,topup,activation',
            'amount' => 'required_unless:mode,activation|nullable|numeric|min:1|max:100000',
            'note' => 'nullable|string|max:190',
        ]);

        $workspace = Workspace::query()->findOrFail($id);

        if ($this->mode === 'balance') {
            $admin->addBalance($workspace, (float) $this->amount, $this->note, auth()->id());
            $this->flash = "{$workspace->name}: +RM".number_format((float) $this->amount, 2).' baki.';
        } else {
            $admin->recordPayment($workspace, $this->mode, (float) $this->amount, $this->note);
            $this->flash = "{$workspace->name}: ".($this->mode === 'activation' ? 'akaun diaktifkan.' : 'bayaran tambah baki direkod.');
        }

        $this->managing = null;
    }

    public function extendTrial(int $id, AdminService $admin): void
    {
        $workspace = Workspace::query()->findOrFail($id);
        $admin->extendTrial($workspace);
        $this->flash = "{$workspace->name}: percubaan +7 hari.";
    }

    public function toggleSuspend(int $id, AdminService $admin): void
    {
        $workspace = Workspace::query()->findOrFail($id);
        $admin->setSuspended($workspace, $workspace->suspended_at === null);
        $this->flash = $workspace->name.($workspace->refresh()->suspended_at ? ' digantung.' : ' diaktifkan semula.');
    }

    public function render(AdminService $admin)
    {
        return view('livewire.admin-page', [
            'totals' => $admin->totals(),
            'rows' => $admin->workspaces(trim($this->search)),
            'trialLeads' => (int) config('billing.trial_leads'),
        ]);
    }
}
