<?php // app/Http/Controllers/Admin/SupportTicketController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // Import Auth facade
use Illuminate\Validation\Rule; // For status validation

class SupportTicketController extends Controller
{
    /**
     * Display a listing of all support tickets.
     */
    public function index(Request $request): View
    {
        Log::info('Admin viewing support ticket list.');
        $query = SupportTicket::with('user:id,name')->latest();

        if ($request->filled('status') && in_array($request->status, ['open', 'in_progress', 'answered', 'closed', 'resolved'])) {
             $query->where('status', $request->status);
        }

        $tickets = $query->paginate(20);
        return view('admin.tickets.index', compact('tickets'));
    }

    /**
     * Display the specified support ticket including its replies.
     */
    public function show(SupportTicket $ticket): View
    {
        Log::info('Admin viewing support ticket.', ['ticket_id' => $ticket->id]);
        // Eager load replies along with their user or admin sender
        $ticket->loadMissing(['user', 'replies.user', 'replies.admin']);
        return view('admin.tickets.show', compact('ticket'));
    }

    /**
     * Update the specified support ticket's status and store admin reply.
     */
    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        Log::info('Admin attempting to update support ticket.', ['ticket_id' => $ticket->id]);

        // Validate status change and admin reply content
        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'answered', 'closed', 'resolved'])], // Added 'answered'
            'admin_reply' => 'nullable|string|max:5000', // Reply is optional
        ]);

        try {
            $ticket->status = $validated['status'];
            $replyAdded = false;

            // Store admin reply if provided
            if (!empty(trim($validated['admin_reply']))) {
                $ticket->replies()->create([
                    // 'user_id' => null, // Not a user reply
                    'admin_id' => Auth::guard('admin')->id(), // Get logged-in admin's ID
                    'message' => $validated['admin_reply'],
                ]);
                $replyAdded = true;

                // Optionally change status automatically when admin replies
                if ($ticket->status === 'open' || $ticket->status === 'in_progress') {
                    $ticket->status = 'answered'; // Set to answered after admin replies
                }
                 Log::info('Admin reply added successfully.', ['ticket_id' => $ticket->id]);
            }

            $ticket->save(); // Save status change (and potentially auto-status change from reply)

            $message = $replyAdded ? 'پاسخ ثبت و وضعیت تیکت به‌روزرسانی شد.' : 'وضعیت تیکت به‌روزرسانی شد.';
            Log::info('Admin updated support ticket successfully.', ['ticket_id' => $ticket->id, 'new_status' => $ticket->status, 'reply_added' => $replyAdded]);

            return redirect()->route('admin.tickets.show', $ticket)->with('success', $message);

        } catch (\Exception $e) {
             Log::error('Failed to update support ticket.', [
                 'ticket_id' => $ticket->id,
                 'error' => $e->getMessage(),
                 'trace' => $e->getTraceAsString(), // Include trace for debugging
             ]);
             return back()->withInput()->with('error', 'خطا در به‌روزرسانی تیکت: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified support ticket from storage.
     */
    public function destroy(SupportTicket $ticket): RedirectResponse
    {
        // ... (destroy logic remains the same) ...
         Log::warning('Admin attempting to delete support ticket.', ['ticket_id' => $ticket->id]);
        try {
            $ticket->delete();
            Log::info('Admin deleted support ticket successfully.', ['ticket_id' => $ticket->id]);
            return redirect()->route('admin.tickets.index')->with('success', 'تیکت با موفقیت حذف شد.');
        } catch (\Exception $e) {
            Log::error('Failed to delete support ticket.', [
                 'ticket_id' => $ticket->id,
                 'error' => $e->getMessage(),
             ]);
            return back()->with('error', 'خطا در حذف تیکت: ' . $e->getMessage());
        }
    }
}