<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->string('agent_auth_mode', 20)->nullable()->after('endpoint')->index();
            $table->text('agent_token')->nullable()->after('agent_auth_mode');
            $table->timestamp('agent_token_rotated_at')->nullable()->after('agent_token');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->dropIndex(['agent_auth_mode']);
            $table->dropColumn(['agent_auth_mode', 'agent_token', 'agent_token_rotated_at']);
        });
    }
};
