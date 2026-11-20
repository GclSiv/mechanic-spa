<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('client_accepted_name', 120)->nullable()->after('approved_by_name');
            $table->timestamp('client_accepted_at')->nullable()->after('client_accepted_name');
            $table->string('client_accepted_ip', 45)->nullable()->after('client_accepted_at');
            $table->string('client_accepted_agent', 255)->nullable()->after('client_accepted_ip');
            $table->char('client_acceptance_hash', 64)->nullable()->after('client_accepted_agent');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn([
                'client_accepted_name',
                'client_accepted_at',
                'client_accepted_ip',
                'client_accepted_agent',
                'client_acceptance_hash',
            ]);
        });
    }
};
