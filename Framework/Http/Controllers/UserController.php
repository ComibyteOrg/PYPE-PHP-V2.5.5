<?php

namespace Framework\Http\Controllers;

use Framework\Helper\ApiResponse;
use Framework\Http\Resources\UserResource;
use Framework\Helper\DB;
use Framework\Helper\Helper;

class UserController
{
    public function index()
    {
        $users = DB::table('users')->get();
        return ApiResponse::success(UserResource::collection($users), "Users retrieved successfully");
    }

    public function show($id)
    {
        $user = DB::table('users')->find($id);
        if (!$user) {
            return ApiResponse::error("User not found", 404);
        }
        return ApiResponse::success(UserResource::make($user), "User retrieved successfully");
    }

    public function update($id)
    {
        $user = DB::table('users')->find($id);
        if (!$user) {
            return ApiResponse::error("User not found", 404);
        }

        $data = [
            'name' => Helper::input('name', $user['name']),
            'email' => Helper::input('email', $user['email']),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        DB::table('users')->update($data, ['id' => $id]);
        $updatedUser = DB::table('users')->find($id);

        return ApiResponse::success(UserResource::make($updatedUser), "User updated successfully");
    }

    public function destroy($id)
    {
        $user = DB::table('users')->find($id);
        if (!$user) {
            return ApiResponse::error("User not found", 404);
        }

        DB::table('users')->delete(['id' => $id]);

        return ApiResponse::success(null, "User deleted successfully");
    }
}
