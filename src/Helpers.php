<?php

declare(strict_types=1);

namespace Baraja\Search;


final class Helpers
{

	/**
	 * @throws \Error
	 */
	public function __construct()
	{
		throw new \Error('Class ' . get_class($this) . ' is static and cannot be instantiated.');
	}

	/**
	 * @param string $class
	 * @return \ReflectionClass
	 * @throws \ReflectionException
	 */
	public static function getReflectionClass(string $class): \ReflectionClass
	{
		static $cache = [];

		if (isset($cache[$class]) === false) {
			$cache[$class] = new \ReflectionClass($class);
		}

		return $cache[$class];
	}

	/**
	 * Create feature snippet by inner magic.
	 *
	 * @param string $query
	 * @param string $haystack
	 * @param int $len
	 * @return string
	 */
	public static function smartTruncate(string $query, string $haystack, int $len = 60): string
	{
		if (\strlen($haystack) < $len + 5) {
			return $haystack;
		}

		if (\strlen($query) > 25) {
			return self::truncate($haystack, $len);
		}

		$words = explode(' ', $query);
		$candidates = [];
		$start = 0;

		while (true) {
			$part = substr($haystack, $start, $len);
			$contains = false;
			$containsCount = 0;
			foreach ($words as $word) {
				$word = trim($word);
				if ($word && stripos($part, $word) !== false) {
					$contains = true;
					$containsCount++;
				}
			}

			if ($contains === true) {
				$candidates[$containsCount] = $part;
			}

			if (isset($haystack[$start + $len + 1])) {
				$start++;
			} else {
				break;
			}
		}

		$finalString = self::truncate($haystack, $len);
		$finalStringCount = -1;

		foreach ($candidates as $key => $value) {
			if ($key > $finalStringCount) {
				$finalStringCount = $key;
				$finalString = $value;
			}
		}

		return \strlen($haystack) > \strlen($finalString)
			? '... ' . $finalString . ' ...'
			: $finalString;
	}

	/**
	 * @param string $text
	 * @param string $words
	 * @param string|null $replaceHtml
	 * @param bool|null $caseSensitive
	 * @return string
	 */
	public static function highlightFoundWords(string $text, string $words, ?string $replaceHtml = null, ?bool $caseSensitive = null): string
	{
		if (($words = trim($words)) === '') {
			return $text;
		}

		$result = $text;
		$words = (string) preg_replace('/\s+/', ' ', $words);

		foreach (array_unique(explode(' ', $caseSensitive === true ? $words : mb_strtolower($words))) as $word) {
			$result = self::replaceAndIgnoreAccent($word, $replaceHtml ?? '<i class="highlight">\\0</i>', $result);
		}

		return $result;
	}

	/**
	 * Replace $from => $to, in $string. Helper for national characters.
	 *
	 * @param string $from
	 * @param string $to
	 * @param string $string
	 * @param bool $caseSensitive
	 * @return string
	 */
	public static function replaceAndIgnoreAccent(string $from, string $to, string $string, bool $caseSensitive = false): string
	{
		$from = preg_quote(self::toAscii($from), '/');
		$from = $caseSensitive === false ? (string) mb_strtolower($from) : $from;

		$fromPattern = str_replace(
			['a', 'c', 'd', 'e', 'i', 'l', 'n', 'o', 'r', 's', 't', 'u', 'y', 'z'],
			['[aáä]', '[cč]', '[dď]', '[eèêéě]', '[ií]', '[lĺľ]', '[nň]', '[oô]', '[rŕř]', '[sśš]', '[tť]', '[uúů]', '[yý]', '[zžź]'],
			$from
		);

		$result = (string) preg_replace(
			'/(' . $fromPattern . ')(?=[^>]*(<|$))/smu' . ($caseSensitive === false ? 'i' : ''),
			$to,
			$string
		);

		return $result ? : $string;
	}

	/**
	 * @param string $searchTerm
	 * @param string[] $columns
	 * @param bool $ignoreAccents
	 * @param callable|null $quoteCallback
	 * @return string
	 * @throws SearchException
	 */
	public static function generateFulltextCondition(string $searchTerm, array $columns, bool $ignoreAccents = false, ?callable $quoteCallback = null): string
	{
		if (!\is_array($columns) || empty($columns) || trim($searchTerm) === '') {
			return '1=1';
		}

		if ($ignoreAccents === true) {
			$searchTerm = self::toAscii($searchTerm);
		}

		$result = '';
		$searchTerm = str_replace(['.', '?'], ['. ', ' '], $searchTerm);
		$words = explode(' ', trim((string) preg_replace('/\s+/', ' ', $searchTerm)));

		foreach (\is_array($words) ? $words : [] as $word) {
			$result .= "\n" . ' AND (';
			foreach ($columns as $column) {
				if (@preg_match('/^[a-z0-9\.\_\-\@\(\)\'\, ]{1,100}$/i', $column) !== 1) {
					throw new SearchException('Invalid column name "' . $column . '".');
				}

				$quotedWord = $quoteCallback !== null
					? $quoteCallback('%' . $word . '%')
					: '\'%' . addslashes($word) . '%\'';

				$result .= $column . ' LIKE ' . $quotedWord . ' OR ';
			}
			$result = substr($result, 0, -4);
			$result .= ')';
		}

		return (string) preg_replace('/^\s*AND/', '', $result);
	}

