<?php

return [
    'PATH_CHROME' => env('PATH_CHROME'),
    'PATH_NODE_MODULES' => env('PATH_NODE_MODULES'),
    'HOST' => env('BROWSERSHOT_CHROMIUM_HOST', '127.0.0.1'),
    'NODE' => env('NODE', '/usr/bin/node'),
    'NPM' => env('NPM', '/usr/bin/npm'),
];
