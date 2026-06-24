<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource {
    public function toArray($request) {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "balance" => $this->balance,
            "currency_code" => $this->currency_code,
        ];
    }
}