<?php

declare(strict_types=1);

namespace Larapilot\Support;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Time-based one-time passwords (RFC 6238): HMAC-SHA1, six digits, a new
 * code every thirty seconds — what Google Authenticator, 1Password, Authy,
 * Microsoft Authenticator, and every other app make of an otpauth:// URI.
 * No dependency: the secret is base32, the code is HOTP of the time step.
 */
final class Totp
{
    public const DIGITS = 6;

    public const PERIOD = 30;

    /**
     * Steps of drift accepted on either side of the clock: a code typed
     * a little early or a little late still passes.
     */
    public const WINDOW = 1;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * A fresh secret: 160 random bits, as the apps expect, in base32.
     */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function step(?int $timestamp = null): int
    {
        return intdiv($timestamp ?? Carbon::now()->getTimestamp(), self::PERIOD);
    }

    /**
     * The code an authenticator shows for one step.
     *
     * @throws InvalidArgumentException when the secret is not base32
     */
    public static function code(string $secret, int $step): string
    {
        $key = self::base32Decode($secret);
        $counter = pack('NN', ($step >> 32) & 0xFFFFFFFF, $step & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * The step a code was made for, when it is good now — within WINDOW
     * steps of the clock — and newer than the last step that let the user
     * in, so a code is good once. Null when it is not.
     */
    public static function verify(string $secret, string $code, ?int $lastStep = null, ?int $timestamp = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (preg_match('/^\d{'.self::DIGITS.'}$/', $code) !== 1) {
            return null;
        }

        try {
            $now = self::step($timestamp);

            for ($delta = -self::WINDOW; $delta <= self::WINDOW; $delta++) {
                $step = $now + $delta;

                if ($lastStep !== null && $step <= $lastStep) {
                    continue;
                }

                if (hash_equals(self::code($secret, $step), $code)) {
                    return $step;
                }
            }
        } catch (InvalidArgumentException) {
            return null;
        }

        return null;
    }

    /**
     * What the QR code tells the app: the issuer, the account, the secret.
     */
    public static function uri(string $issuer, string $account, string $secret): string
    {
        $issuer = trim(str_replace(':', ' ', $issuer));
        $issuer = $issuer === '' ? 'Larapilot' : $issuer;
        $account = trim(str_replace(':', ' ', $account));

        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($account)
            .'?secret='.$secret
            .'&issuer='.rawurlencode($issuer)
            .'&algorithm=SHA1&digits='.self::DIGITS.'&period='.self::PERIOD;
    }

    /**
     * The secret in groups of four, for whoever types it instead of scanning.
     */
    public static function pretty(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    public static function base32Encode(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    /**
     * @throws InvalidArgumentException when the text is not base32
     */
    public static function base32Decode(string $encoded): string
    {
        $clean = strtoupper(preg_replace('/[\s=-]/', '', $encoded) ?? '');

        if ($clean === '' || preg_match('/^[A-Z2-7]+$/', $clean) !== 1) {
            throw new InvalidArgumentException('Not a base32 secret.');
        }

        $bits = '';

        foreach (str_split($clean) as $char) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
