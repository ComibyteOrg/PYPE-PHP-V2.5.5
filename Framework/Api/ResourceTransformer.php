<?php

namespace Framework\Api;

class ResourceTransformer
{
    protected array $includes = [];
    protected array $defaultIncludes = [];
    protected array $visible = [];
    protected array $hidden = [];
    protected array $appends = [];
    protected array $meta = [];

    public static function make(): static
    {
        return new static();
    }

    public function transform(mixed $resource): array
    {
        if (is_null($resource)) {
            return [];
        }

        if (is_iterable($resource)) {
            return $this->transformCollection($resource);
        }

        return $this->transformItem($resource);
    }

    public function transformCollection(iterable $resources): array
    {
        return array_map(fn($item) => $this->transformItem($item), $resources);
    }

    public function include(string $relation): static
    {
        $this->includes[] = $relation;
        return $this;
    }

    public function includes(array $relations): static
    {
        $this->includes = array_merge($this->defaultIncludes, $relations);
        return $this;
    }

    public function visible(array $fields): static
    {
        $this->visible = $fields;
        return $this;
    }

    public function hidden(array $fields): static
    {
        $this->hidden = $fields;
        return $this;
    }

    public function append(string $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->appends = array_merge($this->appends, $key);
        } else {
            $this->appends[$key] = $value;
        }
        return $this;
    }

    public function meta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);
        return $this;
    }

    public function paginate(array $items, int $page, int $perPage, int $total): array
    {
        return [
            'data' => $this->transformCollection($items),
            'meta' => array_merge($this->meta, [
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                    'has_next' => $page < ceil($total / $perPage),
                    'has_prev' => $page > 1,
                ],
            ]),
        ];
    }

    protected function transformItem(mixed $item): array
    {
        $data = $this->normalizeItem($item);
        $data = $this->applyVisibility($data);
        $data = $this->applyIncludes($data, $item);
        $data = $this->applyAppends($data, $item);

        return $data;
    }

    protected function normalizeItem(mixed $item): array
    {
        if (is_array($item)) {
            return $item;
        }

        if (is_object($item)) {
            if (method_exists($item, 'toArray')) {
                return $item->toArray();
            }

            if ($item instanceof \stdClass) {
                return (array) $item;
            }

            return get_object_vars($item);
        }

        return (array) $item;
    }

    protected function applyVisibility(array $data): array
    {
        if (!empty($this->visible)) {
            return array_intersect_key($data, array_flip($this->visible));
        }

        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    protected function applyIncludes(array $data, mixed $item): array
    {
        foreach ($this->includes as $include) {
            if (is_array($item) && isset($item[$include])) {
                $data[$include] = $item[$include];
            } elseif (is_object($item)) {
                if (isset($item->$include)) {
                    $data[$include] = $item->$include;
                } elseif (method_exists($item, $include)) {
                    $data[$include] = $item->{$include}();
                }
            }
        }

        return $data;
    }

    protected function applyAppends(array $data, mixed $item): array
    {
        foreach ($this->appends as $key => $value) {
            if (is_callable($value)) {
                $data[$key] = $value($item);
            } elseif (is_object($item) && isset($item->$key)) {
                $data[$key] = $item->$key;
            } elseif (is_array($item) && isset($item[$key])) {
                $data[$key] = $item[$key];
            } else {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
