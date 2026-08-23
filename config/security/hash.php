<?php

declare(strict_types=1);

final class AdminPasswordHasher
{
    private const OPTIONS = [
        'memory_cost' => 19 * 1024,
        'time_cost'   => 2,
        'threads'     => 1,
    ];

    private const MAX_PASSWORD_BYTES = 4096;
    private const MAX_HASH_SECONDS = 2.0;

    public static function hash(string $plainPassword): string
    {
        self::assertSupported();
        self::assertValidPassword($plainPassword);

        $hash = password_hash(
            $plainPassword,
            constant('PASSWORD_ARGON2ID'),
            self::OPTIONS
        );

        if (!is_string($hash)) {
            throw new RuntimeException('Password hashing failed.');
        }

        return $hash;
    }

    public static function verify(string $plainPassword, string $storedHash): bool
    {
        if ($plainPassword === '' || $storedHash === '') {
            return false;
        }

        return password_verify($plainPassword, $storedHash);
    }

    public static function needsRehash(string $storedHash): bool
    {
        self::assertSupported();

        return password_needs_rehash(
            $storedHash,
            constant('PASSWORD_ARGON2ID'),
            self::OPTIONS
        );
    }

    /**
     * Verify a login and create an upgraded hash when settings have changed.
     *
     * @return array{valid: bool, new_hash: string|null}
     */
    public static function verifyAndRehash(
        string $plainPassword,
        string $storedHash
    ): array {
        if (!self::verify($plainPassword, $storedHash)) {
            return ['valid' => false, 'new_hash' => null];
        }

        $newHash = self::needsRehash($storedHash)
            ? self::hash($plainPassword)
            : null;

        return ['valid' => true, 'new_hash' => $newHash];
    }

    /**
     * Benchmark the real server. Run this during deployment, not per login.
     *
     * @return array<string, mixed>
     */
    public static function benchmark(int $runs = 3): array
    {
        if ($runs < 1 || $runs > 10) {
            throw new InvalidArgumentException('Benchmark runs must be 1 to 10.');
        }

        $durations = [];

        for ($i = 0; $i < $runs; $i++) {
            $testPassword = bin2hex(random_bytes(32));
            $startedAt = hrtime(true);

            self::hash($testPassword);

            $durations[] = (hrtime(true) - $startedAt) / 1_000_000_000;
        }

        $average = array_sum($durations) / count($durations);
        $maximum = max($durations);

        return [
            'algorithm'         => 'argon2id',
            'options'           => self::OPTIONS,
            'runs'              => $runs,
            'average_seconds'   => round($average, 4),
            'maximum_seconds'   => round($maximum, 4),
            'under_two_seconds' => $maximum <= self::MAX_HASH_SECONDS,
        ];
    }

    private static function assertSupported(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            throw new RuntimeException(
                'Argon2id is unavailable. Use PHP 7.3+ with Argon2 support enabled.'
            );
        }
    }

    private static function assertValidPassword(string $plainPassword): void
    {
        $length = strlen($plainPassword);

        if ($length === 0) {
            throw new InvalidArgumentException('Password cannot be empty.');
        }

        if ($length > self::MAX_PASSWORD_BYTES) {
            throw new InvalidArgumentException('Password is too long.');
        }
    }
}


