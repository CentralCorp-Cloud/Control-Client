<?php

use App\Services\DomainNameService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('domain_mode', 20)->default('CENTRALCLOUD')->after('billing_type')->index();
            $table->string('canonical_hostname')->nullable()->after('domain_mode')->unique();
            $table->string('custom_hostname')->nullable()->after('canonical_hostname')->unique();
            $table->timestamp('domain_verified_at')->nullable()->after('custom_hostname');
            $table->timestamp('domain_last_checked_at')->nullable()->after('domain_verified_at');
            $table->string('domain_check_error', 500)->nullable()->after('domain_last_checked_at');
        });
        Schema::table('nodes', function (Blueprint $table) {
            $table->json('capabilities')->nullable()->after('agent_version');
        });

        DB::table('projects')->orderBy('id')->eachById(function (object $project): void {
            $hostname = DB::table('deployments')->where('project_id', $project->id)->value('hostname')
                ?: DomainNameService::opaqueCentralHostname((string) $project->uuid);
            DB::table('projects')->where('id', $project->id)->update(['canonical_hostname' => strtolower($hostname)]);
        });
    }

    public function down(): void
    {
        Schema::table('nodes', fn (Blueprint $table) => $table->dropColumn('capabilities'));
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['canonical_hostname']);
            $table->dropUnique(['custom_hostname']);
            $table->dropColumn(['domain_mode', 'canonical_hostname', 'custom_hostname', 'domain_verified_at', 'domain_last_checked_at', 'domain_check_error']);
        });
    }
};
