<?php

declare(strict_types=1);

namespace Capell\Notes\Facades;

use Capell\Notes\Support\NotesManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void registerSubject(string $modelClass)
 * @method static void registerParticipant(string $modelClass)
 * @method static void ensureSubject(Model $subject)
 * @method static void ensureParticipant(Model $participant)
 * @method static void clear()
 *
 * @see NotesManager
 */
final class CapellNotes extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NotesManager::class;
    }
}
