<?php

use RedirectScout\Console\ScanCommand;
use RedirectScout\Livewire\Scout;
use RedirectScout\Store;
use RedirectScout\Tools\ListTool;
use RedirectScout\Tools\SetTool;
use Wake\Plugins\Plugin;

return function (Plugin $plugin) {
    app()->singleton(Store::class);
    app()->instance('redirect-scout', $plugin);

    $plugin->page('addresses', 'redirect-scout::page', ['en' => 'Old addresses', 'de' => 'Alte Adressen'], 'link')
        ->livewire('scout', Scout::class)
        ->tool(ListTool::class)
        ->tool(SetTool::class)
        ->command(ScanCommand::class);
};
