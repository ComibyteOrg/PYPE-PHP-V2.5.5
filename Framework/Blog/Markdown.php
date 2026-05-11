<?php

namespace Framework\Blog;

/**
 * Markdown Parser
 * Parses Markdown to HTML with code block highlighting support.
 * Zero external dependencies.
 *
 * Usage:
 * $html = Markdown::parse('# Hello **World**');
 * $html = Markdown::parseFile('post.md');
 */
class Markdown
{
    public static function parse(string $markdown): string
    {
        $lines = explode("\n", $markdown);
        $html = '';
        $inCode = false;
        $codeBlock = '';
        $codeLang = '';

        foreach ($lines as $line) {
            // Code blocks
            if (preg_match('/^```(\w+)?/', $line, $matches)) {
                if (!$inCode) {
                    $inCode = true;
                    $codeLang = $matches[1] ?? 'text';
                    $codeBlock = '';
                    continue;
                } else {
                    $html .= self::renderCodeBlock($codeBlock, $codeLang);
                    $inCode = false;
                    $codeBlock = '';
                    continue;
                }
            }

            if ($inCode) {
                $codeBlock .= htmlspecialchars($line) . "\n";
                continue;
            }

            // Headers
            if (preg_match('/^(#{1,6})\s+(.*)/', $line, $matches)) {
                $level = strlen($matches[1]);
                $text = self::parseInline($matches[2]);
                $id = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $matches[2]), '-'));
                $html .= "<h{$level} id=\"{$id}\">{$text}</h{$level}>\n";
                continue;
            }

            // Horizontal rule
            if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $line)) {
                $html .= "<hr>\n";
                continue;
            }

            // Blockquote
            if (preg_match('/^>\s?(.*)/', $line, $matches)) {
                $html .= "<blockquote>" . self::parseInline($matches[1]) . "</blockquote>\n";
                continue;
            }

            // Unordered list
            if (preg_match('/^[\*\-]\s+(.*)/', $line, $matches)) {
                $html .= "<li>" . self::parseInline($matches[1]) . "</li>\n";
                continue;
            }

            // Ordered list
            if (preg_match('/^\d+\.\s+(.*)/', $line, $matches)) {
                $html .= "<li>" . self::parseInline($matches[1]) . "</li>\n";
                continue;
            }

            // Empty line
            if (trim($line) === '') {
                $html .= "\n";
                continue;
            }

            // Paragraph
            $html .= "<p>" . self::parseInline($line) . "</p>\n";
        }

        // Wrap consecutive <li> in <ul>/<ol>
        $html = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $html);
        $html = str_replace("<ul><ul>", "<ul>", $html);
        $html = str_replace("</ul></ul>", "</ul>", $html);

        return trim($html);
    }

    public static function parseFile(string $path): string
    {
        if (!file_exists($path)) {
            return '';
        }
        return self::parse(file_get_contents($path));
    }

    protected static function parseInline(string $text): string
    {
        // Images
        $text = preg_replace('/!\[([^\]]+)\]\(([^)]+)\)/', '<img src="$2" alt="$1">', $text);

        // Links
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text);

        // Bold & Italic
        $text = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);

        // Inline code
        $text = preg_replace('/`([^`]+)`/', '<code class="inline">$1</code>', $text);

        // Strikethrough
        $text = preg_replace('/~~(.+?)~~/', '<del>$1</del>', $text);

        // Line breaks
        $text = nl2br($text);

        return $text;
    }

    protected static function renderCodeBlock(string $code, string $lang): string
    {
        $code = rtrim($code);
        $highlighted = self::highlightSyntax($code, $lang);
        return "<pre><code class=\"language-{$lang}\">{$highlighted}</code></pre>\n";
    }

    protected static function highlightSyntax(string $code, string $lang): string
    {
        // Basic syntax highlighting for common languages
        if ($lang === 'php') {
            $code = preg_replace('/\b(function|class|interface|trait|public|protected|private|static|const|return|if|else|elseif|for|foreach|while|do|switch|case|break|continue|new|use|namespace|extends|implements|throw|try|catch|finally|void|null|true|false|array|string|int|float|bool|object|mixed|self|parent)\b/', '<span class="keyword">$1</span>', $code);
            $code = preg_replace('/\/\/.*/', '<span class="comment">$0</span>', $code);
            $code = preg_replace('/\/\*.*?\*\//s', '<span class="comment">$0</span>', $code);
            $code = preg_replace('/(["\'])(.*?)(?<!\\\\)\1/', '<span class="string">$0</span>', $code);
        } elseif ($lang === 'js' || $lang === 'javascript') {
            $code = preg_replace('/\b(const|let|var|function|class|return|if|else|for|while|switch|case|break|continue|new|import|export|from|async|await|try|catch|finally|throw|this|true|false|null|undefined|void|typeof|instanceof)\b/', '<span class="keyword">$1</span>', $code);
            $code = preg_replace('/\/\/.*/', '<span class="comment">$0</span>', $code);
            $code = preg_replace('/\/\*.*?\*\//s', '<span class="comment">$0</span>', $code);
            $code = preg_replace('/(["\'`])(.*?)(?<!\\\\)\1/', '<span class="string">$0</span>', $code);
        } elseif ($lang === 'html' || $lang === 'xml') {
            $code = preg_replace('/(&lt;\/?)([\w-]+)/', '$1<span class="tag">$2</span>', $code);
            $code = preg_replace('/([\w-]+)=/', '<span class="attr">$1</span>=', $code);
        } elseif ($lang === 'sql') {
            $code = preg_replace('/\b(SELECT|FROM|WHERE|INSERT|UPDATE|DELETE|JOIN|LEFT|RIGHT|INNER|OUTER|ON|GROUP|BY|ORDER|HAVING|LIMIT|AS|AND|OR|NOT|NULL|IS|IN|LIKE|BETWEEN|COUNT|SUM|AVG|MAX|MIN)\b/i', '<span class="keyword">$1</span>', $code);
        }

        return $code;
    }
}
