{{-- Original abaya "technical flat" line diagram — hand-built SVG, not traced
     from any reference. Symmetric shape, so no RTL mirroring is needed; only
     the label text swaps with $isAr. --}}
@php
    $labels = $isAr ? [
        'length'  => 'الطول',
        'bust'    => 'عرض الصدر',
        'hem'     => 'عرض السفل',
        'sleeve'  => 'طول الكم',
    ] : [
        'length'  => 'Length',
        'bust'    => 'Bust width',
        'hem'     => 'Hem width',
        'sleeve'  => 'Sleeve length',
    ];
@endphp
<div class="aroma-size-diagram">
    <svg viewBox="0 0 340 460" role="img" aria-label="{{ $isAr ? 'رسم توضيحي لقياسات العباية' : 'Diagram showing where abaya measurements are taken' }}">
        {{-- Garment silhouette --}}
        <path class="aroma-size-diagram-garment"
              d="M150,38 L104,54 L26,98 L58,146 L92,162 L48,414 L280,414 L236,162 L270,146 L302,98 L224,54 L178,38 L164,56 Z" />

        {{-- Length (left side, vertical) --}}
        <line class="aroma-size-diagram-dim" x1="14" y1="40" x2="14" y2="412" />
        <line class="aroma-size-diagram-tick" x1="8" y1="40" x2="20" y2="40" />
        <line class="aroma-size-diagram-tick" x1="8" y1="412" x2="20" y2="412" />
        <text class="aroma-size-diagram-label" x="14" y="228" transform="rotate(-90 14 228)" text-anchor="middle">{{ $labels['length'] }}</text>

        {{-- Bust width (across underarms) --}}
        <line class="aroma-size-diagram-dim" x1="92" y1="178" x2="236" y2="178" />
        <line class="aroma-size-diagram-tick" x1="92" y1="172" x2="92" y2="184" />
        <line class="aroma-size-diagram-tick" x1="236" y1="172" x2="236" y2="184" />
        <text class="aroma-size-diagram-label" x="164" y="172" text-anchor="middle">{{ $labels['bust'] }}</text>

        {{-- Hem width --}}
        <line class="aroma-size-diagram-dim" x1="48" y1="432" x2="280" y2="432" />
        <line class="aroma-size-diagram-tick" x1="48" y1="426" x2="48" y2="438" />
        <line class="aroma-size-diagram-tick" x1="280" y1="426" x2="280" y2="438" />
        <text class="aroma-size-diagram-label" x="164" y="452" text-anchor="middle">{{ $labels['hem'] }}</text>

        {{-- Sleeve length (along the right sleeve edge) --}}
        <line class="aroma-size-diagram-dim" x1="224" y1="54" x2="302" y2="98" />
        <text class="aroma-size-diagram-label" x="278" y="68" transform="rotate(30 278 68)" text-anchor="middle">{{ $labels['sleeve'] }}</text>
    </svg>
</div>
