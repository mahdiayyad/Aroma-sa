<?php

namespace Tests\Unit;

use App\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class HasTranslationsTest extends TestCase
{
    private function makeModel(): Model
    {
        return new class extends Model {
            use HasTranslations;

            protected array $translatable = ['name'];
            protected $guarded = [];
        };
    }

    public function test_it_returns_the_value_for_the_active_locale(): void
    {
        $this->app->setLocale('ar');

        $model = $this->makeModel();
        $model->name = ['ar' => 'عطر الورد', 'en' => 'Rose Perfume'];

        $this->assertSame('عطر الورد', $model->name);
    }

    public function test_it_falls_back_to_the_fallback_locale_when_missing(): void
    {
        config(['app.fallback_locale' => 'en']);
        $this->app->setLocale('ar');

        $model = $this->makeModel();
        $model->name = ['en' => 'Rose Perfume']; // no Arabic value

        $this->assertSame('Rose Perfume', $model->name);
    }

    public function test_non_translatable_attributes_are_untouched(): void
    {
        $model = $this->makeModel();
        $model->sku = 'AR-001';

        $this->assertSame('AR-001', $model->sku);
    }
}
