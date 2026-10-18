<?php

namespace Wake\Http\Controllers\Panel;

use Illuminate\View\View;

class DashboardController
{
    public function __invoke(): View
    {
        return view('wake::panel.dashboard');
    }
}
