<?php

use Greeter\Text\Salutation;
use Illuminate\Support\Facades\Route;
use Wake\Plugins\Plugin;

return function (Plugin $plugin) {
    $plugin->card('greeting', 'greeter::cards.greeting')
        ->field('colour', 'greeter::fields.colour', ['en' => 'Colour', 'de' => 'Farbe'])
        ->routes(fn () => Route::get('hello-plugin', fn () => Salutation::of($plugin->option('greeting'))))
        ->head(fn ($site, $entry) => '<meta name="greeter" content="'.e($plugin->option('greeting')).'">');
};
