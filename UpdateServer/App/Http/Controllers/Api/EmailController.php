<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public function send(Request $request)
    {
        $systemId = $request->input('system_id');
        $customerId = $request->input('customer_id');
        $toEmail = $request->input('to_email');
        $subject = $request->input('subject');
        $message = $request->input('message');

        // ارسال ایمیل (با Mail::raw یا قالب دلخواه)
        try {
            Mail::raw($message, function ($mail) use ($toEmail, $subject) {
                $mail->to($toEmail)->subject($subject);
            });
            $status = 'success';
        } catch (\Exception $e) {
            $status = 'failed';
        }

        // ثبت لاگ ایمیل
        $emailLog = EmailLog::create([
            'system_id' => $systemId,
            'customer_id' => $customerId,
            'to_email' => $toEmail,
            'subject' => $subject,
            'message' => $message,
            'status' => $status,
            'sent_at' => now(),
        ]);

        return response()->json(['success' => $status === 'success', 'email_log_id' => $emailLog->id]);
    }
}