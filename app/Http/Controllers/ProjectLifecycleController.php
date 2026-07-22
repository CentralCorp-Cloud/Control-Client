<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Deployment;
use App\Models\PanelVersion;
use App\Services\AuditService;
use App\Services\DeploymentLifecycleService;
use App\Services\DeploymentPurgeService;
use App\Services\NodeAgent\NodeAgentClient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProjectLifecycleController extends Controller
{
    public function action(Request $request, Deployment $deployment, string $action, DeploymentLifecycleService $service, AuditService $audit)
    {
        $this->authorize('update', $deployment);
        abort_if($deployment->hasActiveOperation(), 409, 'Une opération est déjà en cours.');
        $operation = match ($action) {
            'start' => $service->start($deployment),'stop' => $service->stop($deployment),'restart' => $service->restart($deployment),default => abort(404)
        };
        $audit->record("deployment.{$action}", $deployment, ['correlation_id' => $operation->correlation_id]);

        return back()->with('success', 'Opération demandée.');
    }

    public function adminReset(Request $request, Deployment $deployment, DeploymentLifecycleService $service, AuditService $audit)
    {
        $this->authorize('update', $deployment);
        $data = $request->validate(['admin_email' => ['required', 'email:rfc', 'max:255'], 'admin_password' => ['required', 'confirmed', Password::min(12)]]);
        $service->adminReset($deployment, $data['admin_email'], $data['admin_password']);
        $audit->record('deployment.admin_reset_requested', $deployment);

        return back()->with('success', 'Réinitialisation demandée.');
    }

    public function logs(Request $request, Deployment $deployment, NodeAgentClient $client)
    {
        $this->authorize('view', $deployment);
        $data = $request->validate(['limit' => ['nullable', 'integer', 'between:1,1000'], 'cursor' => ['nullable', 'string', 'max:500']]);
        abort_unless($deployment->node !== null, 409, 'Ce CentralPanel n’est associé à aucun Node.');

        return response()->json($client->logs($deployment->node, $deployment->uuid, $data['limit'] ?? 100, $data['cursor'] ?? null))->header('Cache-Control', 'no-store');
    }

    public function logPage(Deployment $deployment)
    {
        $this->authorize('view', $deployment);
        $deployment->load('project');

        return view('projects.logs', compact('deployment'));
    }

    public function upgrade(Request $request, Deployment $deployment, DeploymentLifecycleService $service, AuditService $audit)
    {
        $this->authorize('update', $deployment);
        abort_unless($request->user()->can('manage-infrastructure'), 403);
        abort_if($deployment->hasActiveOperation(), 409, 'Une opération est déjà en cours.');
        $data = $request->validate(['panel_version_id' => ['required', 'integer', 'exists:panel_versions,id']]);
        $version = PanelVersion::query()->where('active', true)->findOrFail($data['panel_version_id']);
        $operation = $service->upgrade($deployment, $version->image_reference);
        $audit->record('deployment.upgrade_requested', $deployment, ['target_version' => $version->version, 'correlation_id' => $operation->correlation_id]);

        return back()->with('success', 'Mise à niveau demandée.');
    }

    public function softDelete(Request $request, Deployment $deployment, DeploymentLifecycleService $service, AuditService $audit)
    {
        $this->authorize('update', $deployment);
        abort_unless($request->user()->can('manage-infrastructure'), 403);
        abort_if($deployment->hasActiveOperation(), 409, 'Une opération est déjà en cours.');
        $operation = $service->softDelete($deployment);
        $deployment->project->update(['status' => ProjectStatus::Suspended, 'suspended_at' => now()]);
        $audit->record('deployment.soft_delete_requested', $deployment, ['correlation_id' => $operation->correlation_id]);

        return back()->with('success', 'Suspension technique demandée. Les données sont conservées.');
    }

    public function purge(Request $request, Deployment $deployment, DeploymentPurgeService $service, AuditService $audit)
    {
        $this->authorize('delete', $deployment);
        $data = $request->validate(['confirmation' => ['required', 'string', 'max:120']]);
        if ($data['confirmation'] !== $deployment->project->name) {
            throw ValidationException::withMessages(['confirmation' => 'Saisissez le nom exact du projet pour confirmer la suppression.']);
        }
        abort_if($deployment->hasActiveOperation(), 409, 'Une opération est déjà en cours.');
        $operation = $service->purge($deployment);
        $deployment->project->update(['status' => ProjectStatus::PendingDeletion]);
        $audit->record('deployment.purge_requested', $deployment, ['correlation_id' => $operation->correlation_id]);

        return redirect()->route('projects.show', $deployment->project->uuid)->with('success', 'La purge définitive a été demandée.');
    }
}
