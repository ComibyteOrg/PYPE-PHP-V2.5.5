<?php

namespace Framework\Social;

use Framework\Database\Migration;
use Framework\Database\Schema;

/**
 * SocialMigrations
 * Creates all tables required for social media features.
 * Run this migration once to enable the social media system.
 *
 * Usage:
 * (new \Framework\Social\SocialMigrations())->up();
 *
 * Or create individual tables:
 * SocialMigrations::createFollows();
 * SocialMigrations::createPosts();
 */
class SocialMigrations extends Migration
{
    public function up()
    {
        $this->createFollowsTable();
        $this->createPostsTable();
        $this->createLikesTable();
        $this->createCommentsTable();
        $this->createSharesTable();
        $this->createBookmarksTable();
        $this->createHashtagsTable();
        $this->createTaggablesTable();
        $this->createMentionsTable();
        $this->createNotificationsTable();
        $this->createConversationsTable();
        $this->createMessagesTable();
        $this->createReportsTable();
        $this->createBannedWordsTable();
        $this->createUserProfilesTable();
        $this->createPollVotesTable();
    }

    public function down()
    {
        $this->dropTable('poll_votes');
        $this->dropTable('user_profiles');
        $this->dropTable('banned_words');
        $this->dropTable('reports');
        $this->dropTable('messages');
        $this->dropTable('conversations');
        $this->dropTable('notifications');
        $this->dropTable('mentions');
        $this->dropTable('taggables');
        $this->dropTable('hashtags');
        $this->dropTable('bookmarks');
        $this->dropTable('shares');
        $this->dropTable('comments');
        $this->dropTable('likes');
        $this->dropTable('posts');
        $this->dropTable('follows');
    }

    public static function create()
    {
        (new self())->up();
    }

    public static function drop()
    {
        (new self())->down();
    }

    protected function createFollowsTable()
    {
        $this->createTable('follows', function (Schema $table) {
            $table->id();
            $table->string('follower_type', 255);
            $table->integer('follower_id');
            $table->string('followable_type', 255);
            $table->integer('followable_id');
            $table->timestamp('created_at');

            $table->unique(['follower_type', 'follower_id', 'followable_type', 'followable_id'], 'uniq_follows_composite');
        });
    }

    protected function createPostsTable()
    {
        $this->createTable('posts', function (Schema $table) {
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
        });

        $this->raw("CREATE INDEX idx_posts_user ON posts (user_id)");
        $this->raw("CREATE INDEX idx_posts_type ON posts (type)");
        $this->raw("CREATE INDEX idx_posts_visibility ON posts (visibility)");
        $this->raw("CREATE INDEX idx_posts_parent ON posts (parent_id)");
        $this->raw("CREATE INDEX idx_posts_created ON posts (created_at DESC)");
    }

    protected function createLikesTable()
    {
        $this->createTable('likes', function (Schema $table) {
            $table->id();
            $table->string('post_type', 255);
            $table->integer('post_id');
            $table->integer('user_id');
            $table->timestamp('created_at');

            $table->unique(['post_type', 'post_id', 'user_id'], 'uniq_likes_composite');
            $table->index(['post_type', 'post_id'], 'idx_likes_post');
            $table->index('user_id', 'idx_likes_user');
        });
    }

    protected function createCommentsTable()
    {
        $this->createTable('comments', function (Schema $table) {
            $table->id();
            $table->string('post_type', 255);
            $table->integer('post_id');
            $table->integer('user_id');
            $table->text('content');
            $table->integer('parent_id')->nullable();
            $table->timestamp('created_at');

            $table->index(['post_type', 'post_id'], 'idx_comments_post');
            $table->index('user_id', 'idx_comments_user');
            $table->index('parent_id', 'idx_comments_parent');
        });
    }

    protected function createSharesTable()
    {
        $this->createTable('shares', function (Schema $table) {
            $table->id();
            $table->string('post_type', 255);
            $table->integer('post_id');
            $table->integer('user_id');
            $table->timestamp('created_at');

            $table->unique(['post_type', 'post_id', 'user_id'], 'uniq_shares_composite');
            $table->index(['post_type', 'post_id'], 'idx_shares_post');
            $table->index('user_id', 'idx_shares_user');
        });
    }

    protected function createBookmarksTable()
    {
        $this->createTable('bookmarks', function (Schema $table) {
            $table->id();
            $table->string('post_type', 255);
            $table->integer('post_id');
            $table->integer('user_id');
            $table->timestamp('created_at');

            $table->unique(['post_type', 'post_id', 'user_id'], 'uniq_bookmarks_composite');
            $table->index(['post_type', 'post_id'], 'idx_bookmarks_post');
            $table->index('user_id', 'idx_bookmarks_user');
        });
    }

