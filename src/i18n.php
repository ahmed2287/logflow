<?php
declare(strict_types=1);

/**
 * Tiny i18n layer. Arabic is the source language and the translation key;
 * lang_en.php maps each Arabic string to English. Unknown keys fall back to
 * the Arabic text, so a missing translation is visible, never a crash.
 */

const APP_LANGS = ['ar', 'en'];

function lang(): string
{
    static $lang = null;
    if ($lang === null) {
        $cookie = (string)($_COOKIE['logflow_lang'] ?? $_COOKIE['almasrylog_lang'] ?? '');
        $lang   = in_array($cookie, APP_LANGS, true) ? $cookie : 'ar';
    }
    return $lang;
}

function lang_dir(): string
{
    return lang() === 'ar' ? 'rtl' : 'ltr';
}

function __(string $text): string
{
    if (lang() === 'ar') {
        return $text;
    }
    static $map = null;
    if ($map === null) {
        $map = is_file(__DIR__ . '/lang_en.php') ? (array)require __DIR__ . '/lang_en.php' : [];
    }
    return (string)($map[$text] ?? $text);
}
