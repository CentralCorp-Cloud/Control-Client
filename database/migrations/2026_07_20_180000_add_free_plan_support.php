<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('is_free')->default(false)->after('active')->index();
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->string('billing_type', 16)->default('STRIPE')->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('billing_type'));
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('is_free'));
    }
};
