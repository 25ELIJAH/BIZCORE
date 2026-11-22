@extends('layouts.guest')
@section('title', 'Recovery codes')
@section('content')
<p class="mb-3 text-sm text-slate-600"><strong>Save these recovery codes now.</strong> Each works once if you lose your phone. They will not be shown again.</p>
<div class="mb-4 grid grid-cols-2 gap-2 rounded-xl bg-brand-soft p-4 font-mono text-sm">
    @foreach ($codes as $code)<span class="select-all">{{ $code }}</span>@endforeach
</div>
<a href="{{ route('home') }}" class="btn-primary w-full !py-2.5">I have saved them, continue</a>
@endsection
