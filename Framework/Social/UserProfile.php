<?php

namespace Framework\Social;

use Framework\Model\Model;
use Framework\Model\HasFiles;

/**
 * UserProfile Model
 * Extended profile data for users with avatars, cover photos, bios, and verified badges.
 * Works alongside the main User model.
 *
 * Usage:
 * $profile = UserProfile::forUser($userId);
 * $profile->updateBio('Hello world!');
 * $profile->setAvatar($_FILES['avatar']);
 * $profile->setCoverPhoto($_FILES['cover']);
 * $profile->isVerified();
 */
class UserProfile extends Model
{
    use HasFiles;

    protected static $table = 'user_profiles';
    protected static $primaryKey = 'id';

    public static $fields = [
        'id' => 'integer',
        'user_id' => 'integer',
        'bio' => 'text',
        'location' => 'string',
        'website' => 'string',
        'birthdate' => 'date',
        'gender' => 'string',
        'is_verified' => 'boolean',
        'verified_badge' => 'string',
        'social_links' => 'json',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public static function schema($table)
    {
        $table->id();
        $table->integer('user_id');
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
    }

    public static function forUser(int $userId): ?UserProfile
    {
        $profile = static::where('user_id', $userId)->getFirst();
        if (!$profile) {
            return static::create([
                'user_id' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return $profile;
    }

    public static function getOrCreate(int $userId): UserProfile
    {
        $profile = self::forUser($userId);
        if (!$profile) {
            $profile = static::create([
                'user_id' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return $profile;
    }

    public function updateBio(string $bio): bool
    {
        return static::where('id', $this->id)->updateRows([
            'bio' => $bio,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateLocation(string $location): bool
    {
        return static::where('id', $this->id)->updateRows([
            'location' => $location,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateWebsite(string $website): bool
    {
        return static::where('id', $this->id)->updateRows([
            'website' => $website,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateSocialLinks(array $links): bool
    {
        return static::where('id', $this->id)->updateRows([
            'social_links' => json_encode($links),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function setAvatar($file): ?array
    {
        $this->deleteFile('avatar');
        return $this->attachFile($file, 'avatar');
    }

    public function setCoverPhoto($file): ?array
    {
        $this->deleteFile('cover');
        return $this->attachFile($file, 'cover');
    }

    public function getAvatarUrl(): ?string
    {
        return $this->getFileUrl('avatar');
    }

    public function getCoverPhotoUrl(): ?string
    {
        return $this->getFileUrl('cover');
    }

    public function isVerified(): bool
    {
        return (bool) $this->is_verified;
    }

    public function verify(string $badge = 'blue'): bool
    {
        return static::where('id', $this->id)->updateRows([
            'is_verified' => true,
            'verified_badge' => $badge,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function unverify(): bool
    {
        return static::where('id', $this->id)->updateRows([
            'is_verified' => false,
            'verified_badge' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getSocialLinks(): array
    {
        if ($this->social_links) {
            return json_decode($this->social_links, true) ?? [];
        }
        return [];
    }

    public function addSocialLink(string $platform, string $url): bool
    {
        $links = $this->getSocialLinks();
        $links[$platform] = $url;
        return $this->updateSocialLinks($links);
    }

    public function removeSocialLink(string $platform): bool
    {
        $links = $this->getSocialLinks();
        unset($links[$platform]);
        return $this->updateSocialLinks($links);
    }

    public function getAge(): ?int
    {
        if (!$this->birthdate) {
            return null;
        }

        $birthDate = new \DateTime($this->birthdate);
        $today = new \DateTime();
        $age = $today->diff($birthDate)->y;

        return $age;
    }

    public function getPostCount(): int
    {
        return Post::where('user_id', $this->user_id)->countRows();
    }

    public function getFollowerCount(): int
    {
        $userModel = new \App\Models\User();
        $userModel->data['id'] = $this->user_id;

        if (in_array('Framework\Social\Followable', class_uses_recursive($userModel))) {
            return $userModel->followerCount();
        }

        return 0;
    }

    public function getFollowingCount(): int
    {
        $userModel = new \App\Models\User();
        $userModel->data['id'] = $this->user_id;

        if (in_array('Framework\Social\Followable', class_uses_recursive($userModel))) {
            return $userModel->followingCount();
        }

        return 0;
    }

    public function getRecentPosts(int $limit = 10): array
    {
        return Post::where('user_id', $this->user_id)
            ->where('visibility', 'public')
            ->where('parent_id', null)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['avatar_url'] = $this->getAvatarUrl();
        $data['cover_photo_url'] = $this->getCoverPhotoUrl();
        $data['social_links'] = $this->getSocialLinks();
        $data['age'] = $this->getAge();
        $data['post_count'] = $this->getPostCount();
        $data['follower_count'] = $this->getFollowerCount();
        $data['following_count'] = $this->getFollowingCount();
        return $data;
    }
}
