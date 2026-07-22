<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class WebCronController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = (string) config('centralcloud.webcron.username');
        $secret = (string) config('centralcloud.webcron.secret');
        abort_if($secret === '' || ! hash_equals($user, (string) $request->getUser()) || ! hash_equals($secret, (string) $request->getPassword()), 401);
        Artisan::call('centralcloud:tick');

        return response()->json(['ok' => true]);
    }
}
