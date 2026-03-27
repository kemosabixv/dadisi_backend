<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Models\SystemSetting;

/**
 * @group Contact
 * @groupDescription Public contact form endpoint
 */
class ContactController extends Controller
{
    /**
     * Send Contact Message
     *
     * Sends a contact form message to the site's configured contact email.
     *
     * @bodyParam name string required The sender's full name. Example: Jane Doe
     * @bodyParam email string required The sender's email. Example: jane@example.com
     * @bodyParam subject string required Message subject. Example: Partnership inquiry
     * @bodyParam message string required Message body. Example: I'd like to discuss...
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|min:3|max:255',
            'message' => 'required|string|min:10|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contactEmail = SystemSetting::where('key', 'contact_email')->first()?->value ?? 'info@dadisilab.com';

        try {
            Mail::raw(
                "Name: {$request->name}\n" .
                "Email: {$request->email}\n" .
                "Subject: {$request->subject}\n\n" .
                $request->message,
                function ($mail) use ($request, $contactEmail) {
                    $mail->to($contactEmail)
                        ->replyTo($request->email, $request->name)
                        ->subject("[Dadisi Contact] {$request->subject}");
                }
            );

            return response()->json([
                'success' => true,
                'message' => 'Your message has been sent successfully.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Contact form failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message. Please try again later.',
            ], 500);
        }
    }
}
