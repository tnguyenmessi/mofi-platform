<?php

return [
    'enabled' => env('DEMO_ENABLED', false),
    'login_password' => env('DEMO_LOGIN_PASSWORD'),
    'admin_password' => env('DEMO_ADMIN_PASSWORD'),
    'simulation_date' => '2026-09-15',
    // The fast cadence keeps a product demo visibly alive; production can slow it down via env.
    'market_real_interval_seconds' => max(1, (int) env('DEMO_MARKET_REAL_INTERVAL_SECONDS', 5)),
    'market_simulated_interval_minutes' => max(1, (int) env('DEMO_MARKET_SIMULATED_INTERVAL_MINUTES', 5)),
];
