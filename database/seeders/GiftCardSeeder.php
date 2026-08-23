<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GiftCard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The greeting-card catalogue used by checkout's Gift Options step (see
 * GiftCard model). Source artwork lives in resources/seed-images/gift-cards
 * (git-tracked) and is copied onto the public disk here, since
 * storage/app/public itself is git-ignored — the same way any other admin
 * upload would be, per environment. Safe to re-run: keyed by slug, and the
 * image is only (re)copied if missing from the public disk.
 *
 * Run on a fresh environment (or after a migrate:fresh) with:
 *   php artisan db:seed --class=GiftCardSeeder
 * Also chained into DatabaseSeeder::run() so it's covered by a plain
 * `php artisan migrate --seed` too.
 */
class GiftCardSeeder extends Seeder
{
    private array $cards = [
        // First on purpose: the free write-your-own option, so it's the
        // first thing a shopper sees rather than buried at the end.
        [
            'slug' => 'blank-note',
            'name' => ['ar' => 'بطاقة فارغة', 'en' => 'Blank Note'],
            'file' => 'blank-note.jpg',
            'price' => 0,
        ],
        [
            'slug' => 'happy-birthday-candles',
            'name' => ['ar' => 'عيد ميلاد سعيد (شموع)', 'en' => 'Happy Birthday (Candles)'],
            'file' => 'happy-birthday-candles.jpg',
        ],
        [
            'slug' => 'happy-birthday-cake',
            'name' => ['ar' => 'عيد ميلاد سعيد (كيك)', 'en' => 'Happy Birthday (Cake)'],
            'file' => 'happy-birthday-cake.jpg',
        ],
        [
            'slug' => 'welcome-little-miracle',
            'name' => ['ar' => 'أهلاً بالمولود الجديد', 'en' => 'Welcome, Little Miracle'],
            'file' => 'welcome-little-miracle.jpg',
        ],
        [
            'slug' => 'special-gift-for-you',
            'name' => ['ar' => 'هدية خاصة لك', 'en' => 'A Special Gift For You'],
            'file' => 'special-gift-for-you.jpg',
        ],
        [
            'slug' => 'gift-from-the-heart',
            'name' => ['ar' => 'هدية من القلب', 'en' => 'A Gift For You'],
            'file' => 'gift-from-the-heart.jpg',
        ],
        [
            'slug' => 'speedy-recovery-flowers',
            'name' => ['ar' => 'سلامة وعافية', 'en' => 'Speedy Recovery'],
            'file' => 'speedy-recovery-flowers.jpg',
        ],
        [
            'slug' => 'new-arrival',
            'name' => ['ar' => 'تهانينا بالمولود الجديد', 'en' => 'Congrats On Your New Arrival'],
            'file' => 'new-arrival.jpg',
        ],
        [
            'slug' => 'other-half-red-hearts',
            'name' => ['ar' => 'أنت نصفي الآخر (قلوب حمراء)', 'en' => 'You Are My Other Half (Red Hearts)'],
            'file' => 'other-half-red-hearts.jpg',
        ],
        [
            'slug' => 'happiest-birthday-cake',
            'name' => ['ar' => 'أسعد التهاني بعيد ميلادك', 'en' => 'Happiest Birthday To You'],
            'file' => 'happiest-birthday-cake.jpg',
        ],
        [
            'slug' => 'you-complete-me',
            'name' => ['ar' => 'حياتي جميلة معك', 'en' => 'You Complete Me'],
            'file' => 'you-complete-me.jpg',
        ],
        [
            'slug' => 'get-well-soon-mug',
            'name' => ['ar' => 'سلامة قلبك', 'en' => 'Get Well Soon'],
            'file' => 'get-well-soon-mug.jpg',
        ],
        [
            'slug' => 'congrats-graduate',
            'name' => ['ar' => 'مبروك التخرج', 'en' => 'You Did It! Congrats!'],
            'file' => 'congrats-graduate.jpg',
        ],
        [
            'slug' => 'other-half-pink-hearts',
            'name' => ['ar' => 'أنت نصفي الآخر (قلوب وردية)', 'en' => 'You Are My Other Half (Pink Hearts)'],
            'file' => 'other-half-pink-hearts.jpg',
        ],
        [
            'slug' => 'you-did-it-congrats',
            'name' => ['ar' => 'مبروك تخرجك', 'en' => 'You Did It! Congrats! (Cap)'],
            'file' => 'you-did-it-congrats.jpg',
        ],
    ];

    public function run(): void
    {
        $sourceDir = resource_path('seed-images/gift-cards');

        foreach ($this->cards as $i => $card) {
            $diskPath = 'gift-cards/'.$card['file'];

            if (! Storage::disk('public')->exists($diskPath)) {
                $sourceFile = $sourceDir.'/'.$card['file'];
                if (is_file($sourceFile)) {
                    Storage::disk('public')->put($diskPath, file_get_contents($sourceFile));
                }
            }

            GiftCard::updateOrCreate(
                ['slug' => $card['slug']],
                [
                    'name'       => $card['name'],
                    'image'      => $diskPath,
                    'sort_order' => $i,
                    'is_active'  => true,
                    'price'      => $card['price'] ?? 5.00,
                ]
            );
        }
    }
}
