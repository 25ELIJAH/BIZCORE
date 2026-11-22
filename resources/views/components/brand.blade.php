@props(['light' => false, 'tagline' => true, 'size' => 'md'])
{{-- The BIZCORE mark: the logo file at public/images/logo.png (falls back to a "B" tile until it is added), the name in capitals and the tagline. --}}
@php
    $logoPath = public_path('images/logo.png');
    $hasLogo = is_file($logoPath);
    $box = $size === 'lg' ? 'h-14 w-14' : 'h-11 w-11';
@endphp
<span {{ $attributes->class(['flex items-center gap-3']) }}>
    @if ($hasLogo)
        <img src="{{ asset('images/logo.png') }}?v={{ filemtime($logoPath) }}" alt="" class="{{ $box }} shrink-0 rounded-full bg-white object-contain p-0.5">
    @else
        <span class="{{ $box }} flex shrink-0 items-center justify-center rounded-full bg-indigo-600 text-lg font-bold text-white">B</span>
    @endif
    <span class="leading-tight">
        <span class="block text-lg font-extrabold uppercase tracking-wider {{ $light ? 'text-white' : 'text-ink' }}">BIZCORE</span>
        @if ($tagline)<span class="hidden text-[11px] sm:block font-medium {{ $light ? 'text-white/70' : 'text-slate-500' }}">ERP Management System</span>@endif
    </span>
</span>
