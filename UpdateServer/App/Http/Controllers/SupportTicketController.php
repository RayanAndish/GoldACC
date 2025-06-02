<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Import Auth
use Illuminate\Support\Facades\Log;   // Import Log
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SupportTicketController extends Controller
{
    /**
     * Display a listing of the user's support tickets.
     */
    public function index(): View
    {
        $user = Auth::user(); // Get the authenticated user
        Log::info('User viewing their support tickets.', ['user_id' => $user->id]);

        // Fetch only tickets belonging to the logged-in user
        $tickets = SupportTicket::where('user_id', $user->id)
                                ->latest() // Order by newest first
                                ->paginate(15); // Or your preferred pagination size

        // Use the view from the 'tickets' directory
        return view('tickets.index', compact('tickets'));
    }

    /**
     * Show the form for creating a new support ticket.
     */
    public function create(): View
    {
        Log::info('User viewing create support ticket form.', ['user_id' => Auth::id()]);
        // Use the view from the 'tickets' directory
        return view('tickets.create');
    }

    /**
     * Store a newly created support ticket in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        Log::info('User attempting to store a new support ticket.', ['user_id' => $user->id]);

        $validatedData = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'priority' => 'required|string|in:low,medium,high,critical', // Example priorities
        ]);

        try {
            $ticket = new SupportTicket();
            $ticket->user_id = $user->id; // Assign the logged-in user's ID
            $ticket->subject = $validatedData['subject'];
            $ticket->message = $validatedData['message'];
            $ticket->priority = $validatedData['priority'];
            $ticket->status = 'open'; // Default status for new tickets
            $ticket->save();

            Log::info('User created support ticket successfully.', ['user_id' => $user->id, 'ticket_id' => $ticket->id]);

            // Redirect to the user's ticket list
            return redirect()->route('tickets.index')
                             ->with('success', 'درخواست شما با موفقیت ثبت شد.');

        } catch (\Exception $e) {
            Log::error('Failed to store user support ticket.', [
                 'user_id' => $user->id,
                 'error' => $e->getMessage(),
                 'trace' => $e->getTraceAsString(),
             ]);
            return back()->withInput()->with('error', 'خطا در ثبت درخواست: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified support ticket if it belongs to the user.
     */
    public function show(SupportTicket $ticket): View|RedirectResponse
    {
        $user = Auth::user();

        // Ensure the ticket belongs to the authenticated user
        if ($ticket->user_id !== $user->id) {
            Log::warning('User attempted to view unauthorized ticket.', ['user_id' => $user->id, 'ticket_id' => $ticket->id]);
            abort(403, 'شما اجازه دسترسی به این درخواست را ندارید.'); // Or redirect with error
        }

        Log::info('User viewing their support ticket details.', ['user_id' => $user->id, 'ticket_id' => $ticket->id]);

        // Load replies (optional, if users can see admin replies)
        $ticket->loadMissing('replies.user', 'replies.admin');

        // Use the view from the 'tickets' directory
        return view('tickets.show', compact('ticket'));
    }

    /**
     * Show the form for editing the specified resource.
     * Typically users don't edit tickets directly, maybe just add replies or close.
     * You might not need this method for users.
     */
    public function edit(SupportTicket $ticket)
    {
        // Implement if needed, ensure authorization $ticket->user_id === auth()->id()
        abort(404); // Or redirect, or implement user edit logic
    }

    /**
     * Update the specified resource in storage.
     * Typically users don't update tickets directly, maybe just add replies or close.
     * You might not need this method for users.
     */
    public function update(Request $request, SupportTicket $ticket)
    {
         // Implement if needed, ensure authorization $ticket->user_id === auth()->id()
        abort(404); // Or redirect, or implement user update logic
    }

    /**
     * Remove the specified resource from storage.
     * Typically users don't delete tickets.
     * You might not need this method for users.
     */
    public function destroy(SupportTicket $ticket)
    {
        // Implement if needed, ensure authorization $ticket->user_id === auth()->id()
        abort(404); // Or redirect, or implement user deletion logic
    }
}