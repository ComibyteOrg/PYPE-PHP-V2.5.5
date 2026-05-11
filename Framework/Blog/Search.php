<?php

namespace Framework\Blog;

use Framework\Model\Model;

/**
 * Full-Text Search
 * Searches articles by title, content, tags, and categories with ranking.
 *
 * Usage:
 * $results = Search::query('php tutorial', ['limit' => 10, 'status' => 'published']);
 */
class Search
{
    public static function query(string $keyword, array $options = []): array
    {
        $keyword = trim($keyword);
        if (strlen($keyword) < 2) {
            return [];
        }

        $limit = $options['limit'] ?? 20;
        $offset = $options['offset'] ?? 0;
        $status = $options['status'] ?? Article::STATUS_PUBLISHED;
        $category = $options['category'] ?? null;
        $tag = $options['tag'] ?? null;

        $terms = explode(' ', $keyword);
        $titleConditions = [];
        $contentConditions = [];

        foreach ($terms as $term) {
            $term = trim($term);
            if (strlen($term) < 2) continue;
            $escaped = addslashes($term);
            $titleConditions[] = "a.title LIKE '%{$escaped}%'";
            $contentConditions[] = "a.content LIKE '%{$escaped}%'";
            $contentConditions[] = "a.content_html LIKE '%{$escaped}%'";
        }

        $titleWhere = !empty($titleConditions) ? '(' . implode(' OR ', $titleConditions) . ')' : '1=1';
        $contentWhere = !empty($contentConditions) ? '(' . implode(' OR ', $contentConditions) . ')' : '1=1';

        $sql = "SELECT a.*, 
                (CASE 
                    WHEN {$titleWhere} THEN 10 
                    ELSE 0 
                END) + 
                (CASE 
                    WHEN {$contentWhere} THEN 1 
                    ELSE 0 
                END) AS relevance
                FROM articles a";

        $params = [$status];
        $conditions = ["a.status = ?"];

        if ($category) {
            $sql .= " JOIN article_categories ac ON a.id = ac.article_id";
            $sql .= " JOIN blog_categories bc ON ac.category_id = bc.id";
            $conditions[] = "(bc.slug = ? OR bc.name = ?)";
            $params[] = $category;
            $params[] = $category;
        }

        if ($tag) {
            $sql .= " JOIN article_tags at ON a.id = at.article_id";
            $sql .= " JOIN blog_tags bt ON at.tag_id = bt.id";
            $conditions[] = "(bt.slug = ? OR bt.name = ?)";
            $params[] = $tag;
            $params[] = $tag;
        }

        $sql .= " WHERE " . implode(' AND ', $conditions) . " ORDER BY relevance DESC, a.published_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $result = Model::rawQuery($sql, $params);
        return $result ? $result->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    public static function suggestions(string $prefix, int $limit = 5): array
    {
        $prefix = trim($prefix);
        if (strlen($prefix) < 2) return [];
        $escaped = addslashes($prefix);

        $sql = "SELECT DISTINCT title FROM articles WHERE status = ? AND title LIKE ? ORDER BY title LIMIT ?";
        $result = Model::rawQuery($sql, [Article::STATUS_PUBLISHED, "{$escaped}%", $limit]);
        return $result ? array_column($result->fetchAll(\PDO::FETCH_ASSOC), 'title') : [];
    }

    public static function count(string $keyword, array $options = []): int
    {
        $results = self::query($keyword, array_merge($options, ['limit' => 10000]));
        return count($results);
    }
}
