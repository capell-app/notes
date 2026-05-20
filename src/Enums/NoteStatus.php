<?php

declare(strict_types=1);

namespace Capell\Notes\Enums;

enum NoteStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
    case Archived = 'archived';
}
