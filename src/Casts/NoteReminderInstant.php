<?php

declare(strict_types=1);

namespace Capell\Notes\Casts;

use Capell\Notes\Models\NoteReminder;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Override;

/** @implements CastsAttributes<CarbonImmutable, DateTimeInterface|string|null> */
final class NoteReminderInstant implements CastsAttributes
{
    /** @param array<string, mixed> $attributes */
    #[Override]
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : $this->date($value, $attributes);
    }

    /** @param array<string, mixed> $attributes */
    #[Override]
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : $this->date($value, $attributes)->format($model->getDateFormat());
    }

    /** @param array<string, mixed> $attributes */
    private function date(mixed $value, array $attributes): CarbonImmutable
    {
        // Versions 1/2 stored application-clock dates; never reinterpret them as UTC.
        $version = $attributes['schedule_version'] ?? 1;
        throw_unless(is_int($version) || (is_string($version) && ctype_digit($version)), InvalidArgumentException::class, 'A reminder schedule version must be an integer.');

        $timezone = (int) $version >= NoteReminder::UTC_SCHEDULE_VERSION
            ? 'UTC' : config()->string('app.timezone', 'UTC');
        throw_unless(is_string($value) || $value instanceof DateTimeInterface, InvalidArgumentException::class, 'A reminder instant must be a date or datetime string.');

        return ($value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value, $timezone))
            ->setTimezone($timezone);
    }
}
