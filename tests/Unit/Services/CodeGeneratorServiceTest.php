<?php

declare(strict_types=1);

use App\Contracts\CodeGeneratorInterface;
use App\Services\CodeGeneratorService;

beforeEach(function (): void {
    $this->generator = new CodeGeneratorService;
    $this->configuredLength = (int) config('shortener.code.length');
    $this->configuredAlphabet = (string) config('shortener.code.alphabet');
});

it('implements CodeGeneratorInterface', function (): void {
    expect($this->generator)->toBeInstanceOf(CodeGeneratorInterface::class);
});

it('generates a code with configured length', function (): void {
    $code = $this->generator->generate();

    expect($code)->toHaveLength($this->configuredLength);
});

it('generates a code with custom length', function (): void {
    $customLength = 8;
    $generator = new CodeGeneratorService(length: $customLength);

    $code = $generator->generate();

    expect($code)->toHaveLength($customLength);
});

it('only uses characters from the configured alphabet', function (): void {
    $code = $this->generator->generate();

    for ($i = 0; $i < strlen($code); $i++) {
        expect($this->configuredAlphabet)->toContain($code[$i]);
    }
});

it('only uses characters from a custom alphabet', function (): void {
    $customAlphabet = 'abc123';
    $generator = new CodeGeneratorService(alphabet: $customAlphabet);

    $code = $generator->generate();

    for ($i = 0; $i < strlen($code); $i++) {
        expect($customAlphabet)->toContain($code[$i]);
    }
});

it('generates unique codes', function (): void {
    $codes = [];

    for ($i = 0; $i < 100; $i++) {
        $codes[] = $this->generator->generate();
    }

    expect(array_unique($codes))->toHaveCount(100);
});

it('generates non-empty codes', function (): void {
    $code = $this->generator->generate();

    expect($code)->not->toBeEmpty();
});

it('does not include ambiguous characters in default alphabet', function (): void {
    $ambiguous = ['0', 'O', '1', 'l', 'I', '2', 'Z', '5', 'S'];

    foreach ($ambiguous as $char) {
        expect($this->configuredAlphabet)->not->toContain($char);
    }
});

it('exposes configured length via getter', function (): void {
    expect($this->generator->getLength())->toBe($this->configuredLength);
});

it('exposes configured alphabet via getter', function (): void {
    expect($this->generator->getAlphabet())->toBe($this->configuredAlphabet);
});
