<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomainVerificationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectLifecycleController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WebCronController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
Route::match(['GET', 'POST'], '/_system/webcron', WebCronController::class)->middleware('throttle:6,1')->name('system.webcron');

Route::middleware(['auth', 'verified', 'control-plane.available'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::redirect('/home', '/dashboard');
    Route::resource('projects', ProjectController::class)->parameters(['projects' => 'project:uuid'])->only(['index', 'create', 'store', 'show']);
    Route::post('/projects/{project:uuid}/domain/verify', DomainVerificationController::class)->middleware('throttle:6,1')->name('projects.domain.verify');
    Route::post('/deployments/{deployment:uuid}/{action}', [ProjectLifecycleController::class, 'action'])->whereIn('action', ['start', 'stop', 'restart'])->middleware('throttle:10,1')->name('deployments.action');
    Route::post('/deployments/{deployment:uuid}/admin-reset', [ProjectLifecycleController::class, 'adminReset'])->middleware(['password.confirm', 'throttle:3,10'])->name('deployments.admin-reset');
    Route::get('/deployments/{deployment:uuid}/logs', [ProjectLifecycleController::class, 'logPage'])->name('deployments.logs');
    Route::get('/deployments/{deployment:uuid}/logs/data', [ProjectLifecycleController::class, 'logs'])->middleware('throttle:30,1')->name('deployments.logs.data');
    Route::delete('/deployments/{deployment:uuid}/purge', [ProjectLifecycleController::class, 'purge'])->middleware(['password.confirm', 'throttle:deployment-purge'])->name('deployments.purge');
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');
    Route::post('/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('/account/security', [SecurityController::class, 'index'])->name('security.index');
    Route::delete('/account/sessions/{session}', [SecurityController::class, 'destroySession'])->middleware('password.confirm')->name('security.sessions.destroy');
    Route::view('/account', 'account.profile')->name('account.profile');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'admin', 'admin.2fa'])->group(function (): void {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('/users', [Admin\UserController::class, 'index'])->middleware('can:manage-users')->name('users.index');
    Route::get('/users/{user:uuid}', [Admin\UserController::class, 'show'])->middleware('can:manage-users')->name('users.show');
    Route::patch('/users/{user:uuid}', [Admin\UserController::class, 'update'])->middleware(['can:manage-users', 'password.confirm'])->name('users.update');
    Route::post('/users/{user:uuid}/verification', [Admin\UserController::class, 'resendVerification'])->middleware(['can:manage-users', 'throttle:3,10'])->name('users.verification');
    Route::delete('/users/{user:uuid}/sessions', [Admin\UserController::class, 'destroySessions'])->middleware(['can:manage-users', 'password.confirm'])->name('users.sessions.destroy');
    Route::get('/projects', [Admin\ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{project:uuid}', [Admin\ProjectController::class, 'show'])->name('projects.show');
    Route::post('/deployments/{deployment:uuid}/upgrade', [ProjectLifecycleController::class, 'upgrade'])->middleware(['can:manage-infrastructure', 'password.confirm', 'throttle:5,10'])->name('deployments.upgrade');
    Route::delete('/deployments/{deployment:uuid}/soft-delete', [ProjectLifecycleController::class, 'softDelete'])->middleware(['can:manage-infrastructure', 'password.confirm', 'throttle:5,10'])->name('deployments.soft-delete');
    Route::resource('nodes', Admin\NodeController::class)->parameters(['nodes' => 'node:uuid'])->except(['edit'])->middleware('can:manage-infrastructure');
    Route::get('/operations', [Admin\OperationController::class, 'index'])->name('operations.index');
    Route::get('/operations/{operation:uuid}', [Admin\OperationController::class, 'show'])->name('operations.show');
    Route::resource('plans', Admin\PlanController::class)->except(['show'])->middleware('can:manage-billing');
    Route::resource('panel-versions', Admin\PanelVersionController::class)->except(['show'])->middleware('can:manage-infrastructure');
    Route::get('/billing', [Admin\BillingController::class, 'index'])->middleware('can:manage-billing')->name('billing.index');
    Route::get('/incidents', [Admin\IncidentController::class, 'index'])->name('incidents.index');
    Route::patch('/incidents/{incident:uuid}', [Admin\IncidentController::class, 'update'])->name('incidents.update');
    Route::get('/audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/settings', [Admin\SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [Admin\SettingController::class, 'update'])->name('settings.update');
});
