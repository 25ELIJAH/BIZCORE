@props(['title', 'close' => 'closeModal', 'maxWidth' => 'max-w-lg'])
{{-- Server-driven modal: render it conditionally (@if) from the Livewire component. --}}
<div class="fixed inset-0 z-40 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="{{ $title }}"
     wire:keydown.escape.window="{{ $close }}">
    <div class="absolute inset-0 bg-slate-900/50" wire:click="{{ $close }}"></div>
    <div class="relative max-h-[90vh] w-full {{ $maxWidth }} overflow-y-auto rounded-xl bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold">{{ $title }}</h2>
            <button type="button" wire:click="{{ $close }}" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Close">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <div class="px-5 py-4">{{ $slot }}</div>
    </div>
</div>
