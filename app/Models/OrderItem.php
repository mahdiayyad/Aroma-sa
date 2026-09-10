<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'product_data' => 'array',
        'variant_data' => 'array',
        'options_snapshot' => 'array',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    /**
     * The variant label resolved for the active locale — the stored array is
     * { ar, en }, so a bare ['name'] read (an old bug) always came back blank.
     */
    public function variantLabel(): ?string
    {
        if (empty($this->variant_data)) {
            return null;
        }

        return $this->variant_data[app()->getLocale()] ?? reset($this->variant_data);
    }

    /**
     * Chosen customisation options as [label => value] pairs, resolved for
     * the active locale. Empty when the line carried none.
     *
     * @return array<string,string>
     */
    public function optionLines(): array
    {
        $lines = [];

        foreach ($this->optionRows() as $row) {
            if ($row['value'] !== '') {
                $lines[$row['label']] = $row['value'];
            }
        }

        return $lines;
    }

    /**
     * Chosen options as display rows resolved for the active locale, each with
     * its price adjustment. Views branch on price_delta: > 0 renders an
     * itemised add-on line (the dress), 0 renders a plain "Label: Value".
     *
     * @return array<int,array{key:string,label:string,value:string,price_delta:float}>
     */
    public function optionRows(): array
    {
        $locale = app()->getLocale();
        $rows = [];

        foreach ($this->options_snapshot ?? [] as $option) {
            $label = $option['label'][$locale] ?? ($option['label'] ? reset($option['label']) : ($option['key'] ?? ''));
            $value = $option['value_label'][$locale] ?? ($option['value_label'] ? reset($option['value_label']) : '');

            $rows[] = [
                'key'         => (string) ($option['key'] ?? ''),
                'label'       => (string) $label,
                'value'       => (string) $value,
                'price_delta' => (float) ($option['price_delta'] ?? 0),
            ];
        }

        return $rows;
    }

    /** Sum of the option add-ons frozen onto this line (e.g. the dress). */
    public function addonTotal(): float
    {
        return array_sum(array_map(
            static fn ($o) => (float) ($o['price_delta'] ?? 0),
            $this->options_snapshot ?? []
        ));
    }

    /** Per-unit price before option add-ons (unit_price folds them in). */
    public function baseUnitPrice(): float
    {
        return (float) $this->unit_price - $this->addonTotal();
    }

    public function baseUnitPriceLabel(): string
    {
        return \App\Support\Formatting\Money::format($this->baseUnitPrice());
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function priceLabel(): string
    {
        return \App\Support\Formatting\Money::format($this->unit_price);
    }

    public function totalLabel(): string
    {
        return \App\Support\Formatting\Money::format($this->line_total);
    }
}
