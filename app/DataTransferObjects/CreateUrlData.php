<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\UrlStatus;
use DateTimeInterface;

final readonly class CreateUrlData
{
    public function __construct(
        public string $originalUrl,
        public ?string $title = null,
        public ?string $customCode = null,
        public UrlStatus $status = UrlStatus::Active,
        public ?DateTimeInterface $expiresAt = null,
    ) {}

    /**
     * @param array{
     *     original_url: string,
     *     title?: string|null,
     *     custom_code?: string|null,
     *     status?: UrlStatus|string,
     *     expires_at?: DateTimeInterface|null
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $status = $data['status'] ?? UrlStatus::Active;

        if (is_string($status)) {
            $status = UrlStatus::from($status);
        }

        return new self(
            originalUrl: $data['original_url'],
            title: $data['title'] ?? null,
            customCode: $data['custom_code'] ?? null,
            status: $status,
            expiresAt: $data['expires_at'] ?? null,
        );
    }
}
