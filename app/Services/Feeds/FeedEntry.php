<?php

namespace App\Services\Feeds;

final readonly class FeedEntry
{
    public function __construct(
        public string $title,
        public string $url,
        public ?string $guid,
        public string $description,
        public ?string $author,
        public ?string $publishedAt,
        public ?string $imageUrl,
        public ?string $language,
    ) {}

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, string|null> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['title'] ?? '', $data['url'] ?? '', $data['guid'], $data['description'] ?? '', $data['author'], $data['publishedAt'], $data['imageUrl'], $data['language']);
    }
}
