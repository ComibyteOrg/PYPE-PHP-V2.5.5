<?php

namespace Framework\Blog;

/**
 * SEO Toolkit
 * Generates meta tags, Open Graph, Twitter Cards, JSON-LD structured data, and sitemaps.
 *
 * Usage:
 * SEO::setTitle('My Article')
 *     ->setDescription('A great article about...')
 *     ->setUrl('https://example.com/post')
 *     ->setImage('https://example.com/cover.jpg')
 *     ->generateMeta();
 *
 * Sitemap::generate($articles, 'https://example.com');
 */
class SEO
{
    protected string $title = '';
    protected string $description = '';
    protected string $url = '';
    protected ?string $image = null;
    protected string $type = 'article';
    protected string $locale = 'en_US';
    protected string $siteName = '';
    protected array $keywords = [];
    protected array $customMeta = [];

    public static function make(): self
    {
        return new self();
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function setDescription(string $desc): self
    {
        $this->description = substr($desc, 0, 300);
        return $this;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function setImage(string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function setSiteName(string $name): self
    {
        $this->siteName = $name;
        return $this;
    }

    public function setKeywords(array $keywords): self
    {
        $this->keywords = $keywords;
        return $this;
    }

    public function addMeta(string $name, string $content): self
    {
        $this->customMeta[$name] = $content;
        return $this;
    }

    public function generateMeta(): string
    {
        $meta = [];

        // Core Meta
        if ($this->title) $meta[] = '<title>' . htmlspecialchars($this->title) . '</title>';
        if ($this->description) $meta[] = '<meta name="description" content="' . htmlspecialchars($this->description) . '">';
        if (!empty($this->keywords)) $meta[] = '<meta name="keywords" content="' . htmlspecialchars(implode(', ', $this->keywords)) . '">';
        $meta[] = '<meta name="viewport" content="width=device-width, initial-scale=1">';
        $meta[] = '<meta charset="UTF-8">';

        // Canonical
        if ($this->url) $meta[] = '<link rel="canonical" href="' . htmlspecialchars($this->url) . '">';

        // Open Graph
        if ($this->title) $meta[] = '<meta property="og:title" content="' . htmlspecialchars($this->title) . '">';
        if ($this->description) $meta[] = '<meta property="og:description" content="' . htmlspecialchars($this->description) . '">';
        if ($this->url) $meta[] = '<meta property="og:url" content="' . htmlspecialchars($this->url) . '">';
        if ($this->type) $meta[] = '<meta property="og:type" content="' . htmlspecialchars($this->type) . '">';
        if ($this->image) $meta[] = '<meta property="og:image" content="' . htmlspecialchars($this->image) . '">';
        if ($this->siteName) $meta[] = '<meta property="og:site_name" content="' . htmlspecialchars($this->siteName) . '">';
        if ($this->locale) $meta[] = '<meta property="og:locale" content="' . htmlspecialchars($this->locale) . '">';

        // Twitter Card
        $meta[] = '<meta name="twitter:card" content="summary_large_image">';
        if ($this->title) $meta[] = '<meta name="twitter:title" content="' . htmlspecialchars($this->title) . '">';
        if ($this->description) $meta[] = '<meta name="twitter:description" content="' . htmlspecialchars($this->description) . '">';
        if ($this->image) $meta[] = '<meta name="twitter:image" content="' . htmlspecialchars($this->image) . '">';

        // Custom
        foreach ($this->customMeta as $name => $content) {
            $meta[] = '<meta name="' . htmlspecialchars($name) . '" content="' . htmlspecialchars($content) . '">';
        }

        return "\n" . implode("\n", $meta) . "\n";
    }

    public function generateJsonLd(): string
    {
        $data = [
            '@context' => 'https://schema.org',
        ];

        if ($this->type === 'article') {
            $data['@type'] = 'Article';
            $data['headline'] = $this->title;
            $data['description'] = $this->description;
            if ($this->url) $data['url'] = $this->url;
            if ($this->image) $data['image'] = $this->image;
            $data['datePublished'] = date('c');
            $data['dateModified'] = date('c');
            $data['mainEntityOfPage'] = ['@type' => 'WebPage', '@id' => $this->url ?: ''];
        } else {
            $data['@type'] = 'WebPage';
            $data['name'] = $this->title;
            $data['description'] = $this->description;
            if ($this->url) $data['url'] = $this->url;
        }

        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES) . '</script>';
    }
}

class Sitemap
{
    public static function generate(array $urls, string $baseUrl = 'https://example.com'): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $urlData) {
            $loc = is_string($urlData) ? $urlData : ($urlData['url'] ?? '#');
            if (!str_starts_with($loc, 'http')) {
                $loc = rtrim($baseUrl, '/') . '/' . ltrim($loc, '/');
            }

            $lastmod = $urlData['lastmod'] ?? date('Y-m-d');
            $changefreq = $urlData['changefreq'] ?? 'monthly';
            $priority = $urlData['priority'] ?? '0.5';

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
            $xml .= "    <lastmod>" . $lastmod . "</lastmod>\n";
            $xml .= "    <changefreq>" . $changefreq . "</changefreq>\n";
            $xml .= "    <priority>" . $priority . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>";
        return $xml;
    }

    public static function generateFromArticles(array $articles, string $baseUrl): string
    {
        $urls = [];
        foreach ($articles as $article) {
            $data = is_array($article) ? $article : (method_exists($article, 'toArray') ? $article->toArray() : []);
            $urls[] = [
                'url' => 'article/' . ($data['slug'] ?? $data['id']),
                'lastmod' => $data['updated_at'] ?? date('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }
        return self::generate($urls, $baseUrl);
    }

    public static function download(array $articles, string $baseUrl, string $filename = 'sitemap.xml'): void
    {
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo self::generateFromArticles($articles, $baseUrl);
        exit;
    }
}
