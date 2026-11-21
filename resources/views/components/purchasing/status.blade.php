@props(['order'])
@php
    $classes = match ($order->status) {
        'draft' => 'bg-slate-100 text-slate-700',
        'pending_approval' => 'bg-amber-100 text-amber-800',
        'approved' => 'bg-sky-100 text-sky-700',
        'partially_received' => 'bg-indigo-100 text-indigo-700',
        'received' => 'bg-emerald-100 text-emerald-700',
        default => 'bg-slate-200 text-slate-600',
    };
@endphp
<span class="badge {{ $classes }}">{{ $order->statusLabel() }}</span>
