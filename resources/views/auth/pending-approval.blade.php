<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vumbi AI - Waiting for approval</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-slate-50 text-slate-800 min-h-full flex items-center justify-center px-4">
    <div class="max-w-md w-full bg-white border border-slate-200 rounded-xl shadow-sm p-8 text-center">
        @if($user->approval_status === 'rejected')
            <h1 class="text-xl font-bold text-slate-900">Your request was not approved</h1>
            <p class="mt-3 text-sm text-slate-600">The platform administrator did not approve this account. If you think this is a mistake, contact them directly.</p>
        @else
            <h1 class="text-xl font-bold text-slate-900">Thanks for registering, {{ $user->name }}</h1>
            <p class="mt-3 text-sm text-slate-600">Your account is waiting for approval by the platform administrator. You will be able to sign in and use Vumbi AI once it is approved. Check back soon; no need to register again.</p>
        @endif
        <p class="mt-3 text-xs text-slate-400">Signed in as {{ $user->email }}</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button class="px-4 py-2 text-sm bg-slate-800 text-white rounded-lg hover:bg-slate-700">Sign out</button>
        </form>
    </div>
</body>
</html>
