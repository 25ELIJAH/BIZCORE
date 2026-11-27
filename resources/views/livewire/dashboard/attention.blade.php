<div class="mb-9">
    @if ($data !== [])
        <h2 class="mb-3 text-[13px] font-semibold uppercase tracking-wide text-slate-900">Needs your attention
            @if ($unread)<a href="{{ route('notifications.index') }}" class="ml-2 rounded-full bg-orange-500 px-2 py-0.5 text-[11px] font-semibold normal-case tracking-normal text-white">{{ $unread }} unread</a>@endif
        </h2>

        @if ($notice)<p class="mb-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ $notice }}</p>@endif
        @error('leave')<p class="mb-3 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{{ $message }}</p>@enderror

        <div class="grid gap-4 lg:grid-cols-2">
            @isset($data['leave'])
                <section class="rounded-xl border border-slate-200 bg-white p-4 lg:col-span-2">
                    <div class="flex items-center justify-between"><h3 class="text-sm font-semibold">Leave requests waiting <span class="ml-1 text-slate-400">({{ $data['leave']['count'] }})</span></h3><a href="{{ route('hr.leave') }}" class="text-xs font-medium text-indigo-600 hover:underline">Open Leave</a></div>
                    @forelse ($data['leave']['rows'] as $r)
                        <div wire:key="al-{{ $r->id }}" class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3 text-sm">
                            <div>
                                <p class="font-medium text-slate-800">{{ $r->employee?->fullName() ?? 'Employee' }} <span class="font-normal text-slate-500">&middot; {{ \App\Models\LeaveRequest::TYPES[$r->type] ?? $r->type }}</span></p>
                                <p class="text-xs text-slate-500">{{ $r->start_date->format('d M') }} to {{ $r->end_date->format('d M Y') }} &middot; {{ rtrim(rtrim((string) $r->days, '0'), '.') }} working day(s){{ $r->reason ? ' · '.$r->reason : '' }}</p>
                            </div>
                            @if ($rejectId === $r->id)
                                <form wire:submit="reject" class="flex flex-wrap items-center gap-2">
                                    <input type="text" wire:model="reason" placeholder="Reason for rejecting" aria-label="Reason" class="input !py-1.5 text-sm" autofocus>
                                    <button type="submit" class="rounded-full bg-red-600 px-3 py-1.5 text-xs font-semibold text-white">Reject</button>
                                    <button type="button" wire:click="cancelReject" class="text-xs text-slate-500 hover:underline">Cancel</button>
                                    @error('reason')<span class="w-full text-xs text-red-600">{{ $message }}</span>@enderror
                                </form>
                            @else
                                <div class="flex gap-2">
                                    <button type="button" wire:click="approve({{ $r->id }})" wire:loading.attr="disabled" class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button>
                                    <button type="button" wire:click="startReject({{ $r->id }})" class="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Reject</button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="mt-2 text-sm text-slate-500">Nothing waiting. You are up to date.</p>
                    @endforelse
                </section>
            @endisset

            @isset($data['orders'])
                <section class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between"><h3 class="text-sm font-semibold">Purchase orders to approve <span class="ml-1 text-slate-400">({{ $data['orders']['count'] }})</span></h3><a href="{{ route('purchase-orders.index') }}" class="text-xs font-medium text-indigo-600 hover:underline">Open</a></div>
                    @forelse ($data['orders']['rows'] as $o)
                        <a wire:key="ao-{{ $o->id }}" href="{{ route('purchase-orders.show', $o) }}" class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-sm hover:text-indigo-600">
                            <span>{{ $o->po_number }} <span class="text-slate-500">&middot; {{ $o->supplier?->name }}</span></span><span class="text-slate-600">{{ $currency }} {{ number_format((float) $o->total, 2) }}</span>
                        </a>
                    @empty
                        <p class="mt-2 text-sm text-slate-500">None waiting.</p>
                    @endforelse
                </section>
            @endisset

            @isset($data['sales'])
                <section class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between"><h3 class="text-sm font-semibold">Latest sales</h3><a href="{{ route('sales.index') }}" class="text-xs font-medium text-indigo-600 hover:underline">All sales</a></div>
                    @foreach ($data['sales']['recent'] as $s)
                        <a wire:key="as-{{ $s->id }}" href="{{ route('sales.show', $s) }}" class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3 text-sm hover:text-indigo-600">
                            <span class="min-w-0 truncate">{{ $s->invoice?->invoice_number }} <span class="text-slate-500">&middot; {{ $s->customer?->name ?? 'Walk-in' }} &middot; by {{ $s->user?->name ?? 'unknown' }}</span></span><span class="shrink-0 text-slate-600">{{ $currency }} {{ number_format((float) $s->total, 2) }}</span>
                        </a>
                    @endforeach
                    @if ($data['sales']['recent']->isEmpty())<p class="mt-2 text-sm text-slate-500">No sales yet.</p>@endif
                    @if ($data['sales']['overdue'])<a href="{{ route('sales.index', ['payment' => 'overdue']) }}" class="mt-3 block rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 hover:bg-amber-100">{{ $data['sales']['overdue'] }} overdue invoice(s) to chase</a>@endif
                </section>
            @endisset

            @isset($data['stock'])
                @if ($data['stock'])
                    <a href="{{ route('inventory.products') }}" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 hover:bg-amber-100">{{ $data['stock'] }} product(s) at or below their minimum stock. Review stock.</a>
                @endif
            @endisset

            @isset($data['mine'])
                @if ($data['mine']->isNotEmpty())
                    <section class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="flex items-center justify-between"><h3 class="text-sm font-semibold">My leave</h3><a href="{{ route('hr.leave') }}" class="text-xs font-medium text-indigo-600 hover:underline">Open</a></div>
                        @foreach ($data['mine'] as $m)
                            @php($tone = ['approved' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-red-50 text-red-700', 'pending' => 'bg-amber-50 text-amber-800', 'cancelled' => 'bg-slate-100 text-slate-600'][$m->status] ?? 'bg-slate-100 text-slate-600')
                            <div wire:key="am-{{ $m->id }}" class="mt-3 border-t border-slate-100 pt-3 text-sm">
                                <div class="flex items-center justify-between gap-3"><span>{{ $m->start_date->format('d M') }} to {{ $m->end_date->format('d M Y') }}</span><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $tone }}">{{ ucfirst($m->status) }}</span></div>
                                @if ($m->decision_note)<p class="mt-1 text-xs text-slate-500">Management note: {{ $m->decision_note }}</p>@endif
                            </div>
                        @endforeach
                    </section>
                @endif
            @endisset
        </div>
    @endif
</div>
