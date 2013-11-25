<?php
namespace AcceptToGettext;

/*
 * accept-to-gettext.inc -- convert information in 'Accept-*' headers to
 * gettext language identifiers.
 * Copyright (c) 2003, Wouter Verhelst <wouter@debian.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * Usage:
 *
 *  $locale=al2gt(<array of supported languages/charsets in gettext syntax>,
 *                <MIME type of document>);
 *  setlocale('LC_ALL', $locale); // or 'LC_MESSAGES', or whatever...
 *
 * Example:
 *
 *  $langs=array('nl_BE.ISO-8859-15','nl_BE.UTF-8','en_US.UTF-8','en_GB.UTF-8');
 *  $locale=al2gt($langs, 'text/html');
 *  setlocale('LC_ALL', $locale);
 *
 * Note that this will send out header information (to be
 * RFC2616-compliant), so it must be called before anything is sent to
 * the user.
 *
 * Assumptions made:
 * * Charset encodings are written the same way as the Accept-Charset
 *   HTTP header specifies them (RFC2616), except that they're parsed
 *   case-insensitive.
 * * Country codes and language codes are the same in both gettext and
 *   the Accept-Language syntax (except for the case differences, which
 *   are dealt with easily). If not, some input may be ignored.
 * * The provided gettext-strings are fully qualified; i.e., no "en_US";
 *   always "en_US.ISO-8859-15" or "en_US.UTF-8", or whichever has been
 *   used. "en.ISO-8859-15" is OK, though.
 * * The language is more important than the charset; i.e., if the
 *   following is given:
 *
 *   Accept-Language: nl-be, nl;q=0.8, en-us;q=0.5, en;q=0.3
 *   Accept-Charset: ISO-8859-15, utf-8;q=0.5
 *
 *   And the supplied parameter contains (amongst others) nl_BE.UTF-8
 *   and nl.ISO-8859-15, then nl_BE.UTF-8 will be picked.
 *
 * $Log: accept-to-gettext.inc,v $
 * Revision 1.1.1.1  2003/11/19 19:31:15  wouter
 * * moved to new CVS repo after death of the old
 * * Fixed code to apply a default to both Accept-Charset and
 *   Accept-Language if none of those headers are supplied; patch from
 *   Dominic Chambers <dominic@encasa.com>
 *
 * Revision 1.2  2003/08/14 10:23:59  wouter
 * Removed little error in Content-Type header syntaxis.
 *
 * 2007-04-01
 * add '@' before use of arrays, to avoid PHP warnings.
 */


class Scorer
{

    public static function acceptToScores($header)
    {
        $parts = explode(',', $header);
        $alscores = array();
        foreach ($parts as $part) {
            $part = trim($part);
            if (false !== strpos($part, ';')) {
                $lang = array_map('trim', explode(';', $part));
                $score = explode('=', $lang[1]);
                $alscores[$lang[0]] = floatval(trim($score[1]));
            } else {
                $alscores[$part] = 1;
            }
        }
        return $alscores;
    }

    public static function explodeLocale($gettext_locale)
    {
        // pt-BR.UTF-8 for Brazilian Portuguese
        $parts = explode('.', str_replace('_', '-', $gettext_locale));
        $gettext_language_plus_country = strtolower($parts[0]);
        if (isset($parts[1])) {
            $gettext_charset = strtoupper($parts[1]);
        } else {
            $gettext_charset = 'UTF-8';
        }
        $gettext_parts = explode('-', $gettext_language_plus_country);
        $exploded = array(
            'language_plus_country' => $gettext_language_plus_country,
            'language' => $gettext_parts[0],
            'country' => $gettext_parts[1],
            'charset' => $gettext_charset
        );
        return $exploded;
    }

