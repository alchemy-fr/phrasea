<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL;

/**
 * The Elasticsearch analysis of the asset index (config/packages/fos_elastica.yaml) reproduced in PHP
 * for {@see AQLDocumentEvaluator}, on the inputs where it is exact only: any other input throws
 * {@see UnsupportedEvaluationException}.
 *
 * - "standard": the standard tokenizer (Unicode word boundaries) and the lowercase filter;
 * - "text": the standard tokenizer, then the elision, lowercase, word delimiter graph (word and number
 *   parts, catenated words, original preserved, English possessive removed) and ASCII folding filters.
 *
 * The supported characters are the Basic Latin and the Latin-1 and Latin Extended-A letters, plus the
 * usual punctuation; the language analyzers (stemming, stop words) are not reproduced.
 */
final class DocumentTextAnalyzer
{
    public const string STANDARD = 'standard';
    public const string TEXT = 'text';

    private const int MAX_TOKEN_LENGTH = 255;

    /**
     * The articles of the "elision" filter (Lucene FrenchAnalyzer::DEFAULT_ARTICLES, case insensitive).
     */
    private const array ELISION_ARTICLES = ['l', 'm', 't', 'qu', 'n', 's', 'j', 'd', 'c', 'jusqu', 'quoiqu', 'lorsqu', 'puisqu'];

    // Word break classes (UAX #29) of the supported characters
    private const string LETTER = 'L';
    private const string NUMERIC = 'N';
    private const string MID_LETTER = 'ML';
    private const string MID_NUM_LET = 'MNL';
    private const string MID_NUM = 'MN';
    private const string EXTEND_NUM_LET = 'EX';
    private const string OTHER = 'O';

    private const array CLASSES = [
        ':' => self::MID_LETTER,
        "\u{B7}" => self::MID_LETTER,
        '.' => self::MID_NUM_LET,
        "'" => self::MID_NUM_LET,
        "\u{2018}" => self::MID_NUM_LET,
        "\u{2019}" => self::MID_NUM_LET,
        ',' => self::MID_NUM,
        ';' => self::MID_NUM,
        '_' => self::EXTEND_NUM_LET,
    ];

    /**
     * Characters always breaking words, besides the ASCII spaces and punctuation.
     */
    private const string BREAKS = "\u{A0}\u{A7}\u{A9}\u{AB}\u{AE}\u{B0}\u{BB}\u{2013}\u{2014}\u{201C}\u{201D}\u{2026}\u{20AC}";

    /**
     * Latin letters whose case mapping or ASCII folding is not reproduced.
     */
    private const string UNSUPPORTED_LETTERS = "\u{130}\u{131}\u{138}\u{149}\u{17F}";

    /**
     * ASCII folding of the lowercase letters that have no canonical decomposition.
     */
    private const array FOLDING = [
        "\u{DF}" => 'ss',
        "\u{E6}" => 'ae',
        "\u{F0}" => 'd',
        "\u{F8}" => 'o',
        "\u{FE}" => 'th',
        "\u{111}" => 'd',
        "\u{127}" => 'h',
        "\u{133}" => 'ij',
        "\u{140}" => 'l',
        "\u{142}" => 'l',
        "\u{14B}" => 'n',
        "\u{153}" => 'oe',
        "\u{167}" => 't',
        "\u{2018}" => "'",
        "\u{2019}" => "'",
    ];

    /**
     * The tokens a path_hierarchy tokenizer ("/" delimiter) indexes: the path and each of its ancestors.
     *
     * @return string[]
     */
    public static function pathHierarchyTokens(string $path): array
    {
        if ('' === $path) {
            return [];
        }

        $tokens = [];
        $length = strlen($path);
        for ($i = 1; $i < $length; ++$i) {
            if ('/' === $path[$i]) {
                $tokens[] = substr($path, 0, $i);
            }
        }
        $tokens[] = $path;

        return array_values(array_unique($tokens));
    }

    /**
     * The terms the analyzer indexes for the values of a field.
     *
     * @param string[] $values
     *
     * @return array<string, true>
     */
    public static function indexTerms(string $analyzer, array $values): array
    {
        $terms = [];
        foreach ($values as $value) {
            foreach (self::analyze($analyzer, $value) as $word) {
                $terms[$word['original']] = true;
                foreach ($word['segments'] as $alternatives) {
                    foreach ($alternatives as $alternative) {
                        foreach ($alternative as $term) {
                            $terms[$term] = true;
                        }
                    }
                }
            }
        }

        return $terms;
    }

