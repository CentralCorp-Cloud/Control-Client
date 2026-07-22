<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PanelVersion;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::with(['owner', 'plan', 'deployment.node'])->when($request->filled('q'), fn ($q) => $q->where(fn ($x) => $x->where('name', 'like', '%'.$request->q.'%')->orWhere('uuid', $request->q)))->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->latest()->paginate(25)->withQueryString();

        return view('admin.projects.index', compact('projects'));
    }

    public function show(Project $project)
    {
        $project->load(['owner', 'plan', 'deployment.node', 'deployment.operations' => fn ($q) => $q->latest()]);
        $panelVersions = PanelVersion::query()->where('active', true)->orderByDesc('recommended')->orderByDesc('created_at')->get();

        return view('admin.projects.show', compact('project', 'panelVersions'));
    }
}
