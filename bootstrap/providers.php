<?php

use App\Providers\AppServiceProvider;
use App\Providers\RateLimitingServiceProvider;

return [
    AppServiceProvider::class,
    RateLimitingServiceProvider::class,
];
