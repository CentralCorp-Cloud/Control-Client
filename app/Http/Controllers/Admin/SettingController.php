<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const ALLOWED = ['registration_open', 'global_maintenance', 'scheduler_ram_margin', 'scheduler_disk_margin', 'payment_grace_days', 'retention_days', 'default_panel_version'];

    public function index()
    {
        return view('admin.settings.index', ['settings' => Setting::whereIn('key', self::ALLOWED)->get()->keyBy('key')]);
    }

    public function update(Request $request, AuditService $audit)
    {
        $data = $request->validate(['settings' => ['required', 'array'], 'settings.*' => ['nullable', 'string', 'max:500']]);
        foreach (array_intersect_key($data['settings'], array_flip(self::ALLOWED)) as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }$audit->record('settings.updated', null, ['keys' => array_keys($data['settings'])]);

        return back()->with('success', 'Paramètres enregistrés.');
    }
}
