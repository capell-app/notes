<?php

declare(strict_types=1);

namespace Capell\Notes\Enums;

use Filament\Support\Contracts\HasLabel;

enum NoteReminderRecurrence: string implements HasLabel
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function getLabel(): string
    {
        return __('capell-notes::note.recurrence.' . $this->value);
    }
}
