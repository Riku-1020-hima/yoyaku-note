@extends('layouts.app', ['title' => 'メニューを選ぶ', 'publicPage' => true, 'shopName' => $shop->name])

@use('App\Models\Menu')

@section('content')
<div class="max-w-xl mx-auto">
    <x-shop-header :shop="$shop" :step="1" />

    @if ($shop->notice)
        <div class="mb-5 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm whitespace-pre-line">{{ $shop->notice }}</div>
    @endif

    <h1 class="font-bold mb-3">メニューを選んでください</h1>
    @if ($menus->isEmpty())
        <p class="bg-white rounded-xl border border-stone-200 p-5 text-sm text-stone-500">ただいまネット予約は受け付けていません。お店にお電話ください。</p>
    @else
        <ul class="space-y-3">
            @foreach ($menus as $menu)
                <li>
                    <a href="{{ route('booking.times', [$shop->slug, $menu->id]) }}"
                       class="block bg-white rounded-xl border border-stone-200 p-4 hover:border-brand-500 focus-visible:outline-2 focus-visible:outline-brand-500">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-bold">{{ $menu->name }}</p>
                                <p class="text-sm text-stone-500 mt-0.5">所要時間 {{ $menu->duration_label }}</p>
                            </div>
                            <p class="font-bold whitespace-nowrap">{{ Menu::yen($menu->price) }}</p>
                        </div>
                        @if ($menu->description)
                            <p class="text-sm text-stone-600 mt-2 whitespace-pre-line">{{ $menu->description }}</p>
                        @endif
                        <p class="mt-3 text-sm font-bold text-brand-700">このメニューで日時を選ぶ →</p>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <x-shop-info :shop="$shop" />
</div>
@endsection
