<?php

namespace EloquentWorks\RatingKit\Enums;

/**
 * Enum MatchStatus
 *
 * Represents the possible statuses of a match.
 */
enum MatchStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Voided = 'voided';
    case Failed = 'failed';
}
