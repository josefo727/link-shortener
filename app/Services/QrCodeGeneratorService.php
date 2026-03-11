<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\QrCodeGeneratorInterface;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class QrCodeGeneratorService implements QrCodeGeneratorInterface
{
    public function generatePng(string $content, int $size = 10): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => EccLevel::H,
            'scale' => $size,
            'outputBase64' => false,
            'imageTransparent' => false,
            'bgColor' => [255, 255, 255],
        ]);

        $qrCode = new QRCode($options);

        /** @var string $output */
        $output = $qrCode->render($content);

        return $output;
    }

    public function generateSvg(string $content, int $size = 10): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => EccLevel::H,
            'scale' => $size,
            'outputBase64' => false,
        ]);

        $qrCode = new QRCode($options);

        /** @var string $output */
        $output = $qrCode->render($content);

        return $output;
    }
}
