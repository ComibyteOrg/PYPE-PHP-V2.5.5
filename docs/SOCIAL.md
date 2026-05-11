# Social Media System - Pype PHP v2.5.5

## Overview

The Pype PHP Social Media system provides a complete foundation for building social platforms. It includes posts, feeds, follows, engagement, messaging, notifications, and content moderation.

## Table of Contents

- [Setup](#setup)
- [Follow System](#follow-system)
- [Posts](#posts)
- [Engagement](#engagement)
- [Hashtags & Mentions](#hashtags--mentions)
- [Activity Feeds](#activity-feeds)
- [Notifications](#notifications)
- [Direct Messaging](#direct-messaging)
- [Content Moderation](#content-moderation)
- [User Profiles](#user-profiles)
- [Helper Functions](#helper-functions)

## Setup

### 1. Run Social Migrations

```php
use Framework\Social\SocialMigrations;

SocialMigrations::create();
```

This creates 16 tables:
- `follows` - Follow relationships
- `posts` - All post types
- `likes`, `comments`, `shares`, `bookmarks` - Engagement
- `hashtags`, `taggables`, `mentions` - Content parsing
- `notifications` - User notifications
- `conversations`, `messages` - Direct messaging
- `reports`, `banned_words` - Moderation
- `user_profiles` - Extended profiles
- `poll_votes` - Poll voting

### 2. Add Traits to User Model

```php
namespace App\Models;

use Framework\Model\Model;
use Framework\Social\Followable;

class User extends Model
{
    use Followable;
}
```

## Follow System

The `Followable` trait provides self-referential follow relationships.

### Basic Usage

```php
// Follow a user
$user->follow($targetUser);

// Unfollow
$user->unfollow($targetUser);

// Toggle follow
$user->toggleFollow($targetUser);

// Check relationship
$user->isFollowing($targetUser);
$user->isFollowedBy($targetUser);
```

### Get Followers & Following

```php
// Get followers (paginated)
$followers = $user->getFollowers(limit: 20, offset: 0);

// Get following
$following = $user->getFollowing(limit: 20, offset: 0);

// Counts
$followerCount = $user->followerCount();
$followingCount = $user->followingCount();

// Mutual followers
$mutual = $user->getMutualFollowers($otherUser);
```

## Posts

The `Post` model supports 6 post types with automatic file attachments.

### Post Types

- `text` - Plain text posts
- `image` - Photo posts with media
- `video` - Video posts
- `link` - Shared links with preview
- `poll` - Posts with voting options
- `story` - 24-hour ephemeral posts

### Creating Posts

```php
use Framework\Social\Post;

// Text post
$post = Post::createPost($userId, 'text', 'Hello world!');

// Image post with media
$post = Post::createPost($userId, 'image', 'Beautiful sunset');
$post->attachFile($_FILES['photo'], 'media');

// Link post
$post = Post::createPost($userId, 'link', 'Check this out', [
    'link_url' => 'https://example.com',
    'link_title' => 'Example Site',
    'link_description' => 'A great website',
]);

// Poll post
$post = Post::createPost($userId, 'poll', 'Favorite language?', [
    'poll_options' => ['PHP', 'Python', 'JavaScript', 'Go'],
]);

// Reply to post
$reply = Post::createPost($userId, 'text', 'Great post!', [
    'parent_id' => $originalPost->id,
]);
```

### Post Methods

```php
$post->isType('image');
$post->isImage();
$post->isVideo();
$post->isPoll();
$post->isStory();
$post->isReply();

// Get media
$mediaUrls = $post->getMediaUrls();

// Poll voting
$post->voteInPoll(0, $userId); // Vote for first option
$results = $post->getPollResults();

// Replies
$replies = $post->getReplies();
$parent = $post->getParentPost();

// Read time estimate
$readTime = $post->getReadTime();

// Query posts
$posts = Post::forUser($userId)->get();
$public = Post::publicPosts()->get();
$stories = Post::stories($userId);
```

## Engagement

The `Engageable` trait provides polymorphic likes, comments, shares, and bookmarks.

### Likes

```php
$post->like($userId);
$post->unlike($userId);
$post->toggleLike($userId);
$post->isLikedBy($userId);
$post->likeCount();
$post->getLikes();
```

### Comments

```php
// Add comment
$post->addComment($userId, 'Great post!');

// Nested comments (replies)
$post->addComment($userId, 'Reply to comment', $parentId);

// Get comments
$comments = $post->getComments();
$replies = $post->getCommentReplies($commentId);

// Count
$post->commentCount();

// Delete comment
$post->deleteComment($commentId);
```

### Shares & Bookmarks

```php
// Shares
$post->share($userId);
$post->unshare($userId);
$post->isSharedBy($userId);
$post->shareCount();

// Bookmarks
$post->bookmark($userId);
$post->unbookmark($userId);
$post->toggleBookmark($userId);
$post->isBookmarkedBy($userId);
```

### Engagement Summary

```php
$summary = $post->getEngagementSummary();
// ['likes' => 42, 'comments' => 8, 'shares' => 3]
```

## Hashtags & Mentions

Auto-extract `#tags` and `@mentions` from content.

### Parsing

```php
use Framework\Social\HashtagParser;

$parsed = HashtagParser::parse('Hello @john! Love #php and #webdev');
// ['hashtags' => ['php', 'webdev'], 'mentions' => ['john']]

// Format content with links
$formatted = HashtagParser::formatContent($content, '/tag/{tag}', '/user/{mention}');
```

### Storing Tags

```php
// Automatically store tags and mentions when creating post
HashtagParser::store('Framework\Social\Post', $postId, 'Love #coding @sarah!');
```

### Querying Tags

```php
// Get posts by tag
$posts = HashtagParser::getPostsByTag('php', limit: 20);

// Trending tags
$trending = HashtagParser::getTrendingTags(limit: 10, hours: 24);

// Search tags
$tags = HashtagParser::searchTags('web');

// Get tags for a post
$tags = HashtagParser::getTagsForModel('Framework\Social\Post', $postId);

// Get mentioned users
$users = HashtagParser::getMentionedUsers('Hello @john and @jane');
```

## Activity Feeds

Generate personalized timelines with chronological or algorithmic sorting.

### Chronological Feed

```php
use Framework\Social\FeedBuilder;

$feed = FeedBuilder::forUser($userId)
    ->chronological()
    ->limit(20)
    ->get();
```

### Algorithmic Feed

```php
$feed = FeedBuilder::forUser($userId)
    ->algorithmic()
    ->weightLikes(1)
    ->weightComments(2)
    ->weightShares(3)
    ->weightRecency(2)
    ->limit(20)
    ->get();
```

### Cursor Pagination

```php
// Get first page
$feed = FeedBuilder::forUser($userId)->limit(20)->get();
$nextCursor = $feed[count($feed) - 1]['next_cursor'];

// Get next page
$feed = FeedBuilder::forUser($userId)
    ->cursor($nextCursor)
    ->limit(20)
    ->get();
```

### Feed Options

```php
$feed = FeedBuilder::forUser($userId)
    ->followingOnly()     // Only posts from followed users
    ->onlyTypes(['text', 'image'])  // Filter post types
    ->excludeTypes(['story'])
    ->excludeUsers([5, 10])         // Mute users
    ->get();
```

## Notifications

Database storage, SSE realtime delivery, and email fallback.

### Sending Notifications

```php
use Framework\Social\Notification;

// Database notification
Notification::send($userId, 'new_follower', [
    'from_user_id' => 5,
    'from_user_name' => 'John',
]);

// Realtime via SSE
Notification::send($userId, 'new_message', [
    'conversation_id' => 12,
    'content' => 'Hello!',
], 'realtime');

// Email notification
Notification::send($userId, 'system', [
    'message' => 'Your account was verified',
], 'email');
```

### Notification Types

- `new_follower` - Someone followed you
- `post_liked` - Someone liked your post
- `post_commented` - Someone commented
- `post_shared` - Someone shared your post
- `mentioned` - You were mentioned
- `new_message` - New DM received
- `system` - System notification

### Managing Notifications

```php
// Get unread
$unread = Notification::unread($userId);

// Get all
$all = Notification::allForUser($userId, limit: 50);

// Mark as read
Notification::markAsRead($notificationId);
Notification::markAllAsRead($userId);

// Count
$count = Notification::unreadCount($userId);

// Cleanup old
Notification::deleteOld(days: 30);
```

## Direct Messaging

Real-time conversations with read receipts.

### Conversations

```php
use Framework\Social\Conversation;

// Create or get conversation
$conv = Conversation::createBetween($userId1, $userId2);

// Check if conversation exists
$conv = Conversation::findByUsers($userId1, $userId2);

// Get user's conversations
$conversations = Conversation::forUser($userId, limit: 20);

// Unread count
$unreadCount = Conversation::unreadCount($userId);
```

### Sending Messages

```php
// Send message
$message = $conv->sendMessage($senderId, 'Hello!');

// Get messages (paginated)
$messages = $conv->getMessages(limit: 50);

// Cursor pagination
$messages = $conv->getMessages(limit: 50, before: '2024-01-01 00:00:00');

// Mark as read
$conv->markAsRead($userId);

// Last message
$lastMessage = $conv->getLastMessage();

// Check participant
$conv->isParticipant($userId);
$otherId = $conv->getOtherUserId($userId);
```

### Message Model

```php
use Framework\Social\Message;

// Mark individual message as read
$message->markAsRead();

// Get conversation
$conversation = $message->getConversation();
```

## Content Moderation

Report system, auto-flagging, and approval queues.

### Reporting Content

```php
use Framework\Social\ContentModeration;

// Report a post
ContentModeration::report(
    $reporterId,
    'Framework\Social\Post',
    $postId,
    'spam',
    'This is spam content'
);
```

### Report Reasons

- `spam`
- `harassment`
- `hate_speech`
- `violence`
- `nsfw`
- `misinformation`
- `copyright`
- `other`

### Moderation Actions

```php
// Get pending reports
$reports = ContentModeration::getPendingReports();

// Approve with action
ContentModeration::approveReport($reportId, $moderatorId, 'hide');

// Reject
ContentModeration::rejectReport($reportId, $moderatorId, 'Not a violation');

// Actions: none, hide, delete, warn, suspend, ban
```

### Auto-Moderation

```php
// Check content for flags
$flags = ContentModeration::autoModerate($content);
// Returns array of flags with type and severity

// Auto-hide if high severity
if (ContentModeration::shouldAutoHide($content)) {
    // Auto-hide content
}

// Manage banned words
ContentModeration::addBannedWord('word');
ContentModeration::removeBannedWord('word');
```

### Stats

```php
$stats = ContentModeration::getReportStats();
// ['total' => 100, 'pending' => 25, 'approved' => 50, 'rejected' => 15, 'actioned' => 10]
```

## User Profiles

Extended profile data with avatars, covers, and verification.

### Profile Management

```php
use Framework\Social\UserProfile;

// Get or create profile
$profile = UserProfile::forUser($userId);
$profile = UserProfile::getOrCreate($userId);

// Update profile
$profile->updateBio('Hello world!');
$profile->updateLocation('New York');
$profile->updateWebsite('https://example.com');

// Set avatar and cover
$profile->setAvatar($_FILES['avatar']);
$profile->setCoverPhoto($_FILES['cover']);

// Get URLs
$avatarUrl = $profile->getAvatarUrl();
$coverUrl = $profile->getCoverPhotoUrl();
```

### Verification

```php
$profile->verify('blue');    // Verify with blue badge
$profile->unverify();        // Remove verification
$profile->isVerified();      // Check status
```

### Social Links

```php
$profile->addSocialLink('twitter', 'https://twitter.com/user');
$profile->addSocialLink('github', 'https://github.com/user');
$profile->removeSocialLink('twitter');
$links = $profile->getSocialLinks();
```

### Profile Stats

```php
$profile->getPostCount();
$profile->getFollowerCount();
$profile->getFollowingCount();
$profile->getAge();
$profile->getRecentPosts(limit: 10);

// Full data
$data = $profile->toArray();
```

## Helper Functions

### Follow System
```php
follow($user, $targetUser);
unfollow($user, $targetUser);
is_following($user, $targetUser);
```

### Posts
```php
create_post($userId, 'text', 'Hello!', ['visibility' => 'public']);
```

### Hashtags & Mentions
```php
parse_hashtags('Hello @john! #php');
format_hashtags($content);
trending_tags(limit: 10, hours: 24);
search_tags('web');
```

### Feeds
```php
get_feed($userId, 'chronological', limit: 20, cursor: $cursor);
```

### Notifications
```php
send_notification($userId, 'new_follower', $data);
unread_notifications($userId);
notification_count($userId);
```

### Messaging
```php
start_conversation($userId1, $userId2);
send_message($conversationId, $senderId, 'Hello!');
```

### Moderation
```php
report_content($reporterId, 'Framework\Social\Post', $postId, 'spam');
```

### Profiles
```php
user_profile($userId);
```

## Complete Example

```php
// Setup
SocialMigrations::create();

// User model
class User extends Model {
    use \Framework\Social\Followable;
    use \Framework\Model\HasFiles;
}

// Create post with media
$post = Post::createPost($userId, 'image', 'My vacation!');
$post->attachFile($_FILES['photo'], 'media');

// Parse and store hashtags
HashtagParser::store('Framework\Social\Post', $post->id, $post->content);

// Like and comment
$post->like($friendId);
$post->addComment($friendId, 'Looks amazing!');

// Follow user
$user->follow($friend);

// Get feed
$feed = FeedBuilder::forUser($userId)
    ->algorithmic()
    ->followingOnly()
    ->limit(20)
    ->get();

// Send notification
Notification::send($post->user_id, 'post_liked', [
    'from_user_id' => $friendId,
    'from_user_name' => $friend->name,
]);

// Start conversation
$conv = Conversation::createBetween($userId, $friendId);
$conv->sendMessage($userId, 'Hey, saw your post!');
```
