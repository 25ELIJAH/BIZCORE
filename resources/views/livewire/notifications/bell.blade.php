<a href="{{ route('notifications.index') }}" wire:poll.60s class="relative rounded-full p-2 text-slate-500 transition-colors hover:bg-brand-soft hover:text-indigo-600" title="Notifications" aria-label="Notifications{{ $count ? ", {$count} unread" : '' }}">
    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
    @if ($count)
        <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-orange-500 px-1 text-[10px] font-bold leading-none text-white">{{ $count > 99 ? '99+' : $count }}</span>
    @endif
</a>
