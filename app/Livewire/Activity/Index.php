<?php

namespace App\Livewire\Activity;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Who did what in the current company, newest first. */
#[Layout('layouts.app')]
#[Title('Activity')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $user = null;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('activity_logs.view');
    }

    public function updating(string $property): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $companyId = app(CurrentCompany::class)->id();

        $logs = ActivityLog::query()->with('user:id,name')->where('company_id', $companyId)
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('action', 'like', '%'.$this->search.'%')->orWhere('description', 'like', '%'.$this->search.'%')))
            ->when($this->user, fn ($q) => $q->where('user_id', $this->user))
            ->when($this->from !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->latest('created_at')->latest('id')->paginate(20);

        $userIds = ActivityLog::where('company_id', $companyId)->whereNotNull('user_id')->distinct()->pluck('user_id');

        return view('livewire.activity.index', [
            'logs' => $logs,
            'users' => User::whereIn('id', $userIds)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
