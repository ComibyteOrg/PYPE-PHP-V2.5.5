<?php
namespace Framework\Http\Resources;

class UserResource extends Resource
{
    public static function make($user)
    {
        return [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'avatar' => $user['avatar'] ?? null,
            'created_at' => $user['created_at'] ?? null,
            'updated_at' => $user['updated_at'] ?? null
        ];
    }
}