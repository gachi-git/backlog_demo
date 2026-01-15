<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // CORSを適用するパス（apiから始まるURLすべて）
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // 許可するHTTPメソッド（GET, POST, PATCH, DELETEなどすべて許可）
    'allowed_methods' => ['*'],

    // 許可するオリジン（フロントエンドのURL）
    // ★開発中は '*' にしておくと、localhostのポート番号が変わっても繋がります
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    // 許可するヘッダー（すべて許可）
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // クッキーを使った認証などをする場合は true にしますが、
    // allowed_origins が '*' の場合は false にする必要があります。
    'supports_credentials' => false,

];