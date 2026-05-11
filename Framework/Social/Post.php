<?php

namespace Framework\Social;

use Framework\Model\Model;
use Framework\Model\HasFiles;

/**
 * Post Model
 * Supports multiple post types: text, image, video, link, poll, story.
 * Includes HasFiles for media attachments and Engageable for likes/comments/shares/bookmarks.
 *
 * Usage:
 * $post = Post::create([
 *     'user_id' => 1,
 *     'type' => 'text',
 *     'content' => 'Hello world!',
 *     'visibility' => 'public',
 * ]);
 *
 * // Attach media
 * $post->attachFile($_FILES['image'], 'media');
 *
 * // Get engagement
 * $likes = $post->getLikes();
 * $comments = $post->getComments();
 * $post->like($userId);
 * $post->addComment($userId, 'Great post!');
 */
class Post extends Model
{
    use HasFiles;
    use Engageable;

    protected static $table = 'posts';
    protected static $primaryKey = 'id';

    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';
    public const TYPE_LINK = 'link';
    public const TYPE_POLL = 'poll';
    public const TYPE_STORY = 'story';

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_FOLLOWERS = 'followers';
    public const VISIBILITY_PRIVATE = 'private';

    public static $fields = [
        'id' => 'integer',
        'user_id' => 'integer',
        'type' => 'string',
        'content' => 'text',
        'media_urls' => 'text',
        'link_url' => 'string',
        'link_title' => 'string',
        'link_description' => 'text',
        'poll_options' => 'json',
        'visibility' => 'string',
        'is_pinned' => 'boolean',
        'parent_id' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->integer('user_id');
        $table->string('type', 20)->default('text');
        $table->text('content')->nullable();
        $table->text('media_urls')->nullable();
        $table->string('link_url', 500)->nullable();
        $table->string('link_title', 255)->nullable();
        $table->text('link_description')->nullable();
        $table->json('poll_options')->nullable();
        $table->string('visibility', 20)->default('public');
        $table->boolean('is_pinned')->default(false);
        $table->integer('parent_id')->nullable();
        $table->timestamp('created_at');
        $table->timestamp('updated_at');
    }

    public static function createPost(int $userId, string $type, string $content, array $options = []): ?Post
    {
        $data = [
            'user_id' => $userId,
            'type' => $type,
            'content' => $content,
            'visibility' => $options['visibility'] ?? self::VISIBILITY_PUBLIC,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (isset($options['link_url'])) {
            $data['link_url'] = $options['link_url'];
        }
        if (isset($options['link_title'])) {
            $data['link_title'] = $options['link_title'];
        }
        if (isset($options['link_description'])) {
            $data['link_description'] = $options['link_description'];
        }
        if (isset($options['poll_options']) && is_array($options['poll_options'])) {
            $data['poll_options'] = json_encode($options['poll_options']);
        }
        if (isset($options['parent_id'])) {
            $data['parent_id'] = $options['parent_id'];
        }

        return self::create($data);
    }

    public function addMedia($file, string $collection = 'media'): ?array
    {
        return $this->attachFile($file, $collection);
    }

    public function getMediaUrls(): array
    {
        return $this->getFileUrls('media');
    }

    public function isType(string $type): bool
    {
        return $this->type === $type;
    }

    public function isImage(): bool
    {
        return $this->type === self::TYPE_IMAGE;
    }

    public function isVideo(): bool
    {
        return $this->type === self::TYPE_VIDEO;
    }

    public function isLink(): bool
    {
        return $this->type === self::TYPE_LINK;
    }

    public function isPoll(): bool
    {
        return $this->type === self::TYPE_POLL;
    }

    public function isStory(): bool
    {
        return $this->type === self::TYPE_STORY;
    }

    public function getPollOptions(): array
    {
        if ($this->poll_options) {
            return json_decode($this->poll_options, true) ?? [];
        }
        return [];
    }

    public function voteInPoll(int $optionIndex, int $userId): bool
    {
        if (!$this->isPoll()) {
            return false;
        }

        $options = $this->getPollOptions();
        if (!isset($options[$optionIndex])) {
            return false;
        }

        $table = self::getPollVotesTable();
        $existing = $this->rawQuery(
            "SELECT id FROM {$table} WHERE post_id = ? AND user_id = ?",
            [$this->id, $userId]
        );

        if ($existing && $existing->fetch(\PDO::FETCH_ASSOC)) {
            return false;
        }

        $this->rawQuery(
            "INSERT INTO {$table} (post_id, user_id, option_index, created_at) VALUES (?, ?, ?, ?)",
            [$this->id, $userId, $optionIndex, date('Y-m-d H:i:s')]
        );

        return true;
    }

    public function getPollResults(): array
    {
        $options = $this->getPollOptions();
        $table = self::getPollVotesTable();

        $results = [];
        foreach ($options as $index => $option) {
            $countResult = $this->rawQuery(
                "SELECT COUNT(*) as count FROM {$table} WHERE post_id = ? AND option_index = ?",
                [$this->id, $index]
            );

            $count = 0;
            if ($countResult) {
                $row = $countResult->fetch(\PDO::FETCH_ASSOC);
                $count = (int) $row['count'];
            }

            $results[] = [
                'index' => $index,
                'option' => $option,
                'votes' => $count,
            ];
        }

        $totalVotes = array_sum(array_column($results, 'votes'));
        foreach ($results as &$result) {
            $result['percentage'] = $totalVotes > 0 ? round(($result['votes'] / $totalVotes) * 100, 1) : 0;
        }

        return $results;
    }

    public function getParentPost(): ?Post
    {
        if (!$this->parent_id) {
            return null;
        }

        return self::find($this->parent_id);
    }

    public function getReplies(int $limit = 20): array
    {
        return self::where('parent_id', $this->id)
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->get();
    }

    public function isReply(): bool
    {
        return (bool) $this->parent_id;
    }

    public function getReadTime(int $wpm = 200): int
    {
        $wordCount = str_word_count(strip_tags($this->content ?? ''));
        return max(1, ceil($wordCount / $wpm));
    }

    public static function getPollVotesTable(): string
    {
        return 'poll_votes';
    }

    public static function forUser(int $userId)
    {
        return static::where('user_id', $userId);
    }

    public static function publicPosts()
    {
        return static::where('visibility', self::VISIBILITY_PUBLIC);
    }

    public static function stories(int $userId)
    {
        $twentyFourHoursAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));
        return static::where('type', self::TYPE_STORY)
            ->where('user_id', $userId)
            ->where('created_at', '>=', $twentyFourHoursAgo)
            ->get();
    }
}
