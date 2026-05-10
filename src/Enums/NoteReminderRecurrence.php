<?php

declare(strict_types=1);

namespace Capell\Notes\Enums;

enum NoteReminderRecurrence: string
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
