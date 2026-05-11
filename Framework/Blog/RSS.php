<?php

namespace Framework\Blog;

/**
 * RSS Feed Generator
 * Generates RSS 2.0 feeds for articles, categories, or tags.
 *
 * Usage:
 * RSS::feed('My Blog', 'https://example.com', $articles);
 * RSS::download('My Blog', 'https://example.com', $articles);
 */
class RSS
{
    public static function feed(string $title, string $link, array $items, array $options = []): string
    {
        $description = $options['description'] ?? 'Latest articles';
        $language = $options['language'] ?? 'en-us';
        $imageUrl = $options['image'] ?? null;
        $author = $options['author'] ?? null;

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n";
        $xml .= "<channel>\n";
        $xml .= "  <title>" . self::escape($title) . "</title>\n";
        $xml .= "  <link>" . self::escape($link) . "</link>\n";
        $xml .= "  <description>" . self::escape($description) . "</description>\n";
        $xml .= "  <language>{$language}</language>\n";
        $xml .= "  <atom:link href=\"" . self::escape($link) . "/feed.xml\" rel=\"self\" type=\"application/rss+xml\"/>\n";
        $xml .= "  <lastBuildDate>" . date('r') . "</lastBuildDate>\n";

        if ($imageUrl) {
            $xml .= "  <image>\n";
            $xml .= "    <url>" . self::escape($imageUrl) . "</url>\n";
            $xml .= "    <title>" . self::escape($title) . "</title>\n";
            $xml .= "    <link>" . self::escape($link) . "</link>\n";
            $xml .= "  </image>\n";
        }

        foreach ($items as $item) {
            $data = is_array($item) ? $item : (method_exists($item, 'toArray') ? $item->toArray() : (array) $item);

            $itemLink = $data['url'] ?? (rtrim($link, '/') . '/article/' . ($data['slug'] ?? $data['id']));
            $pubDate = !empty($data['published_at']) ? date('r', strtotime($data['published_at'])) : date('r');

            $xml .= "  <item>\n";
            $xml .= "    <title>" . self::escape($data['title'] ?? 'Untitled') . "</title>\n";
            $xml .= "    <link>" . self::escape($itemLink) . "</link>\n";
            $xml .= "    <guid isPermaLink=\"false\">" . self::escape($data['id'] ?? uniqid()) . "</guid>\n";
            $xml .= "    <pubDate>{$pubDate}</pubDate>\n";

            if (!empty($data['excerpt'])) {
                $xml .= "    <description>" . self::escape($data['excerpt']) . "</description>\n";
            }
            if (!empty($data['content_html'] ?? $data['content'])) {
                $xml .= "    <content:encoded><![CDATA[" . ($data['content_html'] ?? $data['content']) . "]]></content:encoded>\n";
            }
            if (!empty($data['author_name'] ?? $data['guest_author_name'])) {
                $authorName = $data['author_name'] ?? $data['guest_author_name'];
                $authorEmail = $data['author_email'] ?? $data['guest_author_email'] ?? 'noreply@example.com';
                $xml .= "    <author>" . self::escape($authorEmail) . " (" . self::escape($authorName) . ")</author>\n";
            }

            if (!empty($data['cover_image'])) {
                $xml .= "    <enclosure url=\"" . self::escape($data['cover_image']) . "\" type=\"image/jpeg\"/>\n";
            }
            if (!empty($data['tags'])) {
                $tags = is_string($data['tags']) ? explode(',', $data['tags']) : $data['tags'];
                foreach ($tags as $tag) {
                    $xml .= "    <category>" . self::escape(trim($tag)) . "</category>\n";
                }
            }

            $xml .= "  </item>\n";
        }

        $xml .= "</channel>\n</rss>";
        return $xml;
    }

    public static function download(string $title, string $link, array $items, string $filename = 'feed.xml'): void
    {
        header('Content-Type: application/rss+xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo self::feed($title, $link, $items);
        exit;
    }

    public static function output(string $title, string $link, array $items): void
    {
        header('Content-Type: application/rss+xml; charset=UTF-8');
        echo self::feed($title, $link, $items);
        exit;
    }

    protected static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1, 'UTF-8');
    }
}
