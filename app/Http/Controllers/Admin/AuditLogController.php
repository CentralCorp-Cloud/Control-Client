<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::when($request->filled('q'), fn ($q) => $q->where('action', 'like', '%'.$request->q.'%'))->when($request->filled('actor'), fn ($q) => $q->where('actor_id', $request->actor))->latest('created_at')->paginate(30)->withQueryString();

        return view('admin.audit.index', compact('logs'));
    }
}
