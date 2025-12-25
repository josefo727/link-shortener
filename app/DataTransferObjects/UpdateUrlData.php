<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\UrlStatus;
use DateTimeInterface;

final readonly class UpdateUrlData
{
    public function __construct(
        public ?string $originalUrl = null,
        public ?string $title = null,
        public ?UrlStatus $status = null,
        public ?DateTimeInterface $expiresAt = null,
        public bool $expiresAtWasSet = false,
        public bool $titleWasSet = false,
    ) {}

    /**
     * @param  array{original_url?: string|null, title?: string|null, status?: UrlStatus|string|null, expires_at?: DateTimeInterface|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $status = $data['status'] ?? null;

        if (is_string($status)) {
            $status = UrlStatus::from($status);
        }

        return new self(
            originalUrl: $data['original_url'] ?? null,
            title: $data['title'] ?? null,
            status: $status,
            expiresAt: $data['expires_at'] ?? null,
            expiresAtWasSet: array_key_exists('expires_at', $data),
            titleWasSet: array_key_exists('title', $data),
        );
    }

    public function hasChanges(): bool
    {
        return $this->originalUrl !== null
            || $this->title !== null
            || $this->status !== null
            || $this->expiresAt !== null
            || $this->expiresAtWasSet
            || $this->titleWasSet;
    }
}
