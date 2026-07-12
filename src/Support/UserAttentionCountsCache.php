<?php

declare(strict_types=1);

namespace Capell\Notes\Support;

use Capell\Notes\Actions\BuildUserAttentionCountsAction;
use Capell\Notes\Data\UserAttentionCountData;
use Illuminate\Database\Eloquent\Model;

final class UserAttentionCountsCache
{
    /** @var array<string, UserAttentionCountData> */
    private array $counts = [];

    public function forUser(Model $user): UserAttentionCountData
    {
        $cacheKey = $this->cacheKey($user);

        if (! isset($this->counts[$cacheKey])) {
            $this->counts[$cacheKey] = BuildUserAttentionCountsAction::run($user);
        }

        return $this->counts[$cacheKey];
    }

    public function forgetUser(Model $user): void
    {
        unset($this->counts[$this->cacheKey($user)]);
    }

    private function cacheKey(Model $user): string
    {
        return $user->getMorphClass() . ':' . $this->stringValue($user->getKey());
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value)
            ? (string) $value
            : '';
    }
}
