<?php
namespace App\Http\Requests\Account;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest {
    public function authorize() { return true; }
    public function rules() {
        return [
            "name" => "required|string|max:255",
            "type" => "required|string",
            "balance" => "required|numeric",
            "currency_code" => "required|string|size:3",
        ];
    }
}