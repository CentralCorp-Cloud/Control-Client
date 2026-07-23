<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->string('environment', 64)->nullable()->after('region')->index();
            $table->string('agent_protocol_version', 32)->nullable()->after('agent_version');
            $table->string('installer_version', 64)->nullable()->after('agent_protocol_version');
            $table->string('installation_step', 64)->nullable()->after('installer_version');
            $table->json('compatibility')->nullable()->after('installation_step');
            $table->timestamp('installed_at')->nullable()->after('last_error_at');
        });

        Schema::create('node_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('node_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('user_code_hash', 64)->nullable()->unique();
            $table->char('device_code_hash', 64)->nullable()->unique();
            $table->char('preauthorization_token_hash', 64)->nullable()->unique();
            $table->char('bootstrap_token_hash', 64)->nullable()->unique();
            $table->string('status', 32)->index();
            $table->string('mode', 16)->index();
            $table->string('hostname')->nullable();
            $table->string('os', 32)->nullable();
            $table->string('os_version', 32)->nullable();
            $table->string('architecture', 16)->nullable();
            $table->unsignedBigInteger('memory_bytes')->nullable();
            $table->unsignedBigInteger('disk_bytes')->nullable();
            $table->json('ip_addresses')->nullable();
            $table->json('capabilities')->nullable();
            $table->string('installer_version', 64)->nullable();
            $table->string('local_nonce', 128)->nullable();
            $table->string('requested_agent_version', 64)->nullable();
            $table->string('agent_channel', 16)->default('stable');
            $table->string('chosen_name')->nullable();
            $table->string('region', 80)->nullable()->index();
            $table->string('environment', 64)->nullable()->index();
            $table->string('agent_fqdn')->nullable();
            $table->string('agent_endpoint')->nullable();
            $table->string('published_address')->nullable();
            $table->json('allowed_source_cidrs')->nullable();
            $table->json('allowed_client_sans')->nullable();
            $table->boolean('initial_maintenance')->default(false);
            $table->unsignedInteger('maximum_deployments')->nullable();
            $table->string('step', 32)->default('preflight');
            $table->unsignedTinyInteger('percentage')->default(0);
            $table->string('public_message', 500)->nullable();
            $table->string('error_code', 64)->nullable()->index();
            $table->text('sanitized_error')->nullable();
            $table->string('correlation_id', 64)->index();
            $table->unsignedSmallInteger('poll_interval')->default(5);
            $table->unsignedSmallInteger('failed_claim_attempts')->default(0);
            $table->char('csr_hash', 64)->nullable();
            $table->longText('issued_certificate')->nullable();
            $table->longText('issued_chain')->nullable();
            $table->string('certificate_serial')->nullable();
            $table->json('completion_report')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('bootstrap_expires_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamp('bootstrap_token_delivered_at')->nullable();
            $table->timestamp('certificate_issued_at')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });

        Schema::create('node_enrollment_idempotency', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('node_enrollment_id')->constrained()->cascadeOnDelete();
            $table->uuid('key');
            $table->string('operation', 32);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');
            $table->timestamps();
            $table->unique(['node_enrollment_id', 'key', 'operation'], 'enrollment_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_enrollment_idempotency');
        Schema::dropIfExists('node_enrollments');
        Schema::table('nodes', function (Blueprint $table): void {
            $table->dropColumn(['environment', 'agent_protocol_version', 'installer_version', 'installation_step', 'compatibility', 'installed_at']);
        });
    }
};
