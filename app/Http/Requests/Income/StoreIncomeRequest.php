<?php
namespace App\Http\Requests\Income;
use Illuminate\Foundation\Http\FormRequest;

class StoreIncomeRequest extends FormRequest {
    public function authorize() { return true; }
    public function rules() {
        return [
            "amount" => "required|numeric|min:0.01",
            "title" => "required|string|max:255",
            "account_id" => "required|uuid|exists:accounts,id",
            "category_id" => "required|uuid|exists:categories,id",
            "date" => "required|date",
        ];
    }
}