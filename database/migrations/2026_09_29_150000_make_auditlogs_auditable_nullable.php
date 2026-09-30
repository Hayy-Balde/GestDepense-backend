<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rend le journal d'audit réellement utilisable.
 *
 * `auditable_id` / `auditable_type` étaient obligatoires, ce qui interdisait de
 * tracer les événements qui ne sont pas attachés à une ligne : connexion
 * réussie ou échouée, activation/désactivation de la 2FA, révocation d'une
 * session, refus d'accès. Or ce sont précisément les événements qui comptent
 * pour établir ce qui s'est passé sur un compte.
 *
 * `user_id` devient également indexable pour permettre la recherche par compte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditlogs', function (Blueprint $table) {
            $table->uuid('auditable_id')->nullable()->change();
            $table->string('auditable_type')->nullable()->change();
            $table->string('user_agent', 512)->nullable()->after('ip_address');
        });

        Schema::table('auditlogs', function (Blueprint $table) {
            $table->index(['auditable_type', 'auditable_id'], 'auditlogs_auditable_index');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::table('auditlogs', function (Blueprint $table) {
            $table->dropIndex('auditlogs_auditable_index');
            $table->dropIndex(['action']);
            $table->dropColumn('user_agent');
        });
    }
};
