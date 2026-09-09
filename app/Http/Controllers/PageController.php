<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;

class PageController extends Controller
{
    public function landing()
    {
        return view('landing');
    }

    public function about()
    {
        return view('about');
    }

    public function contact()
    {
        return view('contact');
    }

    public function submitContact(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'message' => 'required|string',
        ]);

        // Option 1: Send an email (uncomment and configure Mail)
        // Mail::to('dante@vumbiventures.com')->send(new ContactMail($validated));

        // Option 2: Store in database
        // Contact::create($validated);

        // Option 3: Simple session flash (no email/db)
        return back()->with('success', 'Thank you for your message! We\'ll get back to you soon.');
    }
}