	/**
	 * Moved from nette/utils.
	 *
	 * Returns a part of UTF-8 string.
	 *
	 * @param string $s
	 * @param int $start
	 * @param int|null $length
	 * @return string
	 */
	public static function substring(string $s, int $start, ?int $length = null): string
	{
		if (function_exists('mb_substr')) {
			return mb_substr($s, $start, $length, 'UTF-8'); // MB is much faster
		}

		if ($length === null) {
			$length = self::length($s);
		} elseif ($start < 0 && $length < 0) {
			$start += self::length($s); // unifies iconv_substr behavior with mb_substr
		}

		return iconv_substr($s, $start, $length, 'UTF-8');
	}

	/**
	 * Moved from nette/utils.
	 *
	 * Removes special controls characters and normalizes line endings, spaces and normal form to NFC in UTF-8 string.
	 *
	 * @param string $s
	 * @return string
	 */
	public static function normalize(string $s): string
	{
		// convert to compressed normal form (NFC)
		if (class_exists('Normalizer', false) && ($n = \Normalizer::normalize($s, \Normalizer::FORM_C)) !== false) {
			$s = $n;
		}

		$s = str_replace(["\r\n", "\r"], "\n", $s);

		// remove control characters; leave \t + \n
		$s = (string) preg_replace('#[\x00-\x08\x0B-\x1F\x7F-\x9F]+#u', '', $s);

		// right trim
		$s = (string) preg_replace('#[\t ]+$#m', '', $s);

		// leading and trailing blank lines
		$s = trim($s, "\n");

		return $s;
	}

	/**
	 * Moved from nette/utils.
	 *
	 * Converts first character to upper case.
	 *
	 * @param string $s
	 * @return string
	 */
	public static function firstUpper(string $s): string
	{
		return mb_strtoupper(self::substring($s, 0, 1), 'UTF-8') . self::substring($s, 1);
	}

	/**
	 * Moved from nette/utils.
	 *
	 * Truncates UTF-8 string to maximal length.
	 *
	 * @param string $s
	 * @param int $maxLen
	 * @param string $append
	 * @return string
	 */
	public static function truncate(string $s, int $maxLen, string $append = "\u{2026}"): string
	{
		if (self::length($s) > $maxLen) {
			if (($maxLen -= self::length($append)) < 1) {
				return $append;
			}

			if (preg_match('#^.{1,' . $maxLen . '}(?=[\s\x00-/:-@\[-`{-~])#us', $s, $matches)) {
				return $matches[0] . $append;
			}

			return self::substring($s, 0, $maxLen) . $append;
		}

		return $s;
	}

	/**
	 * Moved from nette/utils.
	 *
	 * Returns number of characters (not bytes) in UTF-8 string.
	 * That is the number of Unicode code points which may differ from the number of graphemes.
	 *
	 * @param string $s
	 * @return int
	 */
	public static function length(string $s): int
	{
		return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen(utf8_decode($s));
	}

	/**
	 * Moved from nette/utils.
	 *
	 * Converts UTF-8 string to ASCII.
	 *
	 * @param string $s
	 * @return string
	 */
	public static function toAscii(string $s): string
	{
		static $transliterator = null;
		if ($transliterator === null && class_exists('Transliterator', false)) {
			$transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
		}

		$s = preg_replace('#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{2FF}\x{370}-\x{10FFFF}]#u', '', $s);
		$s = strtr($s, '`\'"^~?', "\x01\x02\x03\x04\x05\x06");
		$s = (string) str_replace(
			["\u{201E}", "\u{201C}", "\u{201D}", "\u{201A}", "\u{2018}", "\u{2019}", "\u{B0}"],
			["\x03", "\x03", "\x03", "\x02", "\x02", "\x02", "\x04"], $s
		);
		if ($transliterator !== null) {
			$s = $transliterator->transliterate($s);
		}
		if (ICONV_IMPL === 'glibc') {
			$s = (string) str_replace(
				["\u{BB}", "\u{AB}", "\u{2026}", "\u{2122}", "\u{A9}", "\u{AE}"],
				['>>', '<<', '...', 'TM', '(c)', '(R)'], $s
			);
			$s = iconv('UTF-8', 'WINDOWS-1250//TRANSLIT//IGNORE', $s);
			$s = strtr($s, "\xa5\xa3\xbc\x8c\xa7\x8a\xaa\x8d\x8f\x8e\xaf\xb9\xb3\xbe\x9c\x9a\xba\x9d\x9f\x9e"
				. "\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3"
				. "\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8"
				. "\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xfe"
				. "\x96\xa0\x8b\x97\x9b\xa6\xad\xb7",
				'ALLSSSSTZZZallssstzzzRAAAALCCCEEEEIIDDNNOOOOxRUUUUYTsraaaalccceeeeiiddnnooooruuuuyt- <->|-.');
			$s = (string) preg_replace('#[^\x00-\x7F]++#', '', $s);
		} else {
			$s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
		}

		$s = (string) str_replace(['`', "'", '"', '^', '~', '?'], '', $s);

		return strtr($s, "\x01\x02\x03\x04\x05\x06", '`\'"^~?');
	}

}