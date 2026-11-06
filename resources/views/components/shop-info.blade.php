@props(['shop'])

{{-- お客さん用ページの下:お店の連絡先と営業時間 --}}
<section class="mt-8 text-sm text-stone-600 border-t border-stone-200 pt-5 space-y-1">
    <p class="font-bold text-ink">{{ $shop->name }}</p>
    @if ($shop->address)<p>{{ $shop->address }}</p>@endif
    @if ($shop->phone)<p>電話 <a href="tel:{{ preg_replace('/[^0-9+]/', '', $shop->phone) }}" class="underline">{{ $shop->phone }}</a></p>@endif
    <p>
        営業時間:
        @foreach (\App\Models\User::WEEKDAYS as $w => $label)
            @php $h = $shop->hoursFor($w); @endphp
            <span class="whitespace-nowrap mr-2">{{ $label }} {{ $h ? $h[0].'〜'.$h[1] : '休み' }}</span>
        @endforeach
    </p>
</section>
