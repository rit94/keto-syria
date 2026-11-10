<?php

namespace App\Livewire\Coach\Support;

/**
 * The tiny markdown subset the coach answers in: paragraphs, "-" / "1." lists and **bold**.
 * Everything is HTML-escaped first, so the result is safe to print unescaped.
 */
final class Markdown
{
    public static function render(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') {
            return '';
        }

        $html = [];
        $list = null; // 'ul' | 'ol'
        $paragraph = [];

        $flushParagraph = function () use (&$html, &$paragraph) {
            if ($paragraph !== []) {
                $html[] = '<p>'.implode('<br>', $paragraph).'</p>';
                $paragraph = [];
            }
        };
        $closeList = function () use (&$html, &$list) {
            if ($list !== null) {
                $html[] = "</$list>";
                $list = null;
            }
        };

        foreach (explode("\n", $text) as $line) {
            $line = rtrim($line);

            if (trim($line) === '') {
                $flushParagraph();
                $closeList();

                continue;
            }

            if (preg_match('/^\s*(?:[-*•]|\d+[.)])\s+(.*)$/u', $line, $m) === 1) {
                $flushParagraph();
                $type = preg_match('/^\s*\d+[.)]/u', $line) === 1 ? 'ol' : 'ul';
                if ($list !== $type) {
                    $closeList();
                    $html[] = "<$type>";
                    $list = $type;
                }
                $html[] = '<li>'.self::inline($m[1]).'</li>';

                continue;
            }

            $closeList();

            if (preg_match('/^\s*#{1,6}\s+(.*)$/u', $line, $m) === 1) {
                $flushParagraph();
                $html[] = '<p><strong>'.self::inline($m[1]).'</strong></p>';

                continue;
            }

            $paragraph[] = self::inline($line);
        }

        $flushParagraph();
        $closeList();

        return implode('', $html);
    }

    private static function inline(string $text): string
    {
        $text = e($text);

        return preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $text) ?? $text;
    }
}
