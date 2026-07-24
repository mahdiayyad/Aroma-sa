<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'gateway'         => $this->gateway,
            'method'          => $this->method,
            'status'          => $this->status,
            'transaction_id'  => $this->transaction_id,
            'reference_number'=> $this->reference_number,
            'amount'          => (float) $this->amount,
            'currency'        => $this->currency,
            'refunded_amount' => (float) $this->refunded_amount,
            'refunded_at'     => $this->refunded_at,
            'created_at'      => $this->created_at,
        ];
    }
}