    /**
     * Whether a match query with the AND operator matches the terms of a field: each word of the
     * query must be indexed, as itself or through one of the paths of its token graph.
     *
     * @param array<string, true> $terms
     */
    public static function matches(string $analyzer, string $query, array $terms): bool
    {
        $words = self::analyze($analyzer, $query);
        if (empty($words)) {
            // zero_terms_query defaults to "none"
            return false;
        }

        foreach ($words as $word) {
            if (isset($terms[$word['original']])) {
                continue;
            }
            if (empty($word['segments'])) {
                return false;
            }
            foreach ($word['segments'] as $alternatives) {
                if (!array_any($alternatives, static fn (array $path): bool => array_all($path, static fn (string $t): bool => isset($terms[$t])))) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return list<array{original: string, segments: list<list<list<string>>>}> per word, its term and,
     *                                                                           when the word delimiter splits it, the alternatives of each segment of its graph
     */
    private static function analyze(string $analyzer, string $value): array
    {
        if (!in_array($analyzer, [self::STANDARD, self::TEXT], true)) {
            throw new UnsupportedEvaluationException(sprintf('The "%s" analyzer is not reproduced', $analyzer));
        }

        $words = [];
        foreach (self::tokenize($value) as $token) {
            if (self::STANDARD === $analyzer) {
                $words[] = ['original' => self::lowercase($token), 'segments' => []];

                continue;
            }

            $token = self::lowercase(self::elide($token));
            $parts = self::splitWord($token);
            $original = self::fold($token);
            if (1 === count($parts) && $parts[0][0] === $token) {
                $words[] = ['original' => $original, 'segments' => []];

                continue;
            }

            // Consecutive word parts form a run, which may also be matched by their catenation
            $segments = [];
            $runs = [];
            foreach ($parts as $i => [$part, $isWord]) {
                if (!$isWord) {
                    $segments[] = [[$part]];

                    continue;
                }
                $runs[count($segments)][] = self::fold($part);
                if ($parts[$i + 1][1] ?? false) {
                    continue;
                }
                $run = $runs[count($segments)];
                $segments[] = count($run) > 1 ? [$run, [implode('', $run)]] : [$run];
            }

            $words[] = ['original' => $original, 'segments' => $segments];
        }

        return $words;
    }

    /**
     * The words of the standard tokenizer, following the word boundary rules of UAX #29.
     *
     * @return string[]
     */
    private static function tokenize(string $value): array
    {
        $chars = mb_str_split($value);
        $classes = array_map(self::classify(...), $chars);
        $count = count($chars);
        $class = static fn (int $i): ?string => $classes[$i] ?? null;

        $tokens = [];
        $start = 0;
        for ($i = 1; $i <= $count; ++$i) {
            if ($i < $count && !self::isBreak($class($i - 2), $class($i - 1), $class($i), $class($i + 1))) {
                continue;
            }

            $segment = array_slice($classes, $start, $i - $start);
            if (in_array(self::LETTER, $segment, true) || in_array(self::NUMERIC, $segment, true)) {
                $token = implode('', array_slice($chars, $start, $i - $start));
                if (mb_strlen($token) > self::MAX_TOKEN_LENGTH) {
                    throw new UnsupportedEvaluationException('A word exceeds the maximum token length');
                }
                $tokens[] = $token;
            } elseif (in_array(self::EXTEND_NUM_LET, $segment, true)) {
                throw new UnsupportedEvaluationException('A word made of connectors only is not reproduced');
            }
            $start = $i;
        }

        return $tokens;
    }

    private static function isBreak(?string $before, ?string $left, string $right, ?string $after): bool
    {
        $isLetterOrNumber = static fn (?string $c): bool => self::LETTER === $c || self::NUMERIC === $c;

        return match (true) {
            // WB5, WB8, WB9, WB10
            $isLetterOrNumber($left) && $isLetterOrNumber($right) => false,
            // WB6, WB7
            self::LETTER === $left && in_array($right, [self::MID_LETTER, self::MID_NUM_LET], true) && self::LETTER === $after => false,
            in_array($left, [self::MID_LETTER, self::MID_NUM_LET], true) && self::LETTER === $before && self::LETTER === $right => false,
            // WB11, WB12
            self::NUMERIC === $left && in_array($right, [self::MID_NUM, self::MID_NUM_LET], true) && self::NUMERIC === $after => false,
            in_array($left, [self::MID_NUM, self::MID_NUM_LET], true) && self::NUMERIC === $before && self::NUMERIC === $right => false,
            // WB13a, WB13b
            self::EXTEND_NUM_LET === $right && ($isLetterOrNumber($left) || self::EXTEND_NUM_LET === $left) => false,
            self::EXTEND_NUM_LET === $left && $isLetterOrNumber($right) => false,
            default => true,
        };
    }

    private static function classify(string $char): string
    {
        if (isset(self::CLASSES[$char])) {
            return self::CLASSES[$char];
        }
        if (1 === strlen($char)) {
            return match (true) {
                ctype_alpha($char) => self::LETTER,
                ctype_digit($char) => self::NUMERIC,
                ctype_space($char), ctype_punct($char) => self::OTHER,
                default => throw new UnsupportedEvaluationException('Control characters are not reproduced'),
            };
        }
        if (str_contains(self::BREAKS, $char)) {
            return self::OTHER;
        }

        $codePoint = mb_ord($char);
        if ($codePoint >= 0xC0 && $codePoint <= 0x17F && 0xD7 !== $codePoint && 0xF7 !== $codePoint
            && !str_contains(self::UNSUPPORTED_LETTERS, $char)
        ) {
            return self::LETTER;
        }

        throw new UnsupportedEvaluationException(sprintf('The character U+%04X is not reproduced', $codePoint));
    }

    /**
     * The elision filter: an article followed by an apostrophe is removed.
     */
    private static function elide(string $token): string
    {
        if (!preg_match("/^([^'\u{2019}]*)['\u{2019}](.*)$/su", $token, $matches)) {
            return $token;
        }

        return in_array(self::lowercase($matches[1]), self::ELISION_ARTICLES, true) ? $matches[2] : $token;
    }

    private static function lowercase(string $value): string
    {
        return implode('', array_map(
            static fn (string $char): string => mb_chr(\IntlChar::tolower(mb_ord($char))),
            mb_str_split($value),
        ));
    }

    /**
     * The word delimiter graph filter on a lowercase token: the letter and digit runs, a word part
     * ("'s" of an English possessive excluded) or a number part each.
     *
     * @return list<array{string, bool}> the parts, true for the word ones
     */
    private static function splitWord(string $token): array
    {
        $chars = mb_str_split($token);
        $count = count($chars);
        $type = static fn (int $i): ?string => $i < $count
            ? (ctype_digit($chars[$i]) ? self::NUMERIC : (self::LETTER === self::classify($chars[$i]) ? self::LETTER : null))
            : null;

        $parts = [];
        $i = 0;
        while ($i < $count) {
            if (null === $type($i)) {
                ++$i;

                continue;
            }
            $start = $i;
            $partType = $type($i);
            while ($i < $count && $type($i) === $partType) {
                ++$i;
            }
            $parts[] = [implode('', array_slice($chars, $start, $i - $start)), self::LETTER === $partType];

            // An English possessive ending the part: "'s" followed by the end or a delimiter
            if (self::LETTER === $partType && "'" === ($chars[$i] ?? null)
                && in_array($chars[$i + 1] ?? null, ['s', 'S'], true)
                && ($i + 2 === $count || null === $type($i + 2))
            ) {
                $i += 2;
            }
        }

        return $parts;
    }

    private static function fold(string $token): string
    {
        $folded = '';
        foreach (mb_str_split($token) as $char) {
            if (1 === strlen($char)) {
                $folded .= $char;
            } elseif (isset(self::FOLDING[$char])) {
                $folded .= self::FOLDING[$char];
            } elseif ("\u{B7}" === $char) {
                throw new UnsupportedEvaluationException('The folding of U+00B7 is not reproduced');
            } else {
                $base = (string) preg_replace('/\p{Mn}+/u', '', (string) \Normalizer::normalize($char, \Normalizer::FORM_D));
                if (1 !== strlen($base)) {
                    throw new UnsupportedEvaluationException(sprintf('The folding of U+%04X is not reproduced', mb_ord($char)));
                }
                $folded .= $base;
            }
        }

        return $folded;
    }
}
