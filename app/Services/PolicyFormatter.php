<?php

namespace App\Services;

/**
 * Turns an admin-entered plain-text policy (privacy, returns, ...): title,
 * "Last Updated:", numbered sections, bulleted lines, paragraphs - into
 * renderable blocks so the shop page can style it instead of one wall of text.
 */
class PolicyFormatter
{
    /**
     * @return array{blocks: array<int, array<string, mixed>>, toc: array<int, array{num: string, title: string, id: string}>}
     */
    public static function format(?string $text): array
    {
        $lines = preg_split("/\r\n|\r|\n/", (string) $text) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), fn (string $l) => $l !== ''));

        $blocks = [];
        $toc    = [];
        $list   = [];

        $flush = function () use (&$blocks, &$list) {
            if ($list !== []) {
                $blocks[] = ['type' => 'list', 'items' => $list];
                $list = [];
            }
        };

        foreach ($lines as $i => $line) {
            if ($i === 0 && self::looksLikeTitle($line)) {
                $flush();
                $blocks[] = ['type' => 'title', 'text' => self::clean($line)];
                continue;
            }

            if (preg_match('/^last\s*updated\s*[:\-]?\s*(.*)$/iu', $line, $m)) {
                $flush();
                $blocks[] = ['type' => 'meta', 'text' => self::clean($m[1])];
                continue;
            }

            if (preg_match('/^(\d{1,2})\s*[.)]\s*(.+)$/u', $line, $m)) {
                $flush();
                $title = self::clean($m[2]);
                $id    = 'policy-section-' . $m[1];
                $toc[] = ['num' => $m[1], 'title' => $title, 'id' => $id];
                $blocks[] = ['type' => 'heading', 'num' => $m[1], 'title' => $title, 'id' => $id];
                continue;
            }

            if (($item = self::listItem($line)) !== null) {
                $list[] = $item;
                continue;
            }

            $flush();

            if (self::looksLikeSubheading($line)) {
                $blocks[] = ['type' => 'subheading', 'text' => self::clean($line)];
                continue;
            }

            $blocks[] = ['type' => 'p', 'text' => self::clean($line)];
        }

        $flush();

        return ['blocks' => $blocks, 'toc' => $toc];
    }

    private static function looksLikeTitle(string $line): bool
    {
        if (! preg_match('/\p{L}/u', $line)) {
            return false;
        }

        if (stripos($line, 'privacy policy') !== false) {
            return true;
        }

        $letters = preg_replace('/[^\p{L}]/u', '', $line);

        return $letters !== '' && mb_strtoupper($letters) === $letters;
    }

    /**
     * A bullet line starts with a symbol (•, ▪, □ - whatever the editor stored)
     * or reads like a semicolon-terminated list entry.
     */
    private static function listItem(string $line): ?string
    {
        if (preg_match('/^([^\p{L}\p{N}\s])(?:[^\p{L}\p{N}]{0,3})\s*(\S.*)$/u', $line, $m)) {
            if (! preg_match('/^["\'“”‘’«»()\[\]{}]+$/u', $m[1])) {
                return self::clean($m[2]);
            }
        }

        if (preg_match('/;$/', $line) && mb_strlen($line) < 160) {
            return self::clean($line);
        }

        return null;
    }

    private static function looksLikeSubheading(string $line): bool
    {
        if (mb_strlen($line) > 60 || preg_match('/[.,;:!?]$/u', $line)) {
            return false;
        }

        // ALL CAPS, or two+ Title-Case words: "Contact Us", "CHILDREN'S PRIVACY"
        $letters = preg_replace('/[^\p{L}]/u', '', $line);
        if ($letters !== '' && mb_strtoupper($letters) === $letters) {
            return true;
        }

        return (bool) preg_match(
            '/^[\p{Lu}][\p{L}\d’\'&-]*(\s+[\p{Lu}][\p{L}\d’\'&-]*)+$/u',
            $line
        );
    }

    private static function clean(string $text): string
    {
        return trim(preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}]/u', '', $text) ?? $text);
    }
}
