<?php

declare(strict_types=1);

namespace Capell\Notes\Enums;

use Filament\Support\Contracts\HasLabel;

enum NoteVisibility: string implements HasLabel
{
    case RecordEditors = 'record_editors';
    case Private = 'private';

    public function getLabel(): string
    {
        return __('capell-notes::note.visibility.' . $this->value);
    }
}
