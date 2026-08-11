<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessageMail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('pages.contact');
    }

    /** @return JsonResponse|RedirectResponse */
    public function send(ContactRequest $request)
    {
        $data = $request->safe()->only(['name', 'email', 'phone', 'topic', 'message']);
        $wantsJson = $request->expectsJson();

        try {
            Mail::to(config('aroma.contact.email'))->send(new ContactMessageMail($data));
        } catch (\Exception $e) {
            Log::error('Contact form email failed', ['error' => $e->getMessage()]);

            if ($wantsJson) {
                return response()->json(['message' => __('contact.form.errors.generic')], 500);
            }

            return redirect()->route('contact')->with('error', __('contact.form.errors.generic'));
        }

        if ($wantsJson) {
            return response()->json(['message' => __('contact.form.success')]);
        }

        return redirect()->route('contact')->with('status', __('contact.form.success'));
    }
}
