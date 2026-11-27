<?php

namespace App\Livewire\Notifications;

use App\Support\Tenancy\CurrentCompany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/** Everything the business has told this person, newest first, for the company they are working in. */
#[Layout('layouts.app')]
#[Title('Notifications')]
class Inbox extends Component
{
    use WithPagination;

    public bool $unreadOnly = false;

    public function updatingUnreadOnly(): void
    {
        $this->resetPage();
    }

    /** Mark one as read and go to what it is about. */
    public function open(string $id)
    {
        $notice = $this->inbox()->whereKey($id)->firstOrFail();
        $notice->markAsRead();
        $url = $notice->data['url'] ?? null;

        // Only follow links that stay on this site.
        if ($url && str_starts_with($url, url('/'))) {
            return $this->redirect($url);
        }
    }

    public function markAllRead(): void
    {
        $this->inbox()->whereNull('read_at')->update(['read_at' => now()]);
    }

    private function inbox()
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', Auth::user()->getMorphClass())
            ->where('notifiable_id', Auth::id())
            ->where('company_id', app(CurrentCompany::class)->id());
    }

    public function render()
    {
        $ready = Schema::hasTable('notifications');
        $query = $ready ? $this->inbox()->latest()->when($this->unreadOnly, fn ($q) => $q->whereNull('read_at')) : null;

        return view('livewire.notifications.inbox', [
            'ready' => $ready,
            'notices' => $query?->paginate(20),
            'unread' => $ready ? $this->inbox()->whereNull('read_at')->count() : 0,
        ]);
    }
}
