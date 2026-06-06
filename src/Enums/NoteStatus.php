<?php

declare(strict_types=1);

namespace Capell\Notes\Enums;

use Filament\Support\Contracts\HasLabel;

enum NoteStatus: string implements HasLabel
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return __('capell-notes::note.status.' . $this->value);
    }
}
