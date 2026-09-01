<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams an uploaded attachment back to an authorized viewer.
 *
 * Authorization is evaluated per request in AttachmentService::authorize() —
 * never inferred from the URL — so a leaked id grants nothing on its own.
 */
class AttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachments,
    ) {}

    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $owner = $attachment->attachable;

        abort_unless($owner, 404, 'The record this file belonged to no longer exists.');

        return $this->attachments->download($attachment, $request->user(), $owner);
    }
}
