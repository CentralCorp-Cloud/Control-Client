<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentOperation;
use Illuminate\Http\Request;

class OperationController extends Controller
{
    public function index(Request $request)
    {
        $operations = AgentOperation::with(['deployment.project.owner', 'node'])->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))->latest()->paginate(25)->withQueryString();

        return view('admin.operations.index', compact('operations'));
    }

    public function show(AgentOperation $operation)
    {
        $operation->load(['deployment.project.owner', 'node']);

        return view('admin.operations.show', compact('operation'));
    }
}
