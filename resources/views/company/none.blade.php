@extends('layouts.guest')
@section('title', 'No company')
@section('content')
<h2 class="mb-2 text-lg font-semibold">No company assigned</h2>
<p class="mb-4 text-sm text-slate-600">Your account is not assigned to any active company yet. Ask an administrator to give you access.</p>
<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit" class="w-full rounded-lg border border-slate-300 px-4 py-2 font-medium hover:bg-slate-50">Sign out</button>
</form>
@endsection
