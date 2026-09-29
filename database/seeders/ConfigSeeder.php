<?php

namespace Database\Seeders;

use App\Models\AccountType;
use App\Models\BillingCycle;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DebtType;
use App\Models\PaymentMethod;
use App\Models\SubCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ConfigSeeder extends Seeder
{
    public function run(): void
    {
        $this->accountTypes();
        $this->paymentMethods();
        $this->billingCycles();
        $this->debtTypes();
        $this->currencies();
        $this->systemCategories();
    }

    protected function accountTypes(): void
    {
        $types = [
            ['value' => 'bank', 'label' => 'Compte Bancaire', 'icon' => 'building-2', 'color' => '#4F46E5'],
            ['value' => 'cash', 'label' => 'Espèces', 'icon' => 'banknote', 'color' => '#16A34A'],
            ['value' => 'mobile_money', 'label' => 'Mobile Money', 'icon' => 'smartphone', 'color' => '#F59E0B'],
            ['value' => 'wallet', 'label' => 'Portefeuille', 'icon' => 'wallet', 'color' => '#8B5CF6'],
            ['value' => 'crypto', 'label' => 'Crypto', 'icon' => 'bitcoin', 'color' => '#F97316'],
            ['value' => 'savings', 'label' => 'Épargne', 'icon' => 'piggy-bank', 'color' => '#EC4899'],
            ['value' => 'credit_card', 'label' => 'Carte de Crédit', 'icon' => 'credit-card', 'color' => '#0EA5E9'],
        ];

        foreach ($types as $index => $type) {
            AccountType::updateOrCreate(['value' => $type['value']], $type + ['sort_order' => $index]);
        }
    }

    protected function paymentMethods(): void
    {
        $methods = [
            ['value' => 'cash', 'label' => 'Espèces', 'icon' => 'banknote'],
            ['value' => 'bank_transfer', 'label' => 'Virement Bancaire', 'icon' => 'building-2'],
            ['value' => 'mobile_money', 'label' => 'Mobile Money', 'icon' => 'smartphone'],
            ['value' => 'credit_card', 'label' => 'Carte de Crédit', 'icon' => 'credit-card'],
            ['value' => 'debit_card', 'label' => 'Carte de Débit', 'icon' => 'credit-card'],
            ['value' => 'check', 'label' => 'Chèque', 'icon' => 'receipt'],
            ['value' => 'other', 'label' => 'Autre', 'icon' => 'more-horizontal'],
        ];

        foreach ($methods as $index => $method) {
            PaymentMethod::updateOrCreate(['value' => $method['value']], $method + ['sort_order' => $index]);
        }
    }

    protected function billingCycles(): void
    {
        $cycles = [
            ['value' => 'weekly', 'label' => 'Hebdomadaire'],
            ['value' => 'monthly', 'label' => 'Mensuel'],
            ['value' => 'quarterly', 'label' => 'Trimestriel'],
            ['value' => 'yearly', 'label' => 'Annuel'],
        ];

        foreach ($cycles as $index => $cycle) {
            BillingCycle::updateOrCreate(['value' => $cycle['value']], $cycle + ['sort_order' => $index]);
        }
    }

    protected function debtTypes(): void
    {
        $types = [
            ['value' => 'lent', 'label' => 'Prêté'],
            ['value' => 'borrowed', 'label' => 'Emprunté'],
        ];

        foreach ($types as $index => $type) {
            DebtType::updateOrCreate(['value' => $type['value']], $type + ['sort_order' => $index]);
        }
    }

    protected function currencies(): void
    {
        $currencies = [
            ['code' => 'GNF', 'name' => 'Franc Guinéen', 'symbol' => 'FG', 'decimal_places' => 0],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
            ['code' => 'USD', 'name' => 'Dollar US', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'GBP', 'name' => 'Livre Sterling', 'symbol' => '£', 'decimal_places' => 2],
            ['code' => 'XOF', 'name' => 'Franc CFA', 'symbol' => 'FCFA', 'decimal_places' => 0],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(['code' => $currency['code']], $currency);
        }
    }

    protected function systemCategories(): void
    {
        $expenseCategories = [
            'alimentation' => ['name' => 'Alimentation', 'icon' => 'utensils', 'color' => '#F97316'],
            'transport' => ['name' => 'Transport', 'icon' => 'car', 'color' => '#3B82F6'],
            'logement' => ['name' => 'Logement', 'icon' => 'home', 'color' => '#8B5CF6'],
            'sante' => ['name' => 'Santé', 'icon' => 'heart', 'color' => '#EF4444'],
            'education' => ['name' => 'Éducation', 'icon' => 'graduation-cap', 'color' => '#06B6D4'],
            'loisirs' => ['name' => 'Loisirs', 'icon' => 'gamepad-2', 'color' => '#EC4899'],
            'shopping' => ['name' => 'Shopping', 'icon' => 'shopping-bag', 'color' => '#A855F7'],
            'factures' => ['name' => 'Factures', 'icon' => 'receipt', 'color' => '#F59E0B'],
            'famille' => ['name' => 'Famille', 'icon' => 'users', 'color' => '#10B981'],
            'autre' => ['name' => 'Autre', 'icon' => 'more-horizontal', 'color' => '#6B7280'],
        ];

        foreach ($expenseCategories as $key => $category) {
            $existing = Category::where('is_system', true)->where('slug', $key)->first();
            if ($existing) {
                $existing->update($category);
                continue;
            }
            Category::create([
                'id' => (string) Str::uuid(),
                'name' => $category['name'],
                'slug' => $key,
                'type' => 'expense',
                'icon' => $category['icon'],
                'color' => $category['color'],
                'is_system' => true,
                'sort_order' => array_search($key, array_keys($expenseCategories)),
            ]);
        }

        $incomeCategories = [
            'salaire' => ['name' => 'Salaire', 'icon' => 'briefcase', 'color' => '#16A34A'],
            'business' => ['name' => 'Business', 'icon' => 'building-2', 'color' => '#4F46E5'],
            'freelance' => ['name' => 'Freelance', 'icon' => 'laptop', 'color' => '#0EA5E9'],
            'investissement' => ['name' => 'Investissement', 'icon' => 'trending-up', 'color' => '#8B5CF6'],
            'cadeau' => ['name' => 'Cadeau', 'icon' => 'gift', 'color' => '#EC4899'],
            'autre' => ['name' => 'Autre', 'icon' => 'more-horizontal', 'color' => '#6B7280'],
        ];

        foreach ($incomeCategories as $key => $category) {
            $existing = Category::where('is_system', true)->where('slug', $key)->first();
            if ($existing) {
                $existing->update($category);
                continue;
            }
            Category::create([
                'id' => (string) Str::uuid(),
                'name' => $category['name'],
                'slug' => $key,
                'type' => 'income',
                'icon' => $category['icon'],
                'color' => $category['color'],
                'is_system' => true,
                'sort_order' => array_search($key, array_keys($incomeCategories)),
            ]);
        }
    }
}
