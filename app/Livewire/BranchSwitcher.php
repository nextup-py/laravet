<?php

namespace App\Livewire;

use App\Models\Branch;
use App\Support\BranchContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Selector de la sucursal activa en la barra superior. Lo usa el personal sin sucursal propia
 * (el administrador) y solo aparece cuando hay más de una sucursal habilitada.
 */
class BranchSwitcher extends Component
{
    public function switch(int $branchId): void
    {
        abort_unless($this->canSwitch(), 403);

        BranchContext::switchTo($branchId);

        // Se recarga la pantalla actual para que todo refleje la sucursal elegida.
        $this->redirect(request()->header('Referer') ?: url('/'), navigate: true);
    }

    /** Solo quien no tiene sucursal propia elige la activa. */
    private function canSwitch(): bool
    {
        $user = auth()->user();

        return $user !== null && ! $user->branch_id;
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branches(): Collection
    {
        return Branch::query()->active()->orderBy('code')->get();
    }

    public function render(): View
    {
        $branches = $this->canSwitch() ? $this->branches() : collect();

        return view('livewire.branch-switcher', [
            'branches' => $branches,
            'current' => $branches->isNotEmpty() ? BranchContext::current() : null,
            'visible' => $branches->count() > 1,
        ]);
    }
}
