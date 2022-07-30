<?php
/**
 * Wrapper
 *
 * @category Mailslot
 * @package Mailslot
 * @copyright  Copyright (c) 2022 Tomohisa Oda <linyows@gmail.com>
 * @license MIT
 */

namespace Mailslot;

class Wrapper
{
    public static function header(string $arg): void
    {
        header($arg);
    }

    public static function exit(int $arg): void
    {
        exit($arg);
    }

    public static function mb_send_mail(string $to, string $subject, string $body, array $headers): bool
    {
        return mb_send_mail($to, $subject, $body, $headers);
    }

    public static function curl_init(string $url): \CurlHandle
    {
        return curl_init($url);
    }

    public static function curl_exec(\CurlHandle $handle): string|bool
    {
        return curl_exec($handle);
    }

    public static function curl_setopt(\CurlHandle $handle, int $option, mixed $value): bool
    {
        return curl_setopt($handle, $option, $value);
    }

    public static function curl_close(\CurlHandle $handle): void
    {
        curl_close($handle);
    }

    public static function json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
    {
        return json_encode($value, $flags, $depth);
    }
}
