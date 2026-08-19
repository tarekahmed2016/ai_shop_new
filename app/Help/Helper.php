<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

function target(): string
{
    /** @var User|null $user */
    $user = auth()->user();
    if (! $user) {
        return '/';
    }

    return 'dashboard';
}

/**
 * Get the next available ordering value for a given model.
 */
function nextOrdering(Builder $model): int
{
    return (int) $model->max('ordering') + 1;
}

/**
 * Shift ordering values for a given model.
 *
 * Use 'up' to increment (make room for a new/moved item): affects ordering >= $from.
 * Use 'down' to decrement (fill a gap after removal): affects ordering > $from.
 *
 * @param  'up'|'down'  $direction
 */
function shiftOrdering(Builder $model, int $from, string $direction, ?int $to = null, ?int $excludeId = null): void
{
    $operator = $direction === 'up' ? '>=' : '>';
    $query = $model->where('ordering', $operator, $from);
    if ($to !== null) {
        $query->where('ordering', '<=', $to);
    }
    if ($excludeId !== null) {
        $query->where('id', '!=', $excludeId);
    }
    $direction === 'up' ? $query->increment('ordering') : $query->decrement('ordering');
}
