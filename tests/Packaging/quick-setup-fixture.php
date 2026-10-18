<?php

/** What a person types in the quick setup, in the browser (see web-browser-driver.php). The rest is what the pages propose. */
return [
    'fill' => [
        'Grid name' => 'Web Grid',
        'Grid URL' => 'localhost:8002',
        'Grid owner' => 'Web Owner',
        'Owner password' => 'secret12',
        // The account of the database exists already: its password is asked, once
        'Database password' => 'knownpw12',
        'Region name' => 'Second Place',
    ],
    'click' => [
        // The grid is ready: go on, add a region to the simulator that has one, then leave (the roles the grid has are not checked again)
        'Simulator: ' => ['Add region', 'Quit'],
        'Setup' => 'Continue setup',
    ],
];
