<?php

/** What a person types in the quick setup, in the browser (see web-browser-driver.php). The rest is what the pages propose. */
return [
    'fill' => [
        'Grid name' => 'Web Grid',
        'Login URI' => 'localhost:8002',
        'Grid owner' => 'Web Owner',
        'Owner password' => 'secret12',
        // Nothing is started: the scenario has no .NET runtime
        'Start' => 'no',
    ],
    'click' => [],
];
