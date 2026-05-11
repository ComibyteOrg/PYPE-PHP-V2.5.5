<?php

namespace Framework\Blog;

use Framework\Blog\Article;
use Framework\Blog\ArticleRevision;
use Framework\Blog\Category;
use Framework\Blog\Tag;
use Framework\Blog\Comment;
use Framework\Blog\Analytics;

/**
 * Blog Migrations
 * Creates all blog-related tables.
 *
 * Usage:
 * BlogMigrations::create();
 */
class BlogMigrations
{
    public static function create(): void
    {
        echo "Creating blog tables...\n";

        Article::createTable();
        echo "  ✓ articles\n";

        ArticleRevision::createTable();
        echo "  ✓ article_revisions\n";

        Category::createTable();
        echo "  ✓ blog_categories\n";

        Tag::createTable();
        echo "  ✓ blog_tags\n";

        Comment::createTable();
        echo "  ✓ blog_comments\n";

        Analytics::createTable();
        echo "  ✓ blog_analytics\n";

        // Pivot tables
        self::createArticleCategoriesTable();
        echo "  ✓ article_categories\n";

        self::createArticleTagsTable();
        echo "  ✓ article_tags\n";

        // Indexes
        self::createIndexes();
        echo "  ✓ indexes\n";

        echo "Blog tables created successfully.\n";
    }

    public static function drop(): void
    {
        $db = \Framework\Database\DatabaseQuery::pdo();
        $db->exec("DROP TABLE IF EXISTS article_tags");
        $db->exec("DROP TABLE IF EXISTS article_categories");
        $db->exec("DROP TABLE IF EXISTS blog_analytics");
        $db->exec("DROP TABLE IF EXISTS blog_comments");
        $db->exec("DROP TABLE IF EXISTS blog_tags");
        $db->exec("DROP TABLE IF EXISTS blog_categories");
        $db->exec("DROP TABLE IF EXISTS article_revisions");
        $db->exec("DROP TABLE IF EXISTS articles");
        echo "Blog tables dropped.\n";
    }

    protected static function createArticleCategoriesTable(): void
    {
        $db = \Framework\Database\DatabaseQuery::pdo();
        $db->exec("
            CREATE TABLE IF NOT EXISTS article_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                article_id INT NOT NULL,
                category_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_article_category (article_id, category_id),
                INDEX idx_category (category_id),
                INDEX idx_article (article_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    protected static function createArticleTagsTable(): void
    {
        $db = \Framework\Database\DatabaseQuery::pdo();
        $db->exec("
            CREATE TABLE IF NOT EXISTS article_tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                article_id INT NOT NULL,
                tag_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_article_tag (article_id, tag_id),
                INDEX idx_tag (tag_id),
                INDEX idx_article (article_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    protected static function createIndexes(): void
    {
        $db = \Framework\Database\DatabaseQuery::pdo();
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_articles_slug ON articles(slug)",
            "CREATE INDEX IF NOT EXISTS idx_articles_author ON articles(author_id)",
            "CREATE INDEX IF NOT EXISTS idx_articles_status ON articles(status)",
            "CREATE INDEX IF NOT EXISTS idx_articles_published ON articles(published_at)",
            "CREATE INDEX IF NOT EXISTS idx_articles_views ON articles(view_count)",
            "CREATE INDEX IF NOT EXISTS idx_articles_scheduled ON articles(scheduled_at)",
            "CREATE INDEX IF NOT EXISTS idx_comments_article ON blog_comments(article_id)",
            "CREATE INDEX IF NOT EXISTS idx_comments_status ON blog_comments(status)",
            "CREATE INDEX IF NOT EXISTS idx_analytics_article ON blog_analytics(article_id)",
            "CREATE INDEX IF NOT EXISTS idx_analytics_date ON blog_analytics(viewed_at)",
        ];

        foreach ($indexes as $sql) {
            $db->exec($sql);
        }
    }
}
