<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Order;
use App\Services\LocationLookupService;
use Illuminate\Console\Command;

/**
 * Read-only: lists saved addresses and orders holding the demo National
 * Address resolution (the canned "Al Olaya, Riyadh" result) instead of a
 * customer's real address, so they can be corrected. Changes nothing.
 */
class NationalAddressDemoReportCommand extends Command
{
    protected $signature = 'national-address:demo-data';

    protected $description = 'List saved addresses and orders that contain demo National Address data';

    private const SIGNATURE_LIKE = '%Al Olaya, Riyadh, Riyadh Region (%';

    public function handle(): int
    {
        $addresses = Address::query()
            ->with('user:id,name,email')
            ->where('formatted_address', 'like', self::SIGNATURE_LIKE)
            ->orderBy('id')
            ->get()
            ->filter(function (Address $address): bool {
                return LocationLookupService::isDemoAddress($address->toArray());
            });

        $orders = Order::query()
            ->where(function ($q) {
                foreach (['shipping_address', 'billing_address'] as $column) {
                    $q->orWhere($column, 'like', self::SIGNATURE_LIKE)
                        ->orWhere($column, 'like', '%"is_stub":true%');
                }
            })
            ->orderBy('id')
            ->get()
            ->filter(function (Order $order): bool {
                return LocationLookupService::isDemoAddress($order->shipping_address)
                    || LocationLookupService::isDemoAddress($order->billing_address);
            });

        $this->info("Saved addresses with demo data: {$addresses->count()}");
        if ($addresses->isNotEmpty()) {
            $this->table(['Address ID', 'Customer', 'Email', 'Code entered', 'Label'], $addresses->map(function (Address $a): array {
                return [$a->id, optional($a->user)->name, optional($a->user)->email, $a->location_code, $a->label];
            })->all());
        }

        $this->info("Orders with demo address data: {$orders->count()}");
        if ($orders->isNotEmpty()) {
            $this->table(['Order', 'Status', 'Customer', 'Phone', 'Code entered', 'Created'], $orders->map(function (Order $o): array {
                $address = LocationLookupService::isDemoAddress($o->shipping_address) ? $o->shipping_address : $o->billing_address;

                return [$o->order_number, $o->status, $o->customer_name, $o->customer_phone, $address['location_code'] ?? '', (string) $o->created_at];
            })->all());
            $this->line('Confirm each customer\'s real address and correct it before shipping. Aramex shipping refuses these orders.');
        }

        return self::SUCCESS;
    }
}
