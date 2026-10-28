<?php

namespace App\Services\Mail;

use DOMDocument;
use DOMNode;
use DOMElement;

class MailTemplateContent
{
    /**
     * Render editor HTML using a narrow email-safe tag allowlist. Text-only legacy
     * templates are supported without requiring a backfill.
     */
    public static function html(string $content): string
    {
        if (! preg_match('~</?[a-z][^>]*>~i', $content)) {
            return nl2br(e($content));
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8"><html><body>'.$content.'</body></html>', LIBXML_NONET);
            $out = '';
            foreach ($dom->getElementsByTagName('body')->item(0)?->childNodes ?? [] as $child) {
                $out .= self::node($child);
            }
            return $out;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public static function plainText(string $content): string
    {
        if (! preg_match('~</?[a-z][^>]*>~i', $content)) {
            return $content;
        }

        // Sanitize before extracting readable text, so script/style content is discarded.
        $safe = self::html($content);
        $safe = preg_replace('~</?(?:p|div|h[1-6]|li|br|blockquote)[^>]*>~i', "\n", $safe);
        return trim(html_entity_decode(strip_tags($safe), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function node(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return e($node->nodeValue ?? '');
        }
        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'form', 'object', 'svg', 'img'], true)) {
            return '';
        }
        $allowed = ['p', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 'br', 'ul', 'ol', 'li', 'blockquote', 'h1', 'h2', 'h3', 'a'];
        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= self::node($child);
        }
        if (! in_array($tag, $allowed, true)) {
            return $inner;
        }
        if ($tag === 'br') {
            return '<br>';
        }
        $attr = '';
        if ($tag === 'a') {
            $href = trim($node->getAttribute('href'));
            if (preg_match('~^(https?://|mailto:)~i', $href)) {
                $attr = ' href="'.e($href).'"';
            } else {
                return $inner;
            }
        }
        return '<'.$tag.$attr.'>'.$inner.'</'.$tag.'>';
    }
}