    /* not really important, this one; perhaps I could've put it inline with
     * the rest. */
    public static function findMatch(
        $best_language_score,
        $best_charset_score,
        $best_gettext_locale,
        $language_score,
        $charset_score,
        $gettext_locale
    ) {
        if ($best_language_score < $language_score) {
            $best_language_score = $language_score;
            $best_charset_score = $charset_score;
            $best_gettext_locale = $gettext_locale;
        } elseif ($best_language_score == $language_score) {
            if ($best_charset_score < $charset_score) {
                $best_charset_score = $charset_score;
                $best_gettext_locale = $gettext_locale;
            }
        }
        return array($best_language_score, $best_charset_score, $best_gettext_locale);
    }

    public static function pickLocale($gettext_locales, $headers)
    {
        /* default to "everything is acceptable", as RFC2616 specifies */
        $acceptLanguage = (
            empty($headers["HTTP_ACCEPT_LANGUAGE"])
            ? '*'
            : $headers["HTTP_ACCEPT_LANGUAGE"]
        );
        $acceptCharset = (
            empty($headers["HTTP_ACCEPT_CHARSET"])
            ? '*'
            : $headers["HTTP_ACCEPT_CHARSET"]
        );

        /* Parse the contents of the Accept-Language header.*/
        $accept_language_scores = self::acceptToScores($acceptLanguage);
        /* Do the same for the Accept-Charset header. */
        $accept_charset_scores = self::acceptToScores($acceptCharset);

        /* RFC2616: ``If no "*" is present in an Accept-Charset field, then
         * all character sets not explicitly mentioned get a quality value of
         * 0, except for ISO-8859-1, which gets a quality value of 1 if not
         * explicitly mentioned.''
         */
        if (!isset($accept_charset_scores["*"]) && !isset($accept_charset_scores["ISO-8859-1"])) {
            $accept_charset_scores["ISO-8859-1"] = 1;
        }

        /*
         * Loop through the available languages/encodings, and pick the one
         * with the highest score, excluding the ones with a charset the user
         * did not include.
         */
        $best_language_score = 0;
        $best_charset_score = 0;
        $best_gettext_locale = null;
        if ($gettext_locales) {
            $best_gettext_locale = $gettext_locales[0];
        } else {
            $best_gettext_locale = 'en_US';
        }

        foreach ($gettext_locales as $gettext_locale) {
            $exploded = self::explodeLocale($gettext_locale);
            $testkeys = array(
                array($exploded['language_plus_country'], $exploded['charset']),
                array($exploded['language'], $exploded['charset']),
                array($exploded['language_plus_country'], '*'),
                array($exploded['language'], '*'),
                array('*', $exploded['charset']),
                array('*', '*')
            );

            foreach ($testkeys as $keys) {
                $language_score = 0;
                if (isset($accept_language_scores[$keys[0]])) {
                    $language_score = $accept_language_scores[$keys[0]];
                }

                $charset_score = 0;
                if (isset($accept_language_scores[$keys[1]])) {
                    $charset_score = $accept_charset_scores[$keys[1]];
                }
                $arr = self::findMatch(
                    $best_language_score,
                    $best_charset_score,
                    $best_gettext_locale,
                    $language_score,
                    $charset_score,
                    $gettext_locale
                );
                $best_language_score = $arr[0];
                $best_charset_score = $arr[1];
                $best_gettext_locale = $arr[2];
            }
        }

        // We must re-parse the gettext-string now, since we may have found
        // it through a "*" qualifier.
        $best_exploded = self::explodeLocale($best_gettext_locale);
        return array(
            $best_exploded['language_plus_country'],
            $best_exploded['charset'],
            $best_gettext_locale
        );
    }

    public static function sendHeaders($lang, $mime, $charset)
    {
        header("Content-Language: $lang");
        header("Content-Type: $mime; charset=$charset");
    }

    public static function al2gt($gettext_locales, $mimetype)
    {
        $result = self::pickLocale($gettext_locales, $_SERVER);
        $lang = $result[0];
        $charset = $result[1];
        $best_gettext_locale = $result[3];
        self::sendHeaders($lang, $mimetype, $charset);
        return $best_gettext_locale;
    }
}
