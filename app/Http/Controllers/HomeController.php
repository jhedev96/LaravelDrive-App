<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class HomeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth')];
    }

    /**
     * Show the application dashboard.
     */
    public function index()
    {
        return view('home');
    }

    /**
     * Mount the drive SPA.
     */
    public function drive(Request $request)
    {
        // window.LD.user feeds the SPA router guard; without it every
        // request to /drive rendered with an undefined blade variable
        return view('drive.index')->with(['user' => $request->user()]);
    }
}
