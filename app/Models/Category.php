<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'keywords', 'is_monitored'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'is_monitored' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Article, $this> */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }
}
