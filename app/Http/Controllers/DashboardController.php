<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Enums\RelevanceVerdict;
use App\Enums\ReviewDecision;
use App\Models\Article;
use App\Models\Category;
use App\Models\RssSource;
use App\Services\Statistics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return view('dashboard', [
            'stats' => app(Statistics::class)->forUser($user->id),
            'recent' => Article::with(['source', 'analysis', 'categories'])->withVerdict(RelevanceVerdict::Relevant)->latest('collected_at')->limit(6)->get(),
            'collected' => Article::count(),
            'analyzed' => Article::where('analysis_status', AnalysisStatus::Completed)->count(),
            'relevant' => $user->reviews()->where('decision', ReviewDecision::Relevant)->count(),
            'sources' => RssSource::where('active', true)->count(),
            'categories' => Category::where('is_monitored', true)->orderBy('name')->get(),
        ]);
    }
}
