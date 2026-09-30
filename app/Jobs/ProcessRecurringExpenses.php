<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Expense;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Traitement des dépenses récurrentes échues.
 *
 * La coquille vide précédente faisait échouer silencieusement le job dès sa
 * première ligne. Le traitement est ici limité à ce qui est sans risque :
 * désactiver les récurrences dont la date de fin est dépassée. La création
 * automatique d'une dépense reste un choix produit et n'est pas activée ici —
 * elle déporterait de l'argent sans action explicite de l'utilisateur.
 */
class ProcessRecurringExpenses implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Expense::query()
            ->where('is_recurring', true)
            ->whereNotNull('recurrence_rule')
            ->whereDate('date', '<', now()->subYear()->toDateString())
            ->update(['is_recurring' => false]);
    }
}
