<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Renouvellement des abonnements dont l'échéance est atteinte.
 *
 * Le fichier était une coquille vide : aucun job n'était jamais exécuté et
 * aucune commande ne le dispatchait. Le déclenchement réel est Branché sur
 * `SubscriptionService`, qui porte déjà le calcul de la prochaine échéance.
 */
class ProcessSubscriptionPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly ?string $today = null,
    ) {}

    public function handle(SubscriptionService $subscriptions): void
    {
        $today = $this->today !== null
            ? \Carbon\Carbon::parse($this->today)->toDateString()
            : now()->toDateString();

        Subscription::query()
            ->where('is_active', true)
            ->whereDate('next_billing_date', '<=', $today)
            ->chunkById(200, function ($due) use ($subscriptions) {
                foreach ($due as $subscription) {
                    // La date est avancée même si le débit échoue : sans cela,
                    // un abonnement dont la cible est supprimée serait
                    // retenté indéfiniment à chaque exécution du planificateur.
                    $subscriptions->renewSubscription($subscription);
                }
            });
    }
}
