<?php

namespace App\Contracts;

use App\Services\Ai\AnnouncementBrief;

/**
 * Writes an announcement from a brief.
 *
 * Mirrors CommentWriter: one contract, a deterministic implementation that
 * always works offline, and an optional provider behind it. Nothing in the
 * product depends on a model being reachable.
 *
 * A drafter never publishes. `AnnouncementService::create()` — and therefore
 * the fan-out, the notification channels and the audit trail — is untouched by
 * this. An administrator always presses Publish on text they can read first.
 */
interface AnnouncementDrafter
{
    /**
     * The implementation's identifier, recorded so a school can tell which
     * path produced the text they are about to publish.
     */
    public function name(): string;

    /**
     * @return array{title: string, body: string, short_body: string}
     */
    public function draft(AnnouncementBrief $brief): array;
}
