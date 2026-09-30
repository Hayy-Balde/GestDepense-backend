<?php

use App\Jobs\ProcessRecurringExpenses;
use App\Jobs\ProcessSubscriptionPayments;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/**
 * Plan des tâches.
 *
 * Le projet ne possédait aucun planificateur : les jobs de renouvellement
 * étaient donc présents en base de code mais jamais exécutés. Render n'exécute
 * un cron que pour les tâches déclarées ici.
 */
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Les abonnements dont l'échéance est passée sont renouvelés chaque jour.
// `withoutOverlapping` évite qu'une exécution longue ne se superpose à la
// suivante, ce qui débiterait deux fois le même abonnement.
Schedule::job(new ProcessSubscriptionPayments)
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new ProcessRecurringExpenses)
    ->dailyAt('06:15')
    ->withoutOverlapping()
    ->onOneServer();

// Purge des jetons expirés : la colonne `expires_at` est renseignée, mais
// personne ne supprimait les lignes, qui accumulaient indéfiniment.
Schedule::call(function () {
    \Laravel\Sanctum\Sanctum::pruneExpiredTokens();
})->hourly()->name('prune-expired-tokens');
