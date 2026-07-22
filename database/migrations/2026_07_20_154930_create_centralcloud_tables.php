<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedBigInteger('price');
            $table->char('currency', 3)->default('EUR');
            $table->string('billing_interval', 16)->default('month');
            $table->string('stripe_product_id')->nullable()->unique();
            $table->string('stripe_price_id')->nullable()->unique();
            $table->unsignedBigInteger('memory_bytes');
            $table->decimal('cpu_limit', 8, 3);
            $table->string('storage_display_limit')->nullable();
            $table->unsignedInteger('maximum_projects')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('status', 40)->default('PENDING_PAYMENT')->index();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['owner_id', 'status']);
        });
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('agent_node_id')->unique();
            $table->string('name');
            $table->string('endpoint');
            $table->string('region')->nullable()->index();
            $table->string('status', 24)->default('OFFLINE')->index();
            $table->boolean('scheduling_enabled')->default(false)->index();
            $table->boolean('maintenance')->default(false)->index();
            $table->string('agent_version')->nullable();
            $table->unsignedInteger('cpu_count')->default(0);
            $table->unsignedBigInteger('memory_total_bytes')->default(0);
            $table->unsignedBigInteger('memory_available_bytes')->default(0);
            $table->unsignedBigInteger('disk_total_bytes')->default(0);
            $table->unsignedBigInteger('disk_available_bytes')->default(0);
            $table->unsignedInteger('deployment_count')->default(0);
            $table->unsignedInteger('active_deployment_count')->default(0);
            $table->string('last_health_status')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->timestamps();
        });
        Schema::create('panel_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->string('image_reference')->unique();
            $table->boolean('active')->default(true)->index();
            $table->boolean('recommended')->default(false)->index();
            $table->timestamps();
        });
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('panel_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hostname')->unique();
            $table->string('state', 40)->default('pending')->index();
            $table->string('desired_state', 40)->default('active')->index();
            $table->unsignedBigInteger('memory_bytes');
            $table->decimal('cpu_limit', 8, 3);
            $table->string('image_reference');
            $table->timestamp('provisioning_started_at')->nullable();
            $table->timestamp('deployed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->string('failure_code')->nullable();
            $table->text('failure_message_sanitized')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['node_id', 'state']);
        });
        Schema::create('agent_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('deployment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->uuid('agent_operation_id')->nullable()->unique();
            $table->string('type', 32)->index();
            $table->string('status', 24)->default('QUEUED')->index();
            $table->uuid('idempotency_key')->unique();
            $table->uuid('correlation_id')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('error_code')->nullable()->index();
            $table->text('error_message_sanitized')->nullable();
            $table->timestamp('last_polled_at')->nullable()->index();
            $table->timestamps();
            $table->index(['deployment_id', 'status']);
        });
        Schema::create('agent_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_operation_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('deployment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->nullable()->constrained()->cascadeOnDelete();
            $table->uuid('idempotency_key')->nullable()->index();
            $table->uuid('correlation_id')->nullable()->index();
            $table->string('method', 8);
            $table->string('path');
            $table->char('request_hash', 64)->index();
            $table->longText('encrypted_payload')->nullable();
            $table->longText('encrypted_headers')->nullable();
            $table->string('state', 24)->default('PENDING')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('provisioning_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('encrypted_bootstrap');
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('stripe_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('type')->index();
            $table->longText('payload');
            $table->string('status', 24)->default('RECEIVED')->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
        Schema::create('billing_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stripe_invoice_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('status', 32)->index();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('target_type')->nullable()->index();
            $table->string('target_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('fingerprint')->unique();
            $table->string('severity', 16)->index();
            $table->string('source_type')->index();
            $table->string('source_id')->nullable()->index();
            $table->text('message');
            $table->string('status', 16)->default('OPEN')->index();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->string('type', 16)->default('string');
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'incidents', 'audit_logs', 'billing_transactions', 'stripe_events', 'provisioning_requests', 'agent_requests', 'agent_operations', 'deployments', 'panel_versions', 'nodes', 'projects', 'plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
