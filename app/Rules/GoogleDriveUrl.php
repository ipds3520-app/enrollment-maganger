<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GoogleDriveUrl implements ValidationRule
{
    public static function isValid(mixed $value): bool
    {
        if (! is_string($value) || strlen($value) > 255 || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($value);

        if (! is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        if ($host === 'drive.google.com') {
            return preg_match(
                '~\A/(?:file/d/[A-Za-z0-9_-]+(?:/[^?#]*)?|drive/(?:u/[0-9]+/)?folders/[A-Za-z0-9_-]+(?:/[^?#]*)?|open|uc)\z~D',
                $path,
            ) === 1
                && (! in_array($path, ['/open', '/uc'], true)
                    || preg_match('/(?:\A|&)id=[A-Za-z0-9_-]+(?:&|\z)/', $parts['query'] ?? '') === 1);
        }

        if ($host === 'docs.google.com') {
            return preg_match(
                '~\A/(?:document|spreadsheets|presentation|forms)/(?:u/[0-9]+/)?d/(?:e/)?[A-Za-z0-9_-]+(?:/[^?#]*)?\z~D',
                $path,
            ) === 1;
        }

        return false;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid($value)) {
            $fail('Masukkan link Google Drive atau Google Docs yang valid menggunakan HTTPS.');
        }
    }
}
