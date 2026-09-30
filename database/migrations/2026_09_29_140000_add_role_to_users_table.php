<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Introduit une notion de rôle.
 *
 * Les référentiels globaux (types de compte, modes de paiement, cycles de
 * facturation, types de dette, devises) sont partagés par tous les utilisateurs
 * : n'importe qui pouvait donc les modifier et impacter la comptabilité des
 * autres. Le rôle `admin` restreint ces écritures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
