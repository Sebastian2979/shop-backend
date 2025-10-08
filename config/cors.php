<?php

return [
    'paths' => ['api/*'], // reicht, wenn nur /api benutzt wird

    'allowed_methods' => ['*'],

    // Exakte Dev-Origins:
    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],

    // Alternativ (statt oben) flexibel für alle Vite-Ports:
    // 'allowed_origins_patterns' => ['#^http://(localhost|127\.0\.0\.1):\d+$#'],

    // Alle benötigten Request-Header explizit erlauben
    'allowed_headers' => [
        'Content-Type', 'Accept', 'Authorization',
        'X-Requested-With', 'X-Cart-Token',
    ],

    // Nur nötig, wenn du Response-Header im Browser AUSLESEN willst
    // (z. B. wenn der Server "X-Cart-Token" zurückschickt)
    'exposed_headers' => ['X-Cart-Token'],

    'max_age' => 0,

    // Wir nutzen Bearer-Header, keine Cookies:
    'supports_credentials' => false,
];