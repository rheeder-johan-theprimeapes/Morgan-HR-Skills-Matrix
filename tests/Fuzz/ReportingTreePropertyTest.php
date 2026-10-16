<?php

declare(strict_types=1);

use App\Services\ReportingTree;
use Eris\Generators;
use Illuminate\Validation\ValidationException;

it('refuses generated reporting cycles regardless of chain length or map order', function (): void {
    $this->limitTo(50)->forAll(Generators::choose(2, 25), Generators::choose(100, 999))->then(function (int $length, int $offset): void {
        $parents = [];
        for ($position = 1; $position <= $length; $position++) {
            $parents[$offset + $position] = $position === 1 ? null : $offset + $position - 1;
        }
        $tree = new ReportingTree;

        expect(fn () => $tree->assertManagerIsSafe($offset + 1, $offset + $length, $parents))->toThrow(ValidationException::class);
        expect(fn () => $tree->assertManagerIsSafe($offset + $length, null, array_reverse($parents, true)))->not->toThrow(Throwable::class);
    });
})->group('fuzz');
