@props(['label', 'hint' => null])
{{-- An on/off switch. Pass wire:model.live (or any checkbox attributes) on the tag. --}}
<label class="flex cursor-pointer items-start justify-between gap-4">
    <span class="min-w-0">
        <span class="block text-sm font-medium text-slate-800">{{ $label }}</span>
        @if ($hint)<span class="mt-0.5 block text-xs text-slate-500">{{ $hint }}</span>@endif
    </span>
    <span class="relative mt-0.5 inline-flex shrink-0">
        <input type="checkbox" role="switch" {{ $attributes->class(['peer sr-only']) }}>
        <span class="h-6 w-11 rounded-full bg-slate-300 transition-colors peer-checked:bg-indigo-600 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-600/40"></span>
        <span class="pointer-events-none absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></span>
    </span>
</label>
