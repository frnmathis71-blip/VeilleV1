<?php

namespace App\Contracts;

use App\Models\Article;

interface ArticleAnalyzer
{
    /** @param array<string, mixed> $settings */
    public function analyze(Article $article, array $settings): void;
}
