<?php

/** A region added to the grid the quick setup made, from its menu, in the browser (see web-browser-driver.php). */
return [
    'fill' => [
        'Region name' => 'Second Place',
        'Database password' => 'knownpw12',
    ],
    'click' => [
        // The menu of the simulator that was made: leave. The menu of the grid: add a region
        'Simulator: ' => 'Quit',
        'Grid: web_grid' => 'Add region',
    ],
];
