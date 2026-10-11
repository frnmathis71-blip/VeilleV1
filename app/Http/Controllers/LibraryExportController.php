<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\LibraryPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LibraryExportController extends Controller
{
    public function __invoke(Request $request, LibraryPdf $pdf): Response
    {
        $validated = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $user = $request->user();
        abort_unless($user !== null, 403);
        $query = Article::with(['source', 'analysis', 'categories', 'reviews' => fn ($q) => $q->where('user_id', $user->id)])
            ->whereHas('reviews', fn ($q) => $q->where('user_id', $user->id)->where('decision', 'relevant'));
        if (! empty($validated['from'])) {
            $query->whereDate('published_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('published_at', '<=', $validated['to']);
        }
        abort_if($query->count() > 100, 422, 'Export limité à 100 articles : choisissez une période plus courte.');
        $articles = $query->orderBy('published_at')->get();
        $period = ($validated['from'] ?? 'Début').' au '.($validated['to'] ?? 'Aujourd’hui').' (publication)';

        return response($pdf->render($articles, $user->name, $period), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="dossier-veille-'.now()->format('Y-m-d').'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
