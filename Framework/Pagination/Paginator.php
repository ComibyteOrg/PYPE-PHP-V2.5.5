<?php

namespace Framework\Pagination;

/**
 * Pagination — offset and cursor pagination with meta.
 *
 * Usage:
 * $paginator = Paginator::make($items, $total, 15);
 * echo $paginator->links();
 *
 * $cursor = CursorPaginator::make($items, 15);
 * echo $cursor->links();
 */
class Paginator
{
    protected int $currentPage;
    protected int $perPage;
    protected int $total;
    protected int $lastPage;
    protected array $items;
    protected ?string $path = null;
    protected string $pageName = 'page';

    public static function make(array $items, int $total, int $perPage = 15, ?int $page = null): self
    {
        return new self($items, $total, $perPage, $page);
    }

    public function __construct(array $items, int $total, int $perPage = 15, ?int $page = null)
    {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->lastPage = max(1, (int) ceil($total / $perPage));
        $this->currentPage = min(max(1, $page ?? $this->resolvePage()), $this->lastPage);
    }

    public function items(): array
    {
        return $this->items;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function firstItem(): ?int
    {
        if ($this->total === 0) return null;
        return ($this->currentPage - 1) * $this->perPage + 1;
    }

    public function lastItem(): ?int
    {
        if ($this->total === 0) return null;
        return min($this->currentPage * $this->perPage, $this->total);
    }

    public function from(): ?int
    {
        return $this->firstItem();
    }

    public function to(): ?int
    {
        return $this->lastItem();
    }

    public function nextPageUrl(): ?string
    {
        return $this->hasMorePages() ? $this->url($this->currentPage + 1) : null;
    }

    public function previousPageUrl(): ?string
    {
        return $this->currentPage > 1 ? $this->url($this->currentPage - 1) : null;
    }

    public function url(int $page): string
    {
        $path = $this->path ?? $_SERVER['REQUEST_URI'] ?? '?';
        $separator = str_contains($path, '?') ? '&' : '?';
        return rtrim($path, '?&') . $separator . $this->pageName . '=' . $page;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'data' => $this->items,
            'first_page_url' => $this->url(1),
            'from' => $this->from(),
            'last_page' => $this->lastPage,
            'last_page_url' => $this->url($this->lastPage),
            'links' => $this->linkArray(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path ?? '',
            'per_page' => $this->perPage,
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->to(),
            'total' => $this->total,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    public function meta(): array
    {
        return [
            'current_page' => $this->currentPage,
            'from' => $this->from(),
            'last_page' => $this->lastPage,
            'per_page' => $this->perPage,
            'to' => $this->to(),
            'total' => $this->total,
        ];
    }

    public function links(string $view = 'simple'): string
    {
        if (!$this->hasPages()) {
            return '';
        }

        if ($view === 'simple') {
            return $this->simpleLinks();
        }

        return $this->fullLinks();
    }

    public function simpleLinks(): string
    {
        $html = '<nav class="pagination-simple"><ul>';
        $html .= '<li class="' . ($this->currentPage <= 1 ? 'disabled' : '') . '">';
        $html .= '<a href="' . ($this->previousPageUrl() ?: '#') . '">Previous</a>';
        $html .= '</li>';
        $html .= '<li class="' . (!$this->hasMorePages() ? 'disabled' : '') . '">';
        $html .= '<a href="' . ($this->nextPageUrl() ?: '#') . '">Next</a>';
        $html .= '</li>';
        $html .= '</ul></nav>';
        return $html;
    }

    public function fullLinks(): string
    {
        $html = '<nav class="pagination"><ul>';

        $html .= '<li class="' . ($this->currentPage <= 1 ? 'disabled' : '') . '">';
        $html .= '<a href="' . ($this->previousPageUrl() ?: '#') . '">&laquo;</a>';
        $html .= '</li>';

        $range = $this->getLinkRange();
        foreach ($range as $page) {
            $isCurrent = $page === $this->currentPage;
            $html .= '<li class="' . ($isCurrent ? 'active' : '') . '">';
            $html .= '<a href="' . $this->url($page) . '">' . $page . '</a>';
            $html .= '</li>';
        }

        $html .= '<li class="' . (!$this->hasMorePages() ? 'disabled' : '') . '">';
        $html .= '<a href="' . ($this->nextPageUrl() ?: '#') . '">&raquo;</a>';
        $html .= '</li>';
        $html .= '</ul></nav>';
        return $html;
    }

    protected function getLinkRange(): array
    {
        $window = 3;
        $start = max(1, $this->currentPage - $window);
        $end = min($this->lastPage, $this->currentPage + $window);
        return range($start, $end);
    }

    protected function linkArray(): array
    {
        $links = [];
        $range = $this->getLinkRange();
        foreach ($range as $page) {
            $links[] = [
                'url' => $this->url($page),
                'label' => (string) $page,
                'active' => $page === $this->currentPage,
            ];
        }
        return $links;
    }

    protected function resolvePage(): int
    {
        return (int) ($_GET[$this->pageName] ?? 1);
    }
}

class CursorPaginator
{
    protected array $items;
    protected int $perPage;
    protected ?string $nextCursor = null;
    protected ?string $cursor = null;
    protected string $cursorName = 'cursor';

    public static function make(array $items, int $perPage = 15, ?string $cursor = null): self
    {
        return new self($items, $perPage, $cursor);
    }

    public function __construct(array $items, int $perPage = 15, ?string $cursor = null)
    {
        $this->perPage = $perPage;
        $this->cursor = $cursor ?? ($_GET[$this->cursorName] ?? null);

        if (count($items) > $perPage) {
            array_pop($items);
            $this->hasMore = true;
            $this->nextCursor = base64_encode(end($items)['id'] ?? time());
        }

        $this->items = $items;
    }

    public function items(): array
    {
        return $this->items;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }

    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'next_cursor' => $this->nextCursor,
            'has_more' => $this->hasMore(),
        ];
    }

    public function links(): string
    {
        if (!$this->hasMore()) {
            return '';
        }
        $url = $this->nextCursorUrl();
        return "<nav class=\"cursor-pagination\"><a href=\"{$url}\">Load More</a></nav>";
    }

    protected function nextCursorUrl(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '?';
        $separator = str_contains($path, '?') ? '&' : '?';
        return rtrim($path, '?&') . $separator . $this->cursorName . '=' . $this->nextCursor;
    }
}
