<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Bill;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function index(Request $request, Bill $bill)
    {
        $this->authorize('view', $bill);

        $payments = $bill->payments()->with('creator')->orderByDesc('payment_date')->get();

        return response()->json(['success' => true, 'data' => PaymentResource::collection($payments)]);
    }

    public function store(StorePaymentRequest $request, Bill $bill)
    {
        $payment = $this->paymentService->record($bill, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded successfully.',
            'data' => new PaymentResource($payment),
        ], 201);
    }
}
