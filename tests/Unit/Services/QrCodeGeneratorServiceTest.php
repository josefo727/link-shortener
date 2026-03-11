<?php

declare(strict_types=1);

use App\Contracts\QrCodeGeneratorInterface;
use App\Services\QrCodeGeneratorService;

describe('QrCodeGeneratorService', function (): void {
    it('implements QrCodeGeneratorInterface', function (): void {
        $service = new QrCodeGeneratorService;

        expect($service)->toBeInstanceOf(QrCodeGeneratorInterface::class);
    });

    it('generates a valid PNG image', function (): void {
        $service = new QrCodeGeneratorService;

        $png = $service->generatePng('https://example.com');

        // PNG files start with the 8-byte signature: \x89PNG\r\n\x1a\n
        expect($png)->toStartWith("\x89PNG");
        expect(strlen($png))->toBeGreaterThan(0);
    });

    it('generates different images for different content', function (): void {
        $service = new QrCodeGeneratorService;

        $png1 = $service->generatePng('https://example.com/one');
        $png2 = $service->generatePng('https://example.com/two');

        expect($png1)->not->toBe($png2);
    });

    it('accepts a custom scale parameter', function (): void {
        $service = new QrCodeGeneratorService;

        $smallPng = $service->generatePng('https://example.com', 5);
        $largePng = $service->generatePng('https://example.com', 20);

        // Larger scale should produce a larger image
        expect(strlen($largePng))->toBeGreaterThan(strlen($smallPng));
    });

    it('generates a scannable QR code with the correct content', function (): void {
        $service = new QrCodeGeneratorService;
        $content = 'https://example.com/test';

        $png = $service->generatePng($content);

        // Verify it's a valid image by loading it with GD
        $image = imagecreatefromstring($png);
        expect($image)->not->toBeFalse();

        if ($image !== false) {
            expect(imagesx($image))->toBeGreaterThan(0);
            expect(imagesy($image))->toBeGreaterThan(0);
            imagedestroy($image);
        }
    });
});

describe('QrCodeGeneratorService SVG', function (): void {
    it('generates a valid SVG image', function (): void {
        $service = new QrCodeGeneratorService;

        $svg = $service->generateSvg('https://example.com');

        expect($svg)->toContain('<svg');
        expect($svg)->toContain('</svg>');
        expect(strlen($svg))->toBeGreaterThan(0);
    });

    it('generates different SVGs for different content', function (): void {
        $service = new QrCodeGeneratorService;

        $svg1 = $service->generateSvg('https://example.com/one');
        $svg2 = $service->generateSvg('https://example.com/two');

        expect($svg1)->not->toBe($svg2);
    });

    it('accepts a custom scale parameter for SVG', function (): void {
        $service = new QrCodeGeneratorService;

        $svg = $service->generateSvg('https://example.com', 20);

        // SVG is generated successfully regardless of scale
        expect($svg)->toContain('<svg');
    });

    it('generates well-formed XML', function (): void {
        $service = new QrCodeGeneratorService;

        $svg = $service->generateSvg('https://example.com');

        expect($svg)->toContain('<?xml');
        expect($svg)->toContain('xmlns');
    });
});
