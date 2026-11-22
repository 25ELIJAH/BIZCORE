@props(['title', 'close' => 'closeModal', 'maxWidth' => 'max-w-lg'])
{{-- Server-driven modal: render it conditionally (@if) from the Livewire component. --}}
<div class="fixed inset-0 z-40 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="{{ $title }}"
     wire:keydown.escape.window="{{ $close }}">
    <div class="absolute inset-0 animate-fade-in bg-slate-900/50 backdrop-blur-sm" wire:click="{{ $close }}"></div>
    <div class="relative max-h-[90vh] w-full {{ $maxWidth }} animate-pop overflow-y-auto rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/5">
        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white/90 px-5 py-4 backdrop-blur">
            <h2 class="text-lg font-semibold tracking-tight">{{ $title }}</h2>
            <button type="button" wire:click="{{ $close }}" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 active:scale-95" aria-label="Close">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <div class="px-5 py-4">{{ $slot }}</div>
    </div>
</div>
