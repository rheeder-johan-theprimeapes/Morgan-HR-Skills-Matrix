<?php

declare(strict_types=1);

use App\Models\User;
use Eris\Generators;

it('never serializes authentication secrets for generated user attributes', function (): void {
    $this->limitTo(200)->forAll(
        Generators::string(),
        Generators::string(),
        Generators::string(),
    )->then(function (string $name, string $password, string $rememberToken): void {
        $user = new User;
        $user->setRawAttributes([
            'name' => $name,
            'password' => $password,
            'remember_token' => $rememberToken,
        ]);

        expect($user->toArray())->toBe(['name' => $name]);
        expect(json_decode($user->toJson(), true, flags: JSON_THROW_ON_ERROR))->toBe(['name' => $name]);
    });
})->group('fuzz');

it('retains minimal secret-serialization regression cases', function (string $password): void {
    $user = new User;
    $user->setRawAttributes(['name' => '', 'password' => $password, 'remember_token' => '']);

    expect($user->toArray())->toBe(['name' => '']);
})->with(['empty secret' => [''], 'seed 20261015 shrunk secret' => ['?lLS']])->group('fuzz');
