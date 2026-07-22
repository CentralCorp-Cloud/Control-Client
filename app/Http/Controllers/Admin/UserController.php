<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::withCount('projects')->when($request->filled('q'), fn ($q) => $q->where(fn ($x) => $x->where('email', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')->orWhere('uuid', $request->q)))->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))->latest()->paginate(25)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $user->load(['projects.plan', 'projects.deployment']);

        return view('admin.users.show', compact('user'));
    }

    public function update(Request $request, User $user, AuditService $audit)
    {
        if ($request->filled('status')) {
            $data = $request->validate(['status' => ['required', 'in:ACTIVE,SUSPENDED']]);
            $user->update(['status' => $data['status'], 'suspended_at' => $data['status'] === 'SUSPENDED' ? now() : null]);
            if ($data['status'] === 'SUSPENDED') {
                \DB::table('sessions')->where('user_id', $user->id)->delete();
            }$audit->record('user.status_changed', $user, ['status' => $data['status']]);
        }if ($request->filled('role')) {
            $actor = $request->user();
            abort_unless($actor instanceof User && $actor->role === UserRole::SuperAdmin, 403);
            $data = $request->validate(['role' => ['required', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))]]);
            $user->update(['role' => $data['role']]);
            $audit->record('user.role_changed', $user, ['role' => $data['role']]);
        }

        return back()->with('success', 'Utilisateur mis à jour.');
    }

    public function resendVerification(User $user, AuditService $audit)
    {
        $user->sendEmailVerificationNotification();
        $audit->record('user.verification_resent', $user);

        return back()->with('success', 'Email de vérification renvoyé.');
    }

    public function destroySessions(User $user, AuditService $audit)
    {
        \DB::table('sessions')->where('user_id', $user->id)->delete();
        $audit->record('user.sessions_revoked', $user);

        return back()->with('success', 'Sessions révoquées.');
    }
}
