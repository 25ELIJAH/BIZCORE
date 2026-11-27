<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1>Notifications</h1>
            <p class="mt-1 text-sm text-slate-500">Sales, leave requests, purchase orders and low stock, as they happen.</p>
        </div>
        <div class="flex items-center gap-4 text-sm">
            <label class="flex items-center gap-2 text-slate-600"><input type="checkbox" wire:model.live="unreadOnly" class="rounded border-slate-300"> Unread only</label>
            @if ($unread)<button type="button" wire:click="markAllRead" class="font-medium text-indigo-600 hover:underline">Mark all as read</button>@endif
        </div>
    </div>

    @if (! $ready)
        <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">The notifications table has not been created yet. Run the database migrations to switch the inbox on. Emails still work.</p>
    @elseif ($notices->isEmpty())
        <div class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">{{ $unreadOnly ? 'Nothing unread.' : 'Nothing yet. You will see new sales, leave requests and approvals here.' }}</div>
    @else
        <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
            @foreach ($notices as $n)
                <li wire:key="n-{{ $n->id }}">
                    <button type="button" wire:click="open('{{ $n->id }}')" class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50 {{ $n->read_at ? '' : 'bg-indigo-50/40' }}">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $n->read_at ? 'bg-transparent' : 'bg-orange-500' }}" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm {{ $n->read_at ? 'text-slate-700' : 'font-semibold text-slate-900' }}">{{ $n->data['title'] ?? 'Update' }}</span>
                            <span class="mt-0.5 block text-sm text-slate-500">{{ $n->data['body'] ?? '' }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-slate-400" title="{{ $n->created_at }}">{{ $n->created_at->diffForHumans() }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $notices->links() }}</div>
    @endif
</div>
