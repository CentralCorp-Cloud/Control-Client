<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecurityController extends Controller
{
    public function index()
    {
        $sessions = DB::table('sessions')->where('user_id', auth()->id())->orderByDesc('last_activity')->get();

        return view('account.security', compact('sessions'));
    }

    public function destroySession(Request $request, string $session)
    {
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', $session)->delete();

        return back()->with('success', 'Session révoquée.');
    }
}
