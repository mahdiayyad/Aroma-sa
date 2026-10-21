<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AramexShipment;
use App\Services\AramexFulfilmentService;
use App\Services\AramexService;
use Illuminate\Console\Command;

/**
 * Pull tracking for every open Aramex shipment. Backstop for the push
 * webhook (which Aramex enables per account); both feed the same idempotent
 * AramexFulfilmentService::applyTrackingEvents(), so running both is safe.
 */
class AramexTrackShipmentsCommand extends Command
{
    protected $signature = 'aramex:track {--awb=* : Only these waybill numbers}';

    protected $description = 'Fetch Aramex tracking updates for open shipments';

    /** Aramex's TrackShipments limit per request. */
    private const BATCH = 50;

    public function handle(AramexService $aramex, AramexFulfilmentService $fulfilment): int
    {
        if (! $aramex->isConfigured()) {
            $this->warn('Aramex is not configured; skipping.');

            return self::SUCCESS;
        }

        $query = AramexShipment::query()
            ->whereNotNull('awb_number')
            ->whereNotIn('shipping_status', AramexShipment::TERMINAL_STATUSES);

        if ($awbs = (array) $this->option('awb')) {
            $query->whereIn('awb_number', $awbs);
        }

        $checked = 0;
        $changed = 0;
        $failedBatches = 0;

        $query->orderBy('id')->chunkById(self::BATCH, function ($shipments) use ($aramex, $fulfilment, &$checked, &$changed, &$failedBatches) {
            $result = $aramex->trackShipments($shipments->pluck('awb_number')->all());

            if (! $result['success']) {
                $failedBatches++;
                $this->error('Tracking request failed: '.implode(' ', $result['errors']));

                return;
            }

            foreach ($shipments as $shipment) {
                $checked++;
                $events = array_map(function (array $update): array {
                    return [
                        'code' => $update['UpdateCode'] ?? null,
                        'description' => $update['UpdateDescription'] ?? null,
                        'occurred_at' => $update['UpdateDateTime'] ?? null,
                        'location' => $update['UpdateLocation'] ?? null,
                        'comment' => $update['Comments'] ?? null,
                    ];
                }, $result['results'][$shipment->awb_number] ?? []);

                if ($events !== [] && $fulfilment->applyTrackingEvents($shipment, $events, 'tracking')) {
                    $changed++;
                }
            }
        });

        $this->info("Checked {$checked} shipment(s); {$changed} changed status.");

        return $failedBatches > 0 ? self::FAILURE : self::SUCCESS;
    }
}
