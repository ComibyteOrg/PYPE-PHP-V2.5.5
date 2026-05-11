<?php
namespace Framework\Http\Resources;

class Resource
{
    public static function make($data)
    {
        return $data;
    }

    public static function collection($data)
    {
        return array_map([static::class, 'make'], $data);
    }
}