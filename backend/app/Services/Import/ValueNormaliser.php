<?php

namespace App\Services\Import;

use Illuminate\Support\Str;

/**
 * Cleans imported values.
 *
 * One rule governs all of it: **normalisation must not lose information.** A
 * phone number is reformatted because the reformatted version carries the same
 * digits; a name is never transliterated, because stripping the accents out of
 * "Ngo Bassa" would be corrupting the record we were asked to create. Where a
 * value cannot be confidently interpreted, it is passed through untouched and
 * the preview shows it as-is.
 */
class ValueNormaliser
{
    /**
     * Cameroon's country code. Numbers are stored in E.164 so the SMS gateway
     * and any future deduplication see one canonical form.
     */
    private const COUNTRY_CODE = '237';

    /**
     * Reformat a Cameroonian phone number to `+237XXXXXXXX`.
     *
     * Handles the forms schools actually export: `690123456`,
     * `+237 690 123 456`, `00237690123456`, `(+237) 6 90 12 34 56`. Anything
     * that does not resolve to nine local digits is returned trimmed but
     * otherwise unchanged — a wrong guess here would text the wrong parent.
     */
    public function phone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $digits = (string) preg_replace('/\D+/', '', $trimmed);

        if ($digits === '') {
            return $trimmed;
        }

        $local = match (true) {
            str_starts_with($digits, '00'.self::COUNTRY_CODE) => substr($digits, 2 + strlen(self::COUNTRY_CODE)),
            str_starts_with($digits, self::COUNTRY_CODE) && strlen($digits) === 9 + strlen(self::COUNTRY_CODE) => substr($digits, strlen(self::COUNTRY_CODE)),
            strlen($digits) === 9 => $digits,
            default => null,
        };

        if ($local === null) {
            return $trimmed;
        }

        return '+'.self::COUNTRY_CODE.$local;
    }

    public function email(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : Str::lower($trimmed);
    }

    public function text(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * A comparison key for matching free text against records the school already
     * has — class names, mostly. Folding accents here is safe because the key is
     * only ever compared, never stored.
     */
    public function matchKey(?string $value): string
    {
        $ascii = Str::ascii(trim((string) $value));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower($ascii)));
    }
}
