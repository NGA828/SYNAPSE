<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;

/**
 * Public authenticity check for a printed document. Anyone holding the paper
 * can confirm it was really issued by the school — no login required, and no
 * personal data beyond what is already printed on the page.
 */
class DocumentVerificationController extends Controller
{
    public function __construct(
        private readonly DocumentService $documents,
    ) {}

    public function show(string $code): JsonResponse
    {
        $result = $this->documents->verify($code);

        if (! $result) {
            return response()->json([
                'valid' => false,
                'message' => 'No document matches this verification code.',
            ], 404);
        }

        return response()->json($result);
    }
}
