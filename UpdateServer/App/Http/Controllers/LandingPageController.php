<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View; // Import View

class LandingPageController extends Controller
{
    /**
     * Display the landing page.
     *
     * @return \Illuminate\View\View
     */
    public function index(): View // Added return type hint
    {
        // You can pass data to the view later if needed
        // $features = Feature::all();
        // return view('landing', compact('features'));
        return view('landing');
    }
}