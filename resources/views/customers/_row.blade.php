{{-- お客さんのページの、予約1件分の行 --}}
<li class="px-5 py-3 flex flex-wrap items-center gap-x-3 gap-y-1">
    <a href="{{ route('reservations.show', $r) }}" class="font-bold underline">{{ $r->when_label }}</a>
    <span>{{ $r->menu_name }}</span>
    <span class="text-stone-500">{{ $r->staff?->name }}</span>
    <x-res-status :reservation="$r" class="ml-auto" />
    @if ($r->shop_note)<p class="w-full text-xs text-stone-500 whitespace-pre-line">メモ:{{ $r->shop_note }}</p>@endif
</li>
