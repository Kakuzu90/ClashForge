<?php

use App\Domain\Media\Support\OriginalFilename;

it('keeps only a display-safe base name', function (string $input, string $expected) {
    expect(OriginalFilename::from($input)->value)->toBe($expected);
})->with([
    'plain' => ['base.jpg', 'base.jpg'],
    'unix path' => ['../../etc/passwd.png', 'passwd.png'],
    'windows path' => ['C:\\Users\\me\\war base.png', 'war base.png'],
    'control characters' => ["evil\u{0000}\u{202E}gnp.jpg", 'evilgnp.jpg'],
    'whitespace' => ["  my \t base  .webp ", 'my base .webp'],
]);

it('caps the length but keeps the extension', function () {
    $name = OriginalFilename::from(str_repeat('a', 400).'.jpeg');

    expect(mb_strlen($name->value))->toBe(OriginalFilename::maxLength())
        ->and($name->extension())->toBe('jpeg');
});

it('rejects a name that sanitises to nothing', function () {
    expect(OriginalFilename::tryFrom("\u{0000}/"))->toBeNull();
});
