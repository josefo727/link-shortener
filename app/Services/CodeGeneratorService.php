<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CodeGeneratorInterface;
use Exception;

use function strlen;

final readonly class CodeGeneratorService implements CodeGeneratorInterface
{
    private int $length;

    private string $alphabet;

    public function __construct(?int $length = null, ?string $alphabet = null)
    {
        /** @var int $configLength */
        $configLength = config('shortener.code.length', 6);
        /** @var string $configAlphabet */
        $configAlphabet = config('shortener.code.alphabet', 'abcdefghjkmnpqrtuvwxyACDEFGHJKMNPQRTUVWXY346789');

        $this->length = $length ?? $configLength;
        $this->alphabet = $alphabet ?? $configAlphabet;
    }

    public function generate(): string
    {
        $alphabetLength = strlen($this->alphabet);

        if ($alphabetLength === 0 || $this->length < 1) {
            return '';
        }

        $code = '';

        try {
            $bytes = random_bytes($this->length);

            for ($i = 0; $i < $this->length; $i++) {
                $index = ord($bytes[$i]) % $alphabetLength;
                $code .= $this->alphabet[$index];
            }
        } catch (Exception) {
            // Fallback if random_bytes fails (extremely rare)
            for ($i = 0; $i < $this->length; $i++) {
                $code .= $this->alphabet[random_int(0, $alphabetLength - 1)];
            }
        }

        return $code;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getAlphabet(): string
    {
        return $this->alphabet;
    }
}
