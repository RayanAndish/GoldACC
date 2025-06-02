<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\User; // Import User model

class CustomerController extends Controller
{
    public function index()
    {
        // Eager load the user relationship to avoid N+1 queries in the view
        $customers = Customer::with('user')->orderBy('created_at', 'desc')->paginate(15);
        return view('admin.customers.index', compact('customers'));
    }

    public function create()
    {
        // Get users who don't have a customer profile yet
        $availableUsers = User::whereDoesntHave('customer')->orderBy('name')->get();
        return view('admin.customers.create', compact('availableUsers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|unique:customers,email',
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string', // Assuming address is also a field from Customer model
            'user_id' => 'nullable|sometimes|exists:users,id|unique:customers,user_id', // Nullable, exists in users, and unique among customers
        ]);

        // Ensure user_id is null if an empty string or '0' is passed from the form
        if (empty($data['user_id'])) {
            $data['user_id'] = null;
        }

        Customer::create($data);
        return redirect()->route('admin.customers.index')
                         ->with('success', 'مشتری با موفقیت افزوده شد.');
    }

    public function edit(Customer $customer)
    {
        // Eager load the user associated with this customer
        $customer->load('user');

        // Get users who don't have a customer profile OR are the current customer's user
        $availableUsers = User::whereDoesntHave('customer')
                                ->orWhere('id', $customer->user_id) // Include the currently linked user
                                ->orderBy('name')
                                ->get();

        return view('admin.customers.edit', compact('customer', 'availableUsers'));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => "required|email|unique:customers,email,{$customer->id}",
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string', // Assuming address is also a field
             // User ID should be nullable, exist in users, and unique among customers *except* for the current customer
            'user_id' => "nullable|sometimes|exists:users,id|unique:customers,user_id,{$customer->id}",
        ]);

        // Ensure user_id is null if an empty string or '0' is passed
        if (empty($data['user_id'])) {
            $data['user_id'] = null;
        }

        $customer->update($data);
        return redirect()->route('admin.customers.index')
                         ->with('success', 'اطلاعات مشتری به‌روز شد.');
    }

    /**
     * Display the specified customer.
     * We can potentially implement a proper show view later.
     */
    public function show(Customer $customer)
    {
        // Eager load relationships if needed for a show view
        $customer->load('user', 'systems'); // Example: Load user and systems
        // return view('admin.customers.show', compact('customer')); // Uncomment when show view is ready
         return redirect()->route('admin.customers.edit', $customer); // Redirect to edit for now
    }

    public function destroy(Customer $customer)
    {
        // Consider what should happen to related data (systems, logs) if a customer is deleted.
        // Setup cascading deletes in migrations or handle deletion logic here if needed.
        $customer->delete();
        return redirect()->route('admin.customers.index')
                         ->with('success', 'مشتری حذف شد.');
    }
}