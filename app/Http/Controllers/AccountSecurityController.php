<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class AccountSecurityController extends Controller
{
    /** Lists the signed-in user's own passkeys. Adding/removing goes through the passkeys package routes. */
    public function index()
    {
        return view('account.security', ['passkeys' => Auth::user()->passkeys()->latest()->get(['id', 'name', 'created_at', 'last_used_at'])]);
    }
}
