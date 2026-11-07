@extends('layouts.app')

@section('title', 'Confirm password')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center py-12 px-4">
    <form method="POST" action="{{ route('password.confirm') }}" class="max-w-md w-full space-y-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-6">
        @csrf
        <h2 class="text-xl font-semibold text-gray-900 dark:text-slate-100">Confirm your password</h2>
        <p class="text-sm text-gray-600 dark:text-slate-400">This area changes how you sign in, so please enter your password again.</p>
        <div>
            <label for="password" class="sr-only">Password</label>
            <input id="password" name="password" type="password" required autofocus autocomplete="current-password" placeholder="Password"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100">
            @error('password')<span class="text-red-600 text-sm">{{ $message }}</span>@enderror
        </div>
        <button class="w-full py-2 px-4 text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">Confirm</button>
    </form>
</div>
@endsection
