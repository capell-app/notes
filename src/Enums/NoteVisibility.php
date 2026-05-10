<?php

declare(strict_types=1);

namespace Capell\Notes\Enums;

enum NoteVisibility: string
{
    case RecordEditors = 'record_editors';
    case Private = 'private';
}
