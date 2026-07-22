<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Services\AuditService;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $incidents = Incident::when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->latest('last_seen_at')->paginate(25);

        return view('admin.incidents.index', compact('incidents'));
    }

    public function update(Request $request, Incident $incident, AuditService $audit)
    {
        $data = $request->validate(['status' => ['required', 'in:ACKNOWLEDGED,RESOLVED']]);
        $incident->update(['status' => $data['status'], 'acknowledged_by' => auth()->id(), 'acknowledged_at' => now(), 'resolved_at' => $data['status'] === 'RESOLVED' ? now() : null]);
        $audit->record('incident.'.strtolower($data['status']), $incident);

        return back();
    }
}
