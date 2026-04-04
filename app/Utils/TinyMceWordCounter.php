<?php

namespace App\Utils;

use DOMDocument;
use DOMNode;
use DOMText;
use DOMXPath;

class TinyMceWordCounter
{
    private const WORD_PATTERN = '/https?:\/\/[^\s]+|(?:\p{L}\.){2,}|[\p{L}\p{N}\p{M}]+(?:[-\'’‘⁄=_][\p{L}\p{N}\p{M}]+|[.:](?=[\p{L}\p{N}\p{M}])[\p{L}\p{N}\p{M}]+|,(?=\p{N})[\p{N}]+)*/u';

    private const BLOCK_ELEMENTS = [
        'address' => true,
        'article' => true,
        'aside' => true,
        'blockquote' => true,
        'canvas' => true,
        'dd' => true,
        'div' => true,
        'dl' => true,
        'dt' => true,
        'fieldset' => true,
        'figcaption' => true,
        'figure' => true,
        'footer' => true,
        'form' => true,
        'h1' => true,
        'h2' => true,
        'h3' => true,
        'h4' => true,
        'h5' => true,
        'h6' => true,
        'header' => true,
        'hr' => true,
        'li' => true,
        'main' => true,
        'nav' => true,
        'noscript' => true,
        'ol' => true,
        'p' => true,
        'pre' => true,
        'section' => true,
        'table' => true,
        'tfoot' => true,
        'ul' => true,
        'video' => true,
    ];

    private const VOID_ELEMENTS = [
        'area' => true,
        'base' => true,
        'br' => true,
        'col' => true,
        'embed' => true,
        'hr' => true,
        'img' => true,
        'input' => true,
        'link' => true,
        'meta' => true,
        'param' => true,
        'source' => true,
        'track' => true,
        'wbr' => true,
    ];

    public static function countWords(?string $content): int
    {
        $text = self::extractText($content);

        if ($text === '') {
            return 0;
        }

        preg_match_all(self::WORD_PATTERN, $text, $matches);

        return count($matches[0]);
    }

    private static function extractText(?string $content): string
    {
        if (blank($content)) {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $html = '<!DOCTYPE html><html><body>'.$content.'</body></html>';
        $dom->loadHTML(mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, ~0], 'UTF-8'));

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $dom->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMNode) {
            return '';
        }

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('.//node()', $body);
        $textBlocks = [];
        $buffer = '';

        foreach ($nodes ?: [] as $node) {
            if ($node instanceof DOMText) {
                $buffer .= str_replace("\u{FEFF}", '', $node->nodeValue ?? '');

                continue;
            }

            if (self::isNewlineNode($node) && $buffer !== '') {
                $textBlocks[] = $buffer;
                $buffer = '';
            }
        }

        if ($buffer !== '') {
            $textBlocks[] = $buffer;
        }

        return str_replace("\u{200B}", '', implode("\n", $textBlocks));
    }

    private static function isNewlineNode(DOMNode $node): bool
    {
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return false;
        }

        $name = strtolower($node->nodeName);

        return isset(self::BLOCK_ELEMENTS[$name]) || isset(self::VOID_ELEMENTS[$name]);
    }
}
