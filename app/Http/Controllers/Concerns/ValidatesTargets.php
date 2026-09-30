<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Exists;

/**
 * Helpers de validation scopés au tenant.
 *
 * Une règle `exists:accounts,id` seule autorise un utilisateur à référencer la
 * ressource d'un autre : la vérification est faite plus loin dans la plupart
 * des contrôleurs, mais pas partout. Ces helpers rendent la contrainte
 * explicite dès la validation, ce qui est le seul endroit où l'on ne peut pas
 * l'oublier par accident.
 */
trait ValidatesTargets
{
    protected function userId(): string
    {
        /** @var Authenticatable&User $user */
        $user = $this->currentUser();

        return $user->id;
    }

    protected function currentUser(): User
    {
        return request()->user();
    }

    /**
     * La ressource cible doit appartenir à l'utilisateur courant.
     */
    protected function owned(string $table): Exists
    {
        return (new Exists($table, 'id'))->where('user_id', $this->userId());
    }

    /**
     * Une catégorie est acceptée si elle appartient à l'utilisateur ou si c'est
     * une catégorie système (partagée, non modifiable — voir CategoryController).
     */
    protected function ownedOrSystemCategory(): Exists
    {
        $userId = $this->userId();

        return (new Exists('categories', 'id'))->where(function ($query) use ($userId) {
            $query->where('user_id', $userId)->orWhere('is_system', true);
        });
    }

    /**
     * Charge une ressource en garantissant qu'elle appartient à l'utilisateur.
     *
     * Le global scope `BelongsToUser` fait déjà le travail en requête HTTP,
     * mais il est désactivé dès que `auth()->check()` est faux (job de queue,
     * commande Artisan) : on ne veut pas que la sécurité tienne à ce contexte.
     */
    protected function findOwned(string $modelClass, string $id): Model
    {
        return $modelClass::where('user_id', $this->userId())->findOrFail($id);
    }
}
