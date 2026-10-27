<?php

declare(strict_types=1);

namespace Capell\Notes\Filament\Forms\Components;

use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Override;

final class ReminderDateTimePicker extends DateTimePicker
{
    /** @return array<StateCast> */
    #[Override]
    public function getDefaultStateCasts(): array
    {
        // This creation field carries a wall time, not an application-zone instant.
        // The action interprets it once, after validating the selected timezone.
        return [];
    }
}
