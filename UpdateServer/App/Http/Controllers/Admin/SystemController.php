<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\System;
use App\Models\Customer;

class SystemController extends Controller
{
    public function index()
    {
        $systems = System::with('customer')
                         ->orderBy('created_at', 'desc')
                         ->paginate(15);
        return view('admin.systems.index', compact('systems'));
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        return view('admin.systems.create', compact('customers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'     => 'required|exists:customers,id',
            'name'            => 'required|string|max:255',
            'domain'          => 'required|url|unique:systems,domain',
            'status'          => 'required|in:active,inactive',
            'current_version' => 'nullable|string|max:50',
        ]);

        System::create($data);
        return redirect()->route('admin.systems.index')
                         ->with('success', 'سامانه جدید افزوده شد.');
    }

    public function edit(System $system)
    {
        $customers = Customer::orderBy('name')->get();
        return view('admin.systems.edit', compact('system','customers'));
    }

    public function update(Request $request, System $system)
    {
        $data = $request->validate([
            'customer_id'     => 'required|exists:customers,id',
            'name'            => 'required|string|max:255',
            'domain'          => "required|url|unique:systems,domain,{$system->id}",
            'status'          => 'required|in:active,inactive',
            'current_version' => 'nullable|string|max:50',
        ]);

        $system->update($data);
        return redirect()->route('admin.systems.index')
                         ->with('success', 'سامانه به‌روز شد.');
    }

    /**
     * Display the specified system.
     *
     * @param  \App\Models\System  $system
     * @return \Illuminate\View\View
     */
    public function show(System $system)
    {
        // Later, you can pass data to a 'show' view
        // return view('admin.systems.show', compact('system'));
        return redirect()->route('admin.systems.edit', $system); // Redirect to edit page for now
    }

    public function destroy(System $system)
    {
        $system->delete();
        return redirect()->route('admin.systems.index')
                         ->with('success', 'سامانه حذف شد.');
    }
}