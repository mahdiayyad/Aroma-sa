<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Real Aroma abaya catalog, sourced from the brand's design lookbook
 * (Designs.pdf). Prices are placeholders pending pricing decisions — see
 * project notes. Deactivates every other category/product so the storefront
 * shows abayas only, for now; nothing is deleted, so this is fully reversible
 * by re-activating is_active on the affected rows.
 */
class AbayaCatalogSeeder extends Seeder
{
    private array $catalog = [
        [
            'slug' => 'mono-edge',
            'name' => ['ar' => 'مونو إيدج', 'en' => 'Mono Edge'],
            'short' => [
                'ar' => 'تباين كلاسيكي بالأبيض والأسود لتفاصيل عصرية راقية.',
                'en' => 'A classic black-and-white contrast for refined, modern details.',
            ],
            'desc' => [
                'ar' => 'تصميم أنيق بياقة كلاسيكية بخط أسود بارز، أكمام واسعة بحافة سوداء وزرين أنيقين، وأزرار أمامية سوداء تضيف لمسة راقية وسهولة في الارتداء.',
                'en' => 'An elegant silhouette with a classic collar trimmed in bold black, wide sleeves with a black-edged cuff, and neat black front buttons for a refined, easy-to-wear finish.',
            ],
            'price' => 890, 'featured' => true, 'new' => false, 'sale' => false,
        ],
        [
            'slug' => 'lilac-vine',
            'name' => ['ar' => 'كرمة اللافندر', 'en' => 'Lilac Vine'],
            'short' => [
                'ar' => 'تفسير عصري للجمال الخالد، صُمم للمرأة التي تُلهم من حولها.',
                'en' => 'A modern interpretation of timeless beauty, crafted for the woman who inspires.',
            ],
            'desc' => [
                'ar' => 'عباية بلون اللافندر الفاتح مزينة بتطريز كرمة من الخرز والكريستال يمتد من الكتف حتى الذيل، مع أكمام شفافة وحجاب مُنسق يمنحك إطلالة أثيرية راقية.',
                'en' => 'A soft lilac abaya adorned with a beaded and crystal vine embroidery cascading from shoulder to hem, sheer cape sleeves, and a matching hijab for an ethereal, polished look.',
            ],
            'price' => 1450, 'featured' => true, 'new' => true, 'sale' => false,
        ],
        [
            'slug' => 'ombre-cascade',
            'name' => ['ar' => 'شلال متدرج', 'en' => 'Ombré Cascade'],
            'short' => [
                'ar' => 'تدرج لوني ناعم من البني إلى الكريمي بأكمام مطوية بعناية.',
                'en' => 'A soft ombré fade from brown to cream, with delicately pleated sleeves.',
            ],
            'desc' => [
                'ar' => 'عباية مفتوحة بتدرج لوني آسر من البني الدافئ إلى الكريمي، بأكمام وذيل مطويين بشكل مروحي يمنح حركة انسيابية فاخرة مع كل خطوة.',
                'en' => 'An open abaya in a striking ombré fade from warm brown to cream, with fan-pleated sleeves and hem that move with graceful, luxurious drama.',
            ],
            'price' => 1290, 'featured' => false, 'new' => true, 'sale' => false,
        ],
        [
            'slug' => 'lace-whisper',
            'name' => ['ar' => 'همسة الدانتيل', 'en' => 'Lace Whisper'],
            'short' => [
                'ar' => 'عباية بيضاء ناعمة بأكمام دانتيل شفافة وتطريز زهري راقٍ.',
                'en' => 'A soft white abaya with sheer lace cuffs and refined floral embroidery.',
            ],
            'desc' => [
                'ar' => 'عباية بيضاء أنيقة بطيات أمامية ناعمة، أكمام واسعة بحواف دانتيل شفاف، وتطريز نباتي دقيق يمتد على طول الذيل لإطلالة عروس أو مناسبة خاصة.',
                'en' => 'An elegant white abaya with soft front pleats, wide sleeves finished in sheer lace, and delicate botanical embroidery tracing the hem — perfect for bridal or special occasions.',
            ],
            'price' => 990, 'featured' => false, 'new' => false, 'sale' => false,
        ],
        [
            'slug' => 'noir-pearl',
            'name' => ['ar' => 'لؤلؤة سوداء', 'en' => 'Noir Pearl'],
            'short' => [
                'ar' => 'أناقة خالدة، مصممة لكل لحظة مميزة.',
                'en' => 'Timeless elegance, crafted for every special moment.',
            ],
            'desc' => [
                'ar' => 'عباية سوداء درامية بأكمام مطرزة بكثافة باللؤلؤ والكريستال، تصميم بسيط في الجسم يترك المجال للتفاصيل المرصعة أن تتألق في المناسبات المسائية.',
                'en' => 'A dramatic black abaya with sleeves densely embellished in pearls and crystals — the body kept clean and simple so the beadwork takes center stage for evening occasions.',
            ],
            'price' => 1590, 'featured' => true, 'new' => false, 'sale' => true,
        ],
        [
            'slug' => 'ivory-dream',
            'name' => ['ar' => 'حلم العاج', 'en' => 'Ivory Dream'],
            'short' => [
                'ar' => 'تفاصيل فاخرة بلون نقي لإطلالة استثنائية.',
                'en' => 'Luxurious details in a pure hue for an exceptional look.',
            ],
            'desc' => [
                'ar' => 'عباية بلون العاج النقي بتطريز ثلاثي الأبعاد بخيوط ناعمة يمتد على شريط أمامي طويل وأكمام واسعة، بقصة انسيابية فريدة تمنحك إطلالة استثنائية.',
                'en' => 'A pure ivory abaya with soft three-dimensional thread embroidery along a full-length front panel and wide sleeves, cut with a uniquely flowing silhouette for an exceptional look.',
            ],
            'price' => 1150, 'featured' => true, 'new' => false, 'sale' => false,
        ],
        [
            'slug' => 'sky-whisper',
            'name' => ['ar' => 'همسة السماء', 'en' => 'Sky Whisper'],
            'short' => [
                'ar' => 'لمسة من النعومة بلون السماء تناسب كل الأوقات.',
                'en' => 'A touch of softness in sky blue, suited for every occasion.',
            ],
            'desc' => [
                'ar' => 'عباية بلون أزرق سماوي هادئ بياقة على شكل V وتطريز زهري ناعم يمتد من الكتف وعلى الأكمام الواسعة، بقصة بسيطة وأنيقة تناسب كل الأوقات.',
                'en' => 'A calm sky-blue abaya with a soft V-neckline and delicate floral embroidery tracing the shoulder and wide sleeves — a simple, elegant cut suited for every occasion.',
            ],
            'price' => 990, 'featured' => false, 'new' => true, 'sale' => false,
        ],
        [
            'slug' => 'mono-line',
            'name' => ['ar' => 'خط أحادي', 'en' => 'Mono Line'],
            'short' => [
                'ar' => 'تباين بسيط بخطوط أنيقة لإطلالة عصرية.',
                'en' => 'A simple contrast in elegant lines for a modern look.',
            ],
            'desc' => [
                'ar' => 'عباية بيضاء مفتوحة بخط مونو أسود متعرج يمتد على طول الياقة والأكمام والذيل، بقصة واسعة وانسيابية تمنحك حركة ناعمة وأناقة عصرية.',
                'en' => 'An open white abaya traced with a wavy black mono-line along the collar, sleeves, and hem — a wide, flowing cut that moves gracefully with a modern edge.',
            ],
            'price' => 820, 'featured' => false, 'new' => false, 'sale' => false,
        ],
        [
            'slug' => 'velvet-wine',
            'name' => ['ar' => 'نبيذ مخملي', 'en' => 'Velvet Wine'],
            'short' => [
                'ar' => 'عباية خالدة تنطق بالرقي في كل تفصيلة.',
                'en' => 'A timeless abaya that speaks sophistication in every detail.',
            ],
            'desc' => [
                'ar' => 'عباية بلون النبيذ العميق بتطريز ذهبي كثيف على الأكمام والشريط الأمامي، مع حزام مزين بخرز وشراشيب معلقة تضيف لمسة فاخرة لإطلالة المساء.',
                'en' => 'A deep wine abaya with dense gold floral embroidery on the sleeves and front panel, finished with a beaded belt and hanging tassels for a luxurious evening statement.',
            ],
            'price' => 1390, 'featured' => true, 'new' => false, 'sale' => false,
        ],
        [
            'slug' => 'blush-pleat',
            'name' => ['ar' => 'طيات وردية', 'en' => 'Blush Pleat'],
            'short' => [
                'ar' => 'لون وردي هادئ بطيات أمامية أنيقة.',
                'en' => 'A soft blush hue with elegant front pleating.',
            ],
            'desc' => [
                'ar' => 'عباية بلون وردي فاتح هادئ بطيات أمامية دقيقة تمتد من الياقة، وتطريز نباتي خفيف عند الذيل، لإطلالة أنثوية ناعمة تناسب كل المناسبات.',
                'en' => 'A quiet blush-pink abaya with fine front pleating from the collar down, and light botanical embroidery near the hem — a soft, feminine look for any occasion.',
            ],
            'price' => 780, 'featured' => false, 'new' => false, 'sale' => false,
        ],
        [
            'slug' => 'olive-garden',
            'name' => ['ar' => 'حديقة الزيتون', 'en' => 'Olive Garden'],
            'short' => [
                'ar' => 'مستوحى من ألوان الطبيعة. بسيط، منعش، وخالد.',
                'en' => "Inspired by nature's hues — simple, fresh, and timeless.",
            ],
            'desc' => [
                'ar' => 'عباية بلون زيتوني هادئ بياقة ناعمة مفتوحة وشريط خياطة ذهبي رفيع يمتد بطول العباية، بقصة واسعة ومحتشمة تمنحك راحة وأناقة في كل حركة.',
                'en' => 'A calm olive-green abaya with a soft open collar and a fine gold stitch line running the full length, cut wide and modest for comfort and elegance in every step.',
            ],
            'price' => 850, 'featured' => false, 'new' => false, 'sale' => false,
        ],
        [
            'slug' => 'dune-grace',
            'name' => ['ar' => 'نعمة الكثبان', 'en' => 'Dune Grace'],
            'short' => [
                'ar' => 'نعمة في كل تفصيلة.',
                'en' => 'Grace in every detail.',
            ],
            'desc' => [
                'ar' => 'عباية بلون رمادي دافئ (تاوب) بأكمام مزينة بدانتيل زهري مقصوص على شكل مروحة، وتطريز كريستال يمتد على الشريط الأمامي لإطلالة راقية ومتماسكة.',
                'en' => 'A warm taupe-grey abaya with sleeves finished in scalloped floral lace and crystal-beaded embroidery running down the front panel, for a polished and cohesive look.',
            ],
            'price' => 1290, 'featured' => false, 'new' => true, 'sale' => false,
        ],
        [
            'slug' => 'ethereal-blue',
            'name' => ['ar' => 'زرقة أثيرية', 'en' => 'Ethereal Blue'],
            'short' => [
                'ar' => 'عباية خالدة تمزج بين الرقي والتفاصيل الدقيقة لكل لحظة.',
                'en' => 'A timeless abaya that blends sophistication and delicate details for every moment.',
            ],
            'desc' => [
                'ar' => 'عباية بلون أزرق سماوي فاتح بتطريز خرز على الأكمام وبروش زهري عند الخصر، بقصة مفتوحة أنيقة تناسب المناسبات الخاصة والاحتفالات.',
                'en' => 'A pale sky-blue abaya with beaded embroidery on the sleeves and a floral brooch detail at the waist — an elegant open cut suited for special occasions and celebrations.',
            ],
            'price' => 1350, 'featured' => true, 'new' => false, 'sale' => true,
        ],
        [
            'slug' => 'peach-glow',
            'name' => ['ar' => 'توهج الخوخ', 'en' => 'Peach Glow'],
            'short' => [
                'ar' => 'لون نابض بالحيوية يمنحك إشراقة صيف لا تُنسى.',
                'en' => 'A vibrant color that gives you an unforgettable summer glow.',
            ],
            'desc' => [
                'ar' => 'عباية بلون خوخي منعش بياقة V أنيقة وقماش خفيف وناعم مثالي لأيام الصيف الحارة، بقصة واسعة ومريحة تجمع بين الراحة والأناقة.',
                'en' => 'A refreshing peach abaya with an elegant V-neckline and a light, soft fabric ideal for warm summer days — a wide, comfortable cut that blends ease with elegance.',
            ],
            'price' => 750, 'featured' => false, 'new' => false, 'sale' => false,
        ],
        [
            'slug' => 'golden-mint',
            'name' => ['ar' => 'نعناع ذهبي', 'en' => 'Golden Mint'],
            'short' => [
                'ar' => 'لون فاتح بتفاصيل غنية، صُمم لأيام مشمسة ولحظات خالدة.',
                'en' => 'Light in color, rich in detail. Designed for sunny days and timeless moments.',
            ],
            'desc' => [
                'ar' => 'عباية بلون النعناع الفاتح بتطريز ذهبي زهري كثيف على الأكمام والشريط الأمامي، مع حزام مزين بخرز وشراشيب لؤلؤية تضيف لمسة صيفية فاخرة.',
                'en' => 'A soft mint-green abaya with dense gold floral embroidery on the sleeves and front panel, finished with a beaded belt and pearl tassels for a luxurious summer touch.',
            ],
            'price' => 1390, 'featured' => false, 'new' => true, 'sale' => false,
        ],
        [
            'slug' => 'wine-vine',
            'name' => ['ar' => 'كرمة النبيذ', 'en' => 'Wine Vine'],
            'short' => [
                'ar' => 'عباية خالدة تجمع الرقي والحرفية في كل تفصيلة.',
                'en' => 'A timeless abaya that speaks sophistication in every detail.',
            ],
            'desc' => [
                'ar' => 'عباية بلون النبيذ العميق بتطريز نباتي رأسي أنيق يمتد على الشريط الأمامي والأكمام، بقصة كلاسيكية بسيطة تبرز جمال التطريز اليدوي.',
                'en' => 'A deep wine abaya with an elegant vertical floral vine embroidered along the front panel and sleeves — a clean, classic cut that lets the handwork take the spotlight.',
            ],
            'price' => 1190, 'featured' => false, 'new' => false, 'sale' => false,
        ],
        [
            'slug' => 'citrus-breeze',
            'name' => ['ar' => 'نسيم الحمضيات', 'en' => 'Citrus Breeze'],
            'short' => [
                'ar' => 'انعاش الصيف في كل تفصيلة. تصميم يجمع بين الراحة والأناقة.',
                'en' => 'Summer freshness in every detail — a design that blends comfort and elegance.',
            ],
            'desc' => [
                'ar' => 'عباية بلون بيج فاتح بياقة أنيقة وأكمام مطوية بشكل مروحي، مع ثنيات جانبية وخلفية دقيقة تمنحك حرية حركة وأناقة عصرية.',
                'en' => 'A light beige abaya with an elegant collar and fan-pleated bell sleeves, finished with fine side and back pleats for freedom of movement and a modern edge.',
            ],
            'price' => 890, 'featured' => false, 'new' => true, 'sale' => false,
        ],
        [
            'slug' => 'ivory-grace',
            'name' => ['ar' => 'نعمة العاج', 'en' => 'Ivory Grace'],
            'short' => [
                'ar' => 'بساطة راقية بقماش ناعم الملمس وتطريز خفيف.',
                'en' => 'Refined simplicity in a softly textured fabric with light embroidery.',
            ],
            'desc' => [
                'ar' => 'عباية بلون العاج بقماش ناعم الملمس وتطريز نباتي خفيف عند الذيل والأكمام، بقصة بسيطة أنيقة تناسب الإطلالات اليومية الراقية.',
                'en' => 'An ivory abaya in a softly textured fabric with light botanical embroidery at the hem and sleeves — a simple, elegant cut suited for refined everyday wear.',
            ],
            'price' => 690, 'featured' => false, 'new' => false, 'sale' => false,
        ],
        [
            'slug' => 'sage-bloom',
            'name' => ['ar' => 'إزهار حكيم', 'en' => 'Sage Bloom'],
            'short' => [
                'ar' => 'عباية بتصميم ملتف وتطريز زهري لامع على الأكمام والذيل.',
                'en' => 'A wrap-style abaya with sparkling floral embroidery on the sleeves and hem.',
            ],
            'desc' => [
                'ar' => 'عباية بلون أخضر حكيم بتصميم ملتف أنيق، وتطريز زهري مطعّم بالترتر يمتد على حواف الأكمام والذيل، لإطلالة أنثوية لافتة.',
                'en' => 'A sage-green abaya in an elegant wrap silhouette, with sequin-flecked floral embroidery tracing the sleeve and hem edges for a striking feminine look.',
            ],
            'price' => 1250, 'featured' => false, 'new' => false, 'sale' => false,
        ],
        [
            'slug' => 'palm-shade',
            'name' => ['ar' => 'ظل النخيل', 'en' => 'Palm Shade'],
            'short' => [
                'ar' => 'لمسة استوائية راقية. مثالي لأيام الصيف الدافئة.',
                'en' => 'A refined tropical touch — perfect for warm summer days.',
            ],
            'desc' => [
                'ar' => 'عباية بلون كريمي بتصميم كيمونو مفتوح وحزام قابل للتعديل، مطرزة بنخيل ذهبي أنيق على الأكمام والذيل يمنحك إطلالة استوائية راقية.',
                'en' => 'A cream abaya in an open kimono silhouette with an adjustable belt, embroidered with elegant golden palm trees on the sleeves and hem for a refined tropical look.',
            ],
            'price' => 950, 'featured' => true, 'new' => true, 'sale' => false,
        ],
        [
            'slug' => 'sage-lace',
            'name' => ['ar' => 'دانتيل حكيم', 'en' => 'Sage Lace'],
            'short' => [
                'ar' => 'نعمة في كل تفصيلة، بدانتيل مقصوص بعناية.',
                'en' => 'Grace in every detail, with meticulously cut lace trim.',
            ],
            'desc' => [
                'ar' => 'عباية بلون أخضر حكيم بحاشية دانتيل زهري مقصوص يمتد على الياقة والأكمام والشريط الأمامي بالكامل، لإطلالة راقية غنية بالتفاصيل.',
                'en' => 'A sage-green abaya bordered in scalloped floral lace running the full length of the collar, sleeves, and front panel — a detail-rich, polished look.',
            ],
            'price' => 1290, 'featured' => false, 'new' => false, 'sale' => true,
        ],
        [
            'slug' => 'lemon-zest',
            'name' => ['ar' => 'نكهة الليمون', 'en' => 'Lemon Zest'],
            'short' => [
                'ar' => 'إشراقة الليمون المنعشة في تصميم أنيق وأنيق.',
                'en' => 'The refreshing brightness of lemon in a sleek, elegant design.',
            ],
            'desc' => [
                'ar' => 'عباية بلون أصفر ليموني فاتح بياقة أنيقة مفتوحة وشريط تطريز أنيق يمتد بطول العباية، بقصة انسيابية محتشمة تناسب جميع المقاسات.',
                'en' => 'A soft lemon-yellow abaya with an elegant open collar and a neat embroidered trim running the full length — a flowing, modest cut that suits every size.',
            ],
            'price' => 820, 'featured' => false, 'new' => false, 'sale' => false,
        ],
        [
            'slug' => 'sage-trend',
            'name' => ['ar' => 'اتجاه حكيم', 'en' => 'Sage Trend'],
            'short' => [
                'ar' => 'لون هادئ وقصة عصرية بتفاصيل مميزة.',
                'en' => 'A calm color and a modern cut with distinctive details.',
            ],
            'desc' => [
                'ar' => 'عباية بلون أخضر حكيم بياقة مفتوحة ناعمة وحزام قابل للتعديل يناسب جميع المقاسات، مع أكمام مطوية بثنيات أنيقة تضيف لمسة راقية.',
                'en' => 'A sage-green abaya with a soft open collar and an adjustable belt that suits every size, finished with elegantly pin-tucked sleeve cuffs for a refined touch.',
            ],
            'price' => 870, 'featured' => false, 'new' => false, 'sale' => false,
        ],
        [
            'slug' => 'blossom-ivory',
            'name' => ['ar' => 'عاج مزهر', 'en' => 'Blossom Ivory'],
            'short' => [
                'ar' => 'عباية عاجية بتطريز زهري أنيق من الأمام والخلف.',
                'en' => 'An ivory abaya with elegant floral embroidery, front and back.',
            ],
            'desc' => [
                'ar' => 'عباية بلون عاجي بتطريز زهري ذهبي يمتد من الكتف حتى الذيل من الأمام ومن الخلف، مع حجاب منسق لإطلالة متكاملة وراقية.',
                'en' => 'An ivory abaya with golden floral embroidery tracing from shoulder to hem on both the front and back, paired with a matching hijab for a complete, polished look.',
            ],
            'price' => 1050, 'featured' => false, 'new' => false, 'sale' => false,
        ],
        [
            'slug' => 'midnight-bloom',
            'name' => ['ar' => 'إزهار منتصف الليل', 'en' => 'Midnight Bloom'],
            'short' => [
                'ar' => 'عباية سوداء أنيقة بتطريز نباتي دقيق.',
                'en' => 'A sleek black abaya with delicate botanical embroidery.',
            ],
            'desc' => [
                'ar' => 'عباية سوداء أنيقة بتصميم غير متماثل وتطريز نباتي دقيق يمتد على الكتف والشريط الأمامي، بقصة عصرية بسيطة تناسب المناسبات المسائية.',
                'en' => 'A sleek black abaya with an asymmetric drape and delicate botanical embroidery along the shoulder and front panel — a clean, modern cut suited for evening wear.',
            ],
            'price' => 990, 'featured' => false, 'new' => true, 'sale' => false,
        ],
        [
            'slug' => 'ivory-lace',
            'name' => ['ar' => 'دانتيل عاجي', 'en' => 'Ivory Lace'],
            'short' => [
                'ar' => 'عباية عاجية بشريط دانتيل مطرز وتفاصيل زهرية راقية.',
                'en' => 'An ivory abaya with an embroidered lace panel and refined floral detail.',
            ],
            'desc' => [
                'ar' => 'عباية بلون عاجي بشريط دانتيل مطرز يمتد بطول العباية، وأكمام دانتيل شفافة، لإطلالة أنيقة تناسب المناسبات الخاصة.',
                'en' => 'An ivory abaya with an embroidered lace panel running the full length and sheer lace sleeve trims — an elegant look suited for special occasions.',
            ],
            'price' => 1090, 'featured' => false, 'new' => false, 'sale' => false,
        ],
    ];

