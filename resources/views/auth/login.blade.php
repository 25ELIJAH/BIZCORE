@extends('layouts.guest')
@section('title', 'Sign in')
@section('content')
<form method="POST" action="{{ route('login') }}" class="space-y-4">
    @csrf
    <div>
        <label for="email" class="mb-1 block text-sm font-medium">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password" class="mb-1 block text-sm font-medium">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
        @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div class="flex items-center justify-between text-sm">
        <label class="flex items-center gap-2"><input type="checkbox" name="remember" class="rounded border-slate-300"> Remember me</label>
        <a href="{{ route('password.request') }}" class="text-indigo-600 hover:underline">Forgot password?</a>
    </div>
    <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">Sign in</button>
</form>
@endsection
