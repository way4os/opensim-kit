<?php

use OpenSim\Provisioning\Slug;

test('keeps the words apart, in kebab-case for a slug and snake_case for a nick', function (string $name, string $slug, string $nick) {
    expect(Slug::slug($name))->toBe($slug)->and(Slug::nick($name))->toBe($nick);
})->with([
    ['The Rapist', 'the-rapist', 'the_rapist'],
    ['Therapist', 'therapist', 'therapist'],
    ['Car Pet', 'car-pet', 'car_pet'],
    ['Carpet', 'carpet', 'carpet'],
    ['Man Age', 'man-age', 'man_age'],
    ['Manage', 'manage', 'manage'],
    ["Olivier's grid", 'oliviers-grid', 'oliviers_grid'],
    ['OliviersGrid', 'oliviers-grid', 'oliviers_grid'],
    ['Joyeux Noël', 'joyeux-noel', 'joyeux_noel'],
    ['  --Weird  name!! ', 'weird-name', 'weird_name'],
]);

test('names the instances of two grids apart even when their words are the same', function () {
    expect(Slug::simulator('testgrid', 'Sim 1'))->toBe('testgrid_sim-1')
        ->and(Slug::simulator('Wonderful', 'Green Field'))->not->toBe(Slug::simulator('Wonderful Green', 'Field'));
});