    public function run(): void
    {
        $abayaCategory = Category::where('slug', 'abayas')->first();

        if (! $abayaCategory) {
            return;
        }

        // Keep only Abayas visible on the storefront for now (reversible —
        // nothing is deleted, just flagged inactive).
        Category::where('id', '!=', $abayaCategory->id)->update(['is_active' => false]);
        Category::whereKey($abayaCategory->id)->update(['is_active' => true]);

        Product::where('category_id', '!=', $abayaCategory->id)->update(['is_active' => false]);

        $brandId = Brand::where('name->en', 'Aroma Signature')->value('id')
            ?? Brand::query()->value('id');

        foreach ($this->catalog as $i => $item) {
            $onSale = $item['sale'];
            $basePrice = (float) $item['price'];

            $product = Product::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'category_id'        => $abayaCategory->id,
                    'brand_id'            => $brandId,
                    'name'                => $item['name'],
                    'short_description'   => $item['short'],
                    'description'         => $item['desc'],
                    'sku'                 => 'ABY-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'base_price'          => $basePrice,
                    'compare_at_price'    => $onSale ? round($basePrice * 1.2, 2) : null,
                    'currency'            => 'SAR',
                    'stock_quantity'      => 18,
                    'has_variants'        => false,
                    'scent_family'        => null,
                    'is_active'           => true,
                    'is_featured'         => $item['featured'],
                    'is_new_arrival'      => $item['new'],
                    'is_gift_eligible'    => true,
                ]
            );

            $product->images()->updateOrCreate(
                ['path' => '/images/products/'.$item['slug'].'.jpg'],
                [
                    'disk'       => 'public',
                    'alt'        => $item['name'],
                    'is_primary' => true,
                    'sort_order' => 0,
                ]
            );
        }
    }
}
