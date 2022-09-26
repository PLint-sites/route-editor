<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class LittleExplorerController extends Controller
{
    public function start()
    {
        return Inertia::render('LittleExplorer');
    }
}
