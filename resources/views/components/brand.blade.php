@props(['light' => false, 'tagline' => true, 'size' => 'md'])
{{-- The BIZCORE mark: the emblem at public/images/logo.png, the name in capitals and the tagline.
     The file is served as a static asset (on Vercel it is not on the PHP function's disk), so the page always asks for it; a "B" tile shows only if it fails to load. --}}
@php
    $box = $size === 'lg' ? 'h-14 w-14' : 'h-11 w-11';
@endphp
<span {{ $attributes->class(['flex items-center gap-3']) }}>
    <span class="{{ $box }} relative flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-white">
        <span class="absolute inset-0 flex items-center justify-center rounded-full bg-indigo-600 text-lg font-bold text-white" aria-hidden="true">B</span>
        <img src="{{ asset('images/logo.png') }}?v=3" alt="" class="relative h-full w-full bg-white object-contain p-0.5" onerror="this.remove()">
    </span>
    <span class="leading-tight">
        <span class="block text-lg font-extrabold uppercase tracking-wider {{ $light ? 'text-white' : 'text-ink' }}">BIZCORE</span>
        @if ($tagline)<span class="hidden text-[11px] sm:block font-medium {{ $light ? 'text-white/70' : 'text-slate-500' }}">ERP Management System</span>@endif
    </span>
</span>
