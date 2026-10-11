<?php

namespace App\Services;

use App\Models\Article;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Collection;

class LibraryPdf
{
    /** @param Collection<int, Article> $articles */
    public function render(Collection $articles, string $name, string $period): string
    {
        $categories = [];
        foreach ($articles as $article) {
            foreach ($article->categories as $category) {
                $categories[$category->name] = ($categories[$category->name] ?? 0) + 1;
            }
        }
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot(storage_path('app'));
        $options->setDefaultFont('DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('exports.library', compact('articles', 'name', 'period', 'categories'))->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $pdf->getCanvas()->page_text(42, 806, 'Veille SIO | {PAGE_NUM} / {PAGE_COUNT}', $font, 8, [0.4, 0.4, 0.4]);

        return $pdf->output() ?? '';
    }
}
