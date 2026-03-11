<?php

declare(strict_types=1);

namespace App\Contracts;

interface QrCodeGeneratorInterface
{
    /**
     * Generate a QR code PNG image for the given content.
     *
     * @return string Raw PNG image data
     */
    public function generatePng(string $content, int $size = 10): string;

    /**
     * Generate a QR code SVG image for the given content.
     *
     * @return string Raw SVG markup
     */
    public function generateSvg(string $content, int $size = 10): string;
}
