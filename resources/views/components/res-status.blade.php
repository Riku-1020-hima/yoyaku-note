@props(['reservation'])

{{-- 予約の状態のバッジ --}}
@php
    $class = match ($reservation->status) {
        'confirmed' => $reservation->starts_at->isPast() ? 'bg-amber-50 text-amber-800' : 'bg-brand-50 text-brand-700',
        'visited' => 'bg-stone-100 text-stone-600',
        'no_show' => 'bg-alert-50 text-alert-600',
        default => 'bg-stone-100 text-stone-400',
    };
    $label = $reservation->status === 'confirmed' && $reservation->starts_at->isPast() ? '予約(来店の記録なし)' : $reservation->status_label;
@endphp
<span {{ $attributes->merge(['class' => "inline-block rounded-full px-2.5 py-0.5 text-xs font-bold $class"]) }}>{{ $label }}</span>
