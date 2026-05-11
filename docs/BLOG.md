# Blog & CMS Guide

Complete blogging and content management system for Pype PHP Framework.

## Quick Start

```php
use Framework\Blog\BlogMigrations;

BlogMigrations::create();
```

## Articles

### Create & Manage

```php
use Framework\Blog\Article;

// Create article
$article = Article::createArticle([
    'title' => 'Getting Started with PHP',
    'content' => '# Introduction\n\nPHP is a popular scripting language...',
    'author_id' => 1,
    'status' => 'draft', // draft, published, scheduled, archived, trash
]);

// Helper function
$article = create_article([
    'title' => 'My Post',
    'content' => 'Content here...',
    'author_id' => 1,
]);
```

### Publishing Workflow

```php
// Publish immediately
$article->publish();

// Schedule for later
$article->schedulePublish('2026-06-01 09:00:00');

// Unpublish (back to draft)
$article->unpublish();

// Archive or trash
$article->archive();
$article->trash();
$article->restore();

// Auto-publish scheduled articles (run via cron)
Article::publishScheduled();
```

### Revisions & Versioning

```php
// Save revision before editing
$article->saveRevision(null, 'Fixed typos');

// Get revision history
$revisions = $article->getRevisions();

// Restore a previous revision
$article->restoreRevision($revisionId);
```

### Querying Articles

```php
// Recent published articles
$recent = Article::recent(10);

// Popular articles (by views, last 30 days)
$popular = Article::popular(10, 30);

// Featured articles
$featured = Article::featured(5);

// By author
$posts = Article::byAuthor($authorId)->get();

// Archive (year/month grouping)
$archive = Article::getArchive();
```

### Helper Functions

```php
recent_articles(10);
popular_articles(10, 30);
featured_articles(5);
get_article($slugOrId);
publish_article($articleId);
```

## Categories

Hierarchical categories with parent/child relationships.

```php
use Framework\Blog\Category;

// Create category
$tech = Category::createCategory([
    'name' => 'Technology',
    'slug' => 'technology',
    'description' => 'Tech articles',
]);

// Child category
$ai = Category::createCategory([
    'name' => 'Artificial Intelligence',
    'parent_id' => $tech->id,
]);

// Get category tree
$tree = Category::getTree();

// Get articles in category
$articles = $tech->getArticles(10);
```

### Helpers

```php
create_category(['name' => 'News', 'slug' => 'news']);
get_categories();
category_tree();
```

## Tags

Flat tagging system for articles.

```php
use Framework\Blog\Tag;

$tag = Tag::createTag(['name' => 'PHP']);
$tag = Tag::firstOrCreateByName('JavaScript');

// Tag cloud (sorted by usage)
$cloud = Tag::cloud(50);

// Popular tags
$popular = Tag::popular(10);

// Articles with tag
$articles = $tag->getArticles(10);
```

### Helpers

```php
create_tag('PHP');
tag_cloud(50);
popular_tags(10);
```

## Comments

Nested comments with moderation and spam filtering.

```php
use Framework\Blog\Comment;

// Add comment
$comment = Comment::createComment([
    'article_id' => 1,
    'author_name' => 'Jane',
    'author_email' => 'jane@example.com',
    'content' => 'Great article!',
]);

// Moderation
$comment->approve();
$comment->reject();
$comment->markSpam();

// Get nested comments
$tree = Comment::treeForArticle($articleId);

// Moderation queue
$pending = Comment::moderationQueue(20);
$spam = Comment::spamQueue(20);
```

### Spam Detection

Automatic spam filtering based on:
- Too many links (>3)
- Blacklisted words (casino, viagra, etc.)
- Very short content (<5 chars)
- ALL CAPS content
- Previously approved email auto-approves

### Helpers

```php
add_comment([...]);
get_comments($articleId);
moderation_queue(20);
```

## Markdown

Native markdown parser with syntax highlighting.

```php
use Framework\Blog\Markdown;

$html = Markdown::parse('# Hello **World**');

// Code blocks with highlighting
$markdown = <<<MD
```php
function hello() {
    return "world";
}
```

```js
console.log("Hello");
```
MD;

$html = Markdown::parse($markdown);

// Parse from file
$html = Markdown::parseFile('post.md');
```

