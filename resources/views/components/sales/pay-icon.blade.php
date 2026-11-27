@props(['method'])
{{-- Small icon for a payment method tile. Unknown methods get a generic coin. --}}
<svg {{ $attributes->merge(['class' => 'h-6 w-6']) }} fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
    @switch($method)
        @case('cash')
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5v9H2.25v-9zM12 15a2.25 2.25 0 100-4.5A2.25 2.25 0 0012 15zM5.25 11.25h.008v.008H5.25v-.008zm13.5 3h.008v.008h-.008v-.008z"/>
            @break
        @case('mpesa')
        @case('mobile_money')
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5h3A2.25 2.25 0 0115.75 3.75v16.5A2.25 2.25 0 0113.5 22.5h-3a2.25 2.25 0 01-2.25-2.25V3.75A2.25 2.25 0 0110.5 1.5zM11 19.5h2"/>
            @break
        @case('bank_transfer')
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75L12 4.5l9 5.25M4.5 10.5v7.5m4-7.5v7.5m3.5-7.5v7.5m4-7.5v7.5m3.5-7.5v7.5M3 19.5h18"/>
            @break
        @case('card')
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75h19.5v10.5H2.25V6.75zM2.25 10.5h19.5M5.25 14.25h3"/>
            @break
        @case('later')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3.75 2.25M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            @break
        @default
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75v10.5M9.5 9.5c0-1 1-1.75 2.5-1.75s2.5.75 2.5 1.75-1 1.5-2.5 2-2.5 1-2.5 2 1 1.75 2.5 1.75 2.5-.75 2.5-1.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    @endswitch
</svg>
