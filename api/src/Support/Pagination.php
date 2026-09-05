<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class Pagination
{
    public static function toJson(Builder $query, array $params): string
    {
        $perPage = max(1, (int) ($params['per_page'] ?? 20));
        $page = max(1, (int) $params['page']);

        $total = (clone $query)->toBase()->getCountForPagination();
        $items = $query->forPage($page, $perPage)->get();

        return json_encode([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }
}
