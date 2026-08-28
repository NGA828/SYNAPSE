<?php

namespace App\Http\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Shared list behaviour for every index endpoint: server-side search,
 * whitelisted sorting and bounded pagination.
 *
 * Query string contract:
 *   ?search=ada&sort=-created_at&per_page=25&page=2
 *
 * `per_page=all` is intentionally *not* supported — unbounded lists are what
 * we are fixing. Callers that genuinely need everything (exports) should use
 * chunking instead.
 */
trait HandlesPagination
{
    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, string>  $searchable  Columns matched with LIKE, dot notation allowed for relations
     * @param  array<int, string>  $sortable  Columns the client may sort by
     */
    protected function paginated(
        Builder $query,
        Request $request,
        array $searchable = [],
        array $sortable = [],
        string $defaultSort = '-id',
    ): LengthAwarePaginator {
        $this->applySearch($query, trim((string) $request->query('search', '')), $searchable);
        $this->applySort($query, (string) $request->query('sort', $defaultSort), $sortable, $defaultSort);

        return $query
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Resolve a bounded page size.
     */
    protected function perPage(Request $request): int
    {
        $default = (int) config('synapse.pagination.per_page', 15);
        $max = (int) config('synapse.pagination.max_per_page', 100);

        $requested = (int) $request->query('per_page', $default);

        if ($requested < 1) {
            $requested = $default;
        }

        return min($requested, $max);
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, string>  $searchable
     */
    protected function applySearch(Builder $query, string $term, array $searchable): void
    {
        if ($term === '' || $searchable === []) {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query->where(function (Builder $outer) use ($searchable, $like) {
            foreach ($searchable as $column) {
                if (! str_contains($column, '.')) {
                    $outer->orWhere($column, 'like', $like);

                    continue;
                }

                // "user.name" → whereHas('user', fn => where('name', like …))
                [$relation, $relationColumn] = explode('.', $column, 2);

                $outer->orWhereHas(
                    $relation,
                    fn (Builder $related) => $related->where($relationColumn, 'like', $like),
                );
            }
        });
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, string>  $sortable
     */
    protected function applySort(Builder $query, string $sort, array $sortable, string $default): void
    {
        $sort = $sort !== '' ? $sort : $default;

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-+');

        if (! in_array($column, $sortable, true)) {
            $column = ltrim($default, '-+');
            $direction = str_starts_with($default, '-') ? 'desc' : 'asc';
        }

        $query->orderBy($column, $direction);
    }
}
