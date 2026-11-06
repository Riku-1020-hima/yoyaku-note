<?php

// 公開デモ用の設定。本番(Laravel Cloud)の環境変数で DEMO_MODE=true にすると有効になります。
return [
    'enabled' => (bool) env('DEMO_MODE', false),
    'email' => 'demo@example.com',
    'password' => 'password',
    // デモのお店の予約ページ(/r/demo)
    'shop_slug' => 'demo',
    // 毎日このタイムゾーンのこの時刻にデータを初期状態に戻す
    'reset_at' => '04:00',
    'timezone' => 'Asia/Tokyo',
];
