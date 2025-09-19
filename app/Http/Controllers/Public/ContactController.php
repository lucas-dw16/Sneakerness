<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessage; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function show()
    {
        return view('pages.contact.index');
    }

    public function submit(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255'],
            'subject' => ['required','string','max:255'],
            'message' => ['required','string','max:5000'],
        ]);

        // Send to configured contact mailbox (fallback to MAIL_FROM_ADDRESS)
        $to = config('mail.contact_to', config('mail.from.address'));

        Mail::to($to)->send(new ContactMessage($data));

        return redirect()->route('contact.show')->with('success','Bericht verzonden!');
    }
}
