<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePanelVersionRequest;
use App\Models\PanelVersion;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

class PanelVersionController extends Controller
{
    public function index()
    {
        return view('admin.panel-versions.index', ['versions' => PanelVersion::latest()->paginate(25)]);
    }

    public function create()
    {
        return view('admin.panel-versions.form', ['panelVersion' => new PanelVersion]);
    }

    public function store(StorePanelVersionRequest $request, AuditService $audit)
    {
        $version = DB::transaction(function () use ($request) {
            if ($request->boolean('recommended')) {
                PanelVersion::query()->update(['recommended' => false]);
            }

            return PanelVersion::create($request->validated());
        });
        $audit->record('panel_version.created', $version, ['version' => $version->version]);

        return redirect()->route('admin.panel-versions.index')->with('success', 'Version publiée.');
    }

    public function edit(PanelVersion $panelVersion)
    {
        return view('admin.panel-versions.form', compact('panelVersion'));
    }

    public function update(StorePanelVersionRequest $request, PanelVersion $panelVersion, AuditService $audit)
    {
        DB::transaction(function () use ($request, $panelVersion) {
            if ($request->boolean('recommended')) {
                PanelVersion::query()->whereKeyNot($panelVersion->id)->update(['recommended' => false]);
            }
            $panelVersion->update($request->validated());
        });
        $audit->record('panel_version.updated', $panelVersion, ['version' => $panelVersion->version]);

        return back()->with('success', 'Version mise à jour.');
    }

    public function destroy(PanelVersion $panelVersion, AuditService $audit)
    {
        $panelVersion->update(['active' => false, 'recommended' => false]);
        $audit->record('panel_version.deactivated', $panelVersion, ['version' => $panelVersion->version]);

        return back()->with('success', 'Version désactivée.');
    }
}
