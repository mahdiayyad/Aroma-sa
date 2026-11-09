<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Optional starter content, run by hand and safe to re-run:
 *   php artisan db:seed --class=StoreContentSeeder
 *
 * Adds abaya sub-categories (empty ones stay hidden on the storefront until
 * products are assigned) and three blog articles as unpublished drafts. The
 * articles only restate the site's own Fit and Care guides.
 */
class StoreContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->subCategories();
        $this->articles();
    }

    private function subCategories(): void
    {
        $parent = Category::whereIn('slug', ['abaya', 'abayas'])->first();

        if (! $parent) {
            $this->command->warn('No abaya category found, so no sub-categories were added.');

            return;
        }

        $subCategories = [
            'abaya-everyday'  => ['ar' => 'عبايات يومية', 'en' => 'Everyday Abayas'],
            'abaya-formal'    => ['ar' => 'عبايات رسمية', 'en' => 'Formal Abayas'],
            'abaya-occasions' => ['ar' => 'عبايات المناسبات', 'en' => 'Occasion Abayas'],
        ];

        $sort = 0;

        foreach ($subCategories as $slug => $name) {
            Category::firstOrCreate(['slug' => $slug], [
                'parent_id'  => $parent->id,
                'name'       => $name,
                'sort_order' => ++$sort,
                'is_active'  => true,
            ]);
        }
    }

    private function articles(): void
    {
        foreach ($this->drafts() as $slug => $article) {
            BlogPost::firstOrCreate(['slug' => $slug], $article + ['is_published' => false]);
        }
    }

    /** @return array<string,array<string,array<string,string>>> */
    private function drafts(): array
    {
        return [
            'choosing-your-abaya-cut' => [
                'title' => [
                    'ar' => 'كيف تختارين قصة العباية المناسبة لكِ',
                    'en' => 'How to choose the right abaya cut',
                ],
                'excerpt' => [
                    'ar' => 'اختيار القصة ليس إخفاءً لشيء، بل اكتشاف لما يشبهك: حسب يومك وحركتك ومناسباتك.',
                    'en' => "Choosing a cut isn't about hiding anything. It's about finding what feels like you, based on your day, your movement and your occasions.",
                ],
                'body' => [
                    'ar' => <<<'TEXT'
كل قوام يستحق أن يشعر صاحبه بالجمال والراحة معاً. اختيار القصة ليس إخفاءً لشيء، بل اكتشاف للقصة التي تشبهك أكثر، حسب يومك وحركتك ومناسباتك.

## راحة الحركة طوال اليوم

القصة الكيمونو المفتوحة من الأمام، بأقمشة خفيفة كالشيفون أو الكتان، تسمح بحرية حركة كاملة وتُلبس فوق أي إطلالة بسهولة.

## إطلالة أنيقة ومهيّبة

القصة المغلقة أو الملفوفة مع تحديد عند الخصر أو حزام مثالية للعمل والمناسبات اليومية الرسمية.

## قصيرة القامة وتبحثين عن إطالة

اختاري الخطوط الرأسية والقصات المستقيمة، مع طول مضبوط بدقة. يساعدك دليل المقاسات على تحديد الطول الأنسب لكِ.

## مناسبات خاصة

الأقمشة الفاخرة كالحرير أو المخمل، مع تطريز أو لمسات مزخرفة، تمنحك إطلالة لافتة في الأعراس والاحتفالات.
TEXT,
                    'en' => <<<'TEXT'
Every body deserves to feel beautiful and comfortable at once. Choosing a cut isn't about hiding anything. It's about discovering the one that feels most like you, based on your day, your movement and your occasions.

## All-day ease of movement

An open-front kimono cut in a lighter fabric like chiffon or linen gives you full freedom to move, and it's easy to layer over any outfit.

## Structured and polished

A closed or wrap-style cut with definition at the waist, or a belt, is well suited to work and formal daily occasions.

## Petite frame, want elongation

Choose clean vertical lines and a straighter cut, with a precisely measured length. Our Size Guide will help you find the length that's right for you.

## Special occasions

Richer fabrics like silk or velvet, with embroidery or embellished details, make a look that stands out at weddings and celebrations.
TEXT,
                ],
            ],

            'abaya-fabrics-guide' => [
                'title' => [
                    'ar' => 'دليلك إلى أقمشة العبايات',
                    'en' => 'A guide to abaya fabrics',
                ],
                'excerpt' => [
                    'ar' => 'من الشيفون الخفيف إلى المخمل الدافئ: لكل قماش يومه ومناسبته.',
                    'en' => 'From light chiffon to warm velvet, every fabric has its day and its occasion.',
                ],
                'body' => [
                    'ar' => <<<'TEXT'
يغيّر القماش سقوط العباية وإحساسها والمناسبة التي تليق بها. هذا دليل سريع لأشهر الأقمشة.

## الشيفون

خفيف وشفاف بحركة انسيابية، يناسب الإطلالات اليومية العصرية.

## الكريب

كثافة متوسطة وسقوط أنيق دون شفافية، وهو الأكثر تنوعاً للاستخدام اليومي.

## الحرير

ملمس فاخر ولمعان راقٍ للمناسبات والاحتفالات.

## المخمل

دافئ وفخم بملمس كثيف، لموسم الشتاء والمناسبات المسائية.

## الكتان

خفيف ومسامي يناسب الأجواء الحارة والراحة اليومية.

## النيدا

قماش كثيف يحافظ على شكله، للقصات الهيكلية والرسمية.
TEXT,
                    'en' => <<<'TEXT'
Fabric changes how an abaya falls, how it feels, and where it belongs. Here's a quick guide to the most popular ones.

## Chiffon

Light and airy with a flowing drape, for everyday, breezy looks.

## Crepe

Medium weight with a clean, opaque fall. It's the most versatile choice for daily wear.

## Silk

A luxurious texture with a soft sheen, for occasions and celebrations.

## Velvet

Warm and rich with body, for winter and evening occasions.

## Linen

Light and breathable, for everyday comfort in warmer weather.

## Nida

A denser, structured fabric that holds its shape, for tailored, formal cuts.
TEXT,
                ],
            ],

            'caring-for-your-abaya' => [
                'title' => [
                    'ar' => 'كيف تعتنين بعبايتك',
                    'en' => 'How to care for your abaya',
                ],
                'excerpt' => [
                    'ar' => 'الغسيل والتجفيف والكي والتخزين بالطريقة الصحيحة.',
                    'en' => 'How to wash, dry, iron and store it the right way.',
                ],
                'body' => [
                    'ar' => <<<'TEXT'
القليل من العناية يحافظ على شكل عبايتك ولونها لوقت أطول.

## الغسيل

اغسليها يدوياً بماء بارد ومنظف لطيف خالٍ من المبيّض. عند استخدام الغسالة، اختاري دورة لطيفة وضعي القطعة داخل كيس غسيل شبكي. اغسلي القطع المطرزة أو المرصّعة بشكل منفصل، أو فضّلي التنظيف الجاف لها، وتجنبي نقعها لفترة طويلة.

## التجفيف

جففيها مفرودة أو على شماعة عريضة للحفاظ على شكل الكتفين، وابعديها عن أشعة الشمس المباشرة لتفادي بهتان الألوان الداكنة. اتركيها تجف تماماً قبل ارتدائها أو تخزينها، ولا تعصريها أو تلويها.

## الكي والبخار

البخار أكثر لطفاً من الكي المباشر، خاصة فوق التطريز أو الترتر أو الدانتيل. إذا استخدمتِ المكواة، اضبطيها على حرارة منخفضة إلى متوسطة واكوي من الداخل، وضعي قطعة قماش رقيقة بين المكواة وأي منطقة مزخرفة.

## التخزين

علّقيها على شماعة عريضة ومبطنة للحفاظ على خط الكتفين، وخزّنيها داخل كيس تخزين قابل للتهوية بعيداً عن الشمس والرطوبة. افصلي القطع المرصّعة عن غيرها لتجنّب تشابك الترتر بالأقمشة الأخرى.
TEXT,
                    'en' => <<<'TEXT'
A little care keeps your abaya's shape and colour for longer.

## Wash

Hand wash in cold water with a mild, bleach-free detergent. If machine washing, use a gentle cycle inside a mesh garment bag. Wash embellished, embroidered or beaded pieces separately, or prefer professional dry cleaning, and avoid long soaking.

## Dry

Dry flat or on a wide hanger to keep the shoulder shape, and keep it out of direct sunlight, which fades darker fabrics. Let the piece air dry completely before wearing or storing, and never wring or twist it.

## Iron and steam

Steam is gentler than direct heat, especially over embroidery, sequins or lace. If ironing, use a low-medium setting, iron from the inside out, and place a thin cloth between the iron and any embellished area.

## Store

Hang it on a wide, padded hanger to preserve the shoulder line, and store it in a breathable garment bag away from direct sunlight and humidity. Keep embellished pieces separate so sequins or beading don't snag other fabrics.
TEXT,
                ],
            ],
        ];
    }
}
