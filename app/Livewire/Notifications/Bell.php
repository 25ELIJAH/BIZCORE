<?php

namespace App\Livewire\Notifications;

use App\Support\Notifier;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/** The bell in the header: unread count for the current company, refreshed every minute. */
class Bell extends Component
{
    public function render()
    {
        $count = 0;

        try {
            if (Schema::hasTable('notifications')) {
                $count = Auth::user()->unreadNotifications()->where('company_id', app(CurrentCompany::class)->id())->count();
            }
        } catch (\Throwable) {
            // The bell must never take a page down.
        }

        return view('livewire.notifications.bell', ['count' => $count]);
    }
}
