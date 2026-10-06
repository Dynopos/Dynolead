<?php

namespace App\Livewire;

use App\Models\Workspace;
use App\Services\Admin\AdminService;
use App\Services\Billing\PlanService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Admin')]
class AdminPage extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    public ?int $managing = null;

    public string $plan = 'asas';

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

    public function addPeriod(int $id, AdminService $admin, PlanService $plans): void
    {
        $this->validate(['plan' => 'required|in:'.implode(',', array_keys($plans->forSale())), 'note' => 'nullable|string|max:190']);
        $workspace = Workspace::query()->findOrFail($id);
        $admin->addPaidPeriod($workspace, $this->plan, $this->note);
        $this->flash = "{$workspace->name}: +30 hari pelan {$this->plan}.";
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

    public function render(AdminService $admin, PlanService $plans)
    {
        return view('livewire.admin-page', [
            'totals' => $admin->totals(),
            'rows' => $admin->workspaces(trim($this->search)),
            'salePlans' => $plans->forSale(),
        ]);
    }
}
