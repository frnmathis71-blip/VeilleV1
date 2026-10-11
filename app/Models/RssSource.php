<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RssSource extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'url', 'url_hash', 'active', 'language', 'reliability', 'last_attempt_at', 'last_success_at', 'last_error', 'etag', 'last_modified', 'next_fetch_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'reliability' => 'integer',
            'last_attempt_at' => 'datetime',
            'last_success_at' => 'datetime',
            'next_fetch_at' => 'datetime',
        ];
    }

    /** @return HasMany<Article, $this> */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
