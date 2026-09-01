<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAnnouncementDraftRequest;
use App\Services\AnnouncementDraftService;
use Illuminate\Http\JsonResponse;

/**
 * Drafts an announcement. Deliberately separate from `store()`.
 *
 * Nothing is published and nothing is persisted here — the administrator reads
 * the draft, edits whatever is wrong, and publishes through the existing
 * endpoint. Keeping drafting out of `AnnouncementService` means the fan-out,
 * the notification channels and the audit trail cannot be affected by a draft.
 */
class AnnouncementDraftController extends Controller
{
    public function __construct(
        private readonly AnnouncementDraftService $drafts,
    ) {}

    public function store(StoreAnnouncementDraftRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->drafts->draft($request->user(), $request->validated()),
        ]);
    }
}