    protected function createHashtagsTable()
    {
        $this->createTable('hashtags', function (Schema $table) {
            $table->id();
            $table->string('name', 100);
            $table->timestamp('created_at');

            $table->unique('name', 'uniq_hashtags_name');
        });
    }

    protected function createTaggablesTable()
    {
        $this->createTable('taggables', function (Schema $table) {
            $table->id();
            $table->integer('tag_id');
            $table->string('taggable_type', 255);
            $table->integer('taggable_id');
            $table->timestamp('created_at');

            $table->unique(['tag_id', 'taggable_type', 'taggable_id'], 'uniq_taggables_composite');
            $table->index(['taggable_type', 'taggable_id'], 'idx_taggables_taggable');
        });
    }

    protected function createMentionsTable()
    {
        $this->createTable('mentions', function (Schema $table) {
            $table->id();
            $table->string('mentionable_type', 255);
            $table->integer('mentionable_id');
            $table->integer('user_id');
            $table->timestamp('created_at');
        });

        $this->raw("CREATE INDEX idx_mentions_user ON mentions (user_id)");
        $this->raw("CREATE INDEX idx_mentions_mentionable ON mentions (mentionable_type, mentionable_id)");
    }

    protected function createNotificationsTable()
    {
        $this->createTable('notifications', function (Schema $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('type', 50);
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->integer('from_user_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->string('priority', 20)->default('normal');
            $table->string('channel', 20)->default('database');
            $table->timestamp('created_at');
            $table->timestamp('read_at')->nullable();
        });

        $this->raw("CREATE INDEX idx_notifications_user ON notifications (user_id, is_read)");
        $this->raw("CREATE INDEX idx_notifications_created ON notifications (created_at DESC)");
    }

    protected function createConversationsTable()
    {
        $this->createTable('conversations', function (Schema $table) {
            $table->id();
            $table->integer('user_one_id');
            $table->integer('user_two_id');
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->unique(['user_one_id', 'user_two_id'], 'uniq_conversations_users');
            $table->index('user_one_id', 'idx_conversations_user1');
            $table->index('user_two_id', 'idx_conversations_user2');
        });
    }

    protected function createMessagesTable()
    {
        $this->createTable('messages', function (Schema $table) {
            $table->id();
            $table->integer('conversation_id');
            $table->integer('sender_id');
            $table->text('content');
            $table->string('attachment', 500)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');

            $table->index('conversation_id', 'idx_messages_conversation');
            $table->index('sender_id', 'idx_messages_sender');
        });
    }

    protected function createReportsTable()
    {
        $this->createTable('reports', function (Schema $table) {
            $table->id();
            $table->integer('reporter_id');
            $table->string('reportable_type', 255);
            $table->integer('reportable_id');
            $table->string('reason', 50);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('action_taken', 20)->default('none');
            $table->integer('moderator_id')->nullable();
            $table->text('moderator_notes')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('resolved_at')->nullable();
        });

        $this->raw("CREATE INDEX idx_reports_reportable ON reports (reportable_type, reportable_id)");
        $this->raw("CREATE INDEX idx_reports_status ON reports (status)");
    }

    protected function createBannedWordsTable()
    {
        $this->createTable('banned_words', function (Schema $table) {
            $table->id();
            $table->string('word', 100)->unique();
            $table->timestamp('created_at');
        });
    }

    protected function createUserProfilesTable()
    {
        $this->createTable('user_profiles', function (Schema $table) {
            $table->id();
            $table->integer('user_id')->unique();
            $table->text('bio')->nullable();
            $table->string('location', 255)->nullable();
            $table->string('website', 500)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('gender', 20)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->string('verified_badge', 50)->nullable();
            $table->json('social_links')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });

        $this->raw("CREATE INDEX idx_profiles_user ON user_profiles (user_id)");
    }

    protected function createPollVotesTable()
    {
        $this->createTable('poll_votes', function (Schema $table) {
            $table->id();
            $table->integer('post_id');
            $table->integer('user_id');
            $table->integer('option_index');
            $table->timestamp('created_at');

            $table->unique('post_id', 'user_id');
        });

        $this->raw("CREATE INDEX idx_poll_votes_post ON poll_votes (post_id)");
        $this->raw("CREATE INDEX idx_poll_votes_user ON poll_votes (user_id)");
    }
}
