<?php

namespace App\Http\Controllers;

use App\Mail\ContactMail;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Jobs\SendContactEmail;

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
            'message' => 'required|string|min:10',
        ]);

        // 1. Store in database
        $contact = Contact::create($validated);

        // 2. Send email notification
        try {
            Mail::to('dante@vumbiventures.com')->send(new ContactMail($validated));
        } catch (\Exception $e) {
            // Log error but don't break the user experience
            Log::error('Failed to send contact email: ' . $e->getMessage());
        }


        SendContactEmail::dispatch($validated);
        return back()->with('success', 'Thank you for your message! We\'ll get back to you soon.');
    }
}
