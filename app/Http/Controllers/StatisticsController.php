<?php

namespace App\Http\Controllers;

use App\Services\Statistics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatisticsController extends Controller
{
    public function __invoke(Request $request, Statistics $statistics): View
    {
        abort_unless($request->user() !== null, 403);

        return view('statistics', ['stats' => $statistics->forUser($request->user()->id)]);
    }
}