### Supported Syntax

- Headers (h1-h6)
- Bold, italic, strikethrough
- Links and images
- Inline code and code blocks
- Blockquotes
- Unordered and ordered lists
- Horizontal rules

### Supported Languages for Syntax Highlighting

- PHP, JavaScript/JS, HTML/XML, SQL
- Keywords, strings, and comments are highlighted with CSS classes

## SEO Toolkit

Generate meta tags, Open Graph, Twitter Cards, JSON-LD, and sitemaps.

```php
use Framework\Blog\SEO;
use Framework\Blog\Sitemap;

// Generate meta tags
$seo = SEO::make()
    ->setTitle('My Article')
    ->setDescription('A great article about...')
    ->setUrl('https://example.com/post')
    ->setImage('https://example.com/cover.jpg')
    ->setKeywords(['php', 'tutorial'])
    ->generateMeta();

// JSON-LD structured data
$jsonLd = $seo->generateJsonLd();

echo $seo->generateMeta() . "\n" . $seo->generateJsonLd();
```

### Helper

```php
echo generate_seo([
    'title' => 'My Article',
    'description' => 'Article description',
    'url' => $articleUrl,
    'image' => $coverUrl,
    'keywords' => ['php', 'blog'],
]);
```

### Sitemap

```php
$articles = Article::published()->get();
$xml = Sitemap::generateFromArticles($articles, 'https://example.com');

// Download sitemap
Sitemap::download($articles, 'https://example.com', 'sitemap.xml');
```

### Helper

```php
echo generate_sitemap($articles, 'https://example.com');
```

## RSS Feeds

Generate RSS 2.0 feeds.

```php
use Framework\Blog\RSS;

$articles = Article::recent(20);

// Generate feed
$xml = RSS::feed('My Blog', 'https://example.com', $articles, [
    'description' => 'Latest posts',
    'image' => 'https://example.com/logo.png',
]);

// Output directly
RSS::output('My Blog', 'https://example.com', $articles);

// Download
RSS::download('My Blog', 'https://example.com', $articles);
```

### Helper

```php
echo rss_feed('My Blog', 'https://example.com', $articles);
```

## Full-Text Search

Search articles by title, content, tags, and categories.

```php
use Framework\Blog\Search;

$results = Search::query('php tutorial', [
    'limit' => 10,
    'status' => 'published',
    'category' => 'technology', // optional
    'tag' => 'php',             // optional
]);

// Autocomplete suggestions
$suggestions = Search::suggestions('php', 5);
```

### Helpers

```php
$results = blog_search('php tutorial');
$suggestions = search_suggestions('php');
```

## Analytics

Track page views, reading time, and popular posts.

```php
use Framework\Blog\Analytics;

// Track page view
Analytics::trackView($articleId, [
    'referer' => $_SERVER['HTTP_REFERER'],
    'country' => 'US',
]);

// Popular posts (last 30 days)
$popular = Analytics::popularPosts(30, 10);

// Trending (last 24 hours)
$trending = Analytics::trendingPosts(24, 5);

// Author statistics
$stats = Analytics::authorStats($authorId, 30);

// Overview
$overview = Analytics::overview(30);
// Returns: total_articles, total_comments, total_views, daily_views
```

### Helpers

```php
track_view($articleId);
trending_posts(24, 5);
blog_overview(30);
author_stats($authorId, 30);
```

## Database Tables

`BlogMigrations::create()` creates:

| Table | Description |
|-------|-------------|
| `articles` | Blog posts with drafts, scheduling, SEO fields |
| `article_revisions` | Content versioning history |
| `blog_categories` | Hierarchical categories |
| `blog_tags` | Flat tags |
| `blog_comments` | Comments with moderation |
| `blog_analytics` | Page view tracking |
| `article_categories` | Pivot: articles ↔ categories |
| `article_tags` | Pivot: articles ↔ tags |

## File Attachments

Articles support cover images via the `HasFiles` trait:

```php
// Upload cover image
$article->setCoverImage($_FILES['cover']);

// Get cover URL
$url = $article->getCoverImageUrl();
```

## Cron Job for Scheduled Posts

Run this daily or hourly:

```php
$count = Article::publishScheduled();
echo "Published {$count} scheduled articles.";
```
