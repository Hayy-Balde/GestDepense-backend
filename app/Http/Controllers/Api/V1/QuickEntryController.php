<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\DefinesEntryRules;
use App\Http\Controllers\Controller;
use App\Services\ExpenseService;
use App\Services\IncomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Saisie rapide : enregistrement de plusieurs écritures en un seul appel.
 *
 * Le frontend ne peut pas se contenter d'appeler `POST /expenses` puis
 * `POST /incomes` en boucle : une coupure réseau au milieu laisserait une
 * saisie à moitié enregistrée, sans aucun moyen de savoir lesquelles. Ici la
 * validation est faite ligne par ligne avant toute écriture, et l'écriture
 * elle-même est atomique.
 */
class QuickEntryController extends Controller
{
    use DefinesEntryRules;

    /**
     * Au-delà, la requête devient inutilement longue et le verrou de
     * transaction bloque les autres écritures de l'utilisateur.
     */
    private const MAX_ENTRIES = 200;

    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly IncomeService $incomeService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'entries' => ['required', 'array', 'min:1', 'max:'.self::MAX_ENTRIES],
            'entries.*' => ['required', 'array'],
            'entries.*.type' => ['required', 'string', Rule::in(['expense', 'income'])],
        ]);

        $entries = $request->input('entries');
        $userId = $request->user()->id;

        /** @var array<string, array<int, string>> $errors */
        $errors = [];
        $prepared = ['expense' => [], 'income' => []];

        // Validation ligne par ligne : l'utilisateur voit ainsi *quelles* lignes
        // sont refusées, et rien n'est écrit tant qu'une seule ligne est fausse.
        foreach ($entries as $index => $entry) {
            $isExpense = $entry['type'] === 'expense';

            $validator = Validator::make(
                $entry,
                $isExpense ? $this->expenseRules(false) : $this->incomeRules(false),
                [],
                $this->entryAttributeNames()
            );

            if ($validator->fails()) {
                foreach ($validator->errors()->toArray() as $field => $messages) {
                    foreach ($messages as $message) {
                        $errors["entries.{$index}.{$field}"][] = $message;
                    }
                }

                continue;
            }

            // `validated()` ne renvoie que les champs listés dans les règles :
            // `type` et tout champ surnuméraire sont donc écartés d'office.
            $data = $validator->validated();
            $data['user_id'] = $userId;

            $prepared[$isExpense ? 'expense' : 'income'][] = $data;
        }

        if ($errors !== []) {
            return response()->json([
                'message' => 'Aucune écriture enregistrée : '.count($errors).' ligne(s) à corriger.',
                'errors' => $errors,
            ], 422);
        }

        $created = DB::transaction(function () use ($prepared): array {
            $expenses = array_map(
                fn (array $data) => $this->expenseService->createExpense($data),
                $prepared['expense']
            );

            $incomes = array_map(
                fn (array $data) => $this->incomeService->createIncome($data),
                $prepared['income']
            );

            return [
                'expenses' => count($expenses),
                'incomes' => count($incomes),
                'total' => count($expenses) + count($incomes),
            ];
        });

        return response()->json([
            'message' => $this->summary($created),
            'created' => $created,
        ], 201);
    }

    /**
     * @param  array{expenses: int, incomes: int, total: int}  $created
     */
    private function summary(array $created): string
    {
        $parts = [];

        if ($created['expenses'] > 0) {
            $parts[] = $created['expenses'].' dépense(s)';
        }

        if ($created['incomes'] > 0) {
            $parts[] = $created['incomes'].' revenu(s)';
        }

        return $created['total'].' écriture(s) enregistrée(s) : '.implode(', ', $parts).'.';
    }
}
