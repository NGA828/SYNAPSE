<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly DocumentService $documents,
    ) {}

    /**
     * Download the PDF receipt for one of the school's own payments.
     */
    public function show(Request $request, Payment $payment): Response
    {
        abort_unless(
            $request->user()->isSuperAdmin() || $payment->school_id === $request->user()->school_id,
            403,
            'This payment belongs to another school.',
        );

        $pdf = $this->documents->receiptBytes($payment);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="receipt-'.$payment->reference.'.pdf"',
        ]);
    }
}
