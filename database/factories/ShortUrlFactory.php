<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UrlStatus;
use App\Models\ShortUrl;
use App\Services\CodeGeneratorService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShortUrl>
 */
final class ShortUrlFactory extends Factory
{
    protected $model = ShortUrl::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = $this->faker->url();

        return [
            'code' => (new CodeGeneratorService)->generate(),
            'original_url' => $url,
            'original_url_hash' => ShortUrl::hashUrl($url),
            'status' => UrlStatus::Active,
            'clicks' => 0,
            'expires_at' => null,
        ];
    }

    /**
     * Set status to inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => UrlStatus::Inactive,
        ]);
    }

    /**
     * Set status to expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => UrlStatus::Expired,
        ]);
    }

    /**
     * Set an expiration date in the past.
     */
    public function expiredAt(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Set an expiration date in the future.
     */
    public function expiresInDays(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->addDays($days),
        ]);
    }

    /**
     * Set a specific number of clicks.
     */
    public function withClicks(int $clicks): static
    {
        return $this->state(fn (array $attributes): array => [
            'clicks' => $clicks,
        ]);
    }

    /**
     * Set a specific URL.
     */
    public function withUrl(string $url): static
    {
        return $this->state(fn (array $attributes): array => [
            'original_url' => $url,
            'original_url_hash' => ShortUrl::hashUrl($url),
        ]);
    }

    /**
     * Set a specific code.
     */
    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => $code,
        ]);
    }
}
