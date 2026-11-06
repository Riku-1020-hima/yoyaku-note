@props(['shop', 'step' => null])

{{-- お客さん用ページの上の部分(お店の名前と、今どの手順か) --}}
<div class="mb-5">
    <a href="{{ route('booking.shop', $shop->slug) }}" class="block">
        <p class="text-xs text-stone-500">ネット予約</p>
        <p class="text-xl font-bold">{{ $shop->name }}</p>
    </a>
    @if ($step)
        <ol class="mt-4 grid grid-cols-3 gap-1 text-xs text-center" aria-label="予約の手順">
            @foreach (['メニュー', '日時', 'お客さま情報'] as $i => $label)
                <li class="rounded-full py-1 {{ $i + 1 === $step ? 'bg-brand-500 text-white font-bold' : ($i + 1 < $step ? 'bg-brand-100 text-brand-700' : 'bg-stone-100 text-stone-500') }}"
                    @if ($i + 1 === $step) aria-current="step" @endif>{{ $i + 1 }}. {{ $label }}</li>
            @endforeach
        </ol>
    @endif
</div>
