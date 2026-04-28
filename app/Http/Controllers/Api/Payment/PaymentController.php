<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\PaymentResource;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * API Payment Controller
 * 
 * Handles payment operations for API consumers.
 * Designed for Agentic AI integration with clear response formats.
 * 
 * @package App\Http\Controllers\Api\Payment
 * @version 1.0.0
 */
class PaymentController extends ApiController
{
    /**
     * List Payments
     * 
     * Retrieve payments with filtering options.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @queryParam status string optional Filter by status. Example: "completed"
     * @queryParam payment_method string optional Filter by payment method. Example: "card"
     * @queryParam per_page integer optional Items per page. Example: 15
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Payment::query()
            ->with(['booking.employer', 'booking.maid']);

        // Filter by user role
        if ($user->hasRole('employer')) {
            $query->whereHas('booking', function ($q) use ($user) {
                $q->where('employer_id', $user->id);
            });
        } elseif ($user->hasRole('maid')) {
            $query->whereHas('booking', function ($q) use ($user) {
                $q->where('maid_id', $user->id);
            });
        }
        // Admins see all payments

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $perPage = min($request->get('per_page', 15), 100);
        $payments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginated(
            PaymentResource::collection($payments),
            $payments,
            'Payments retrieved successfully'
        );
    }

    /**
     * Get Payment
     * 
     * Retrieve a specific payment by ID.
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $payment = Payment::with(['booking.employer', 'booking.maid', 'booking.preference'])
            ->find($id);

        if (!$payment) {
            return $this->notFound('Payment not found');
        }

        // Check authorization
        if (!$user->hasRole('admin')) {
            $booking = $payment->booking;
            if ($booking->employer_id !== $user->id && $booking->maid_id !== $user->id) {
                return $this->forbidden('You do not have permission to view this payment');
            }
        }

        return $this->success(
            new PaymentResource($payment),
            'Payment retrieved successfully'
        );
    }

    /**
     * Initialize Payment
     * 
     * Initialize a new payment for a booking.
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @bodyParam booking_id integer required Booking ID. Example: 1
     * @bodyParam amount integer required Payment amount. Example: 50000
     * @bodyParam payment_method string required Payment method. Example: "card"
     * @bodyParam payment_type string required Payment type. Example: "booking_fee"
     * @bodyParam metadata object optional Additional metadata. Example: {"card_last4": "1234"}
     */
    public function initialize(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('employer')) {
            return $this->forbidden('Only employers can initialize payments');
        }

        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|integer|exists:bookings,id',
            'amount' => 'required|integer|min:1000',
            'payment_method' => 'required|string|in:card,bank_transfer,ussd,mobile_money',
            'payment_type' => 'required|string|in:booking_fee,deposit,full_payment,subscription',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        // Verify booking belongs to employer
        $booking = Booking::where('id', $request->booking_id)
            ->where('employer_id', $user->id)
            ->first();

        if (!$booking) {
            return $this->forbidden('Booking not found or does not belong to you');
        }

        // Check if payment already exists
        $existingPayment = Payment::where('booking_id', $request->booking_id)
            ->where('status', 'completed')
            ->first();

        if ($existingPayment) {
            return $this->error(
                'Payment already completed for this booking',
                Response::HTTP_CONFLICT,
                null,
                'PAYMENT_EXISTS'
            );
        }

        // Generate unique reference
        $reference = 'PAY-' . strtoupper(uniqid() . mt_rand(1000, 9999));

        $payment = Payment::create([
            'booking_id' => $request->booking_id,
            'employer_id' => $user->id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'payment_type' => $request->payment_type,
            'status' => 'pending',
            'reference' => $reference,
            'currency' => 'NGN',
            'metadata' => $request->metadata ?? [],
        ]);

        // Update booking payment status
        $booking->update(['payment_status' => 'pending']);

        return $this->success(
            new PaymentResource($payment->load(['booking.employer', 'booking.maid'])),
            'Payment initialized successfully',
            [
                'payment_reference' => $reference,
                'next_step' => 'Complete payment using the reference',
            ],
            Response::HTTP_CREATED
        );
    }

    /**
     * Verify Payment
     * 
     * Verify a payment status.
     * 
     * @param Request $request
     * @param string $reference
     * @return JsonResponse
     */
    public function verify(Request $request, string $reference): JsonResponse
    {
        $user = $request->user();

        $payment = Payment::with(['booking.employer', 'booking.maid'])
            ->where('reference', $reference)
            ->first();

        if (!$payment) {
            return $this->notFound('Payment not found');
        }

        // Check authorization
        if (!$user->hasRole('admin')) {
            $booking = $payment->booking;
            if ($booking->employer_id !== $user->id && $booking->maid_id !== $user->id) {
                return $this->forbidden('You do not have permission to view this payment');
            }
        }

        return $this->success(
            new PaymentResource($payment),
            'Payment status retrieved successfully',
            [
                'is_completed' => $payment->status === 'completed',
                'is_pending' => $payment->status === 'pending',
                'is_failed' => $payment->status === 'failed',
            ]
        );
    }

    /**
     * Process Payment (Webhook)
     * 
     * Process payment webhook from payment provider.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function webhook(Request $request): JsonResponse
    {
        // Validate webhook signature (implementation depends on payment provider)
        // This is a placeholder for webhook handling

        $validator = Validator::make($request->all(), [
            'reference' => 'required|string',
            'status' => 'required|string|in:success,failed,pending',
            'amount' => 'required|integer',
            'transaction_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $payment = Payment::where('reference', $request->reference)->first();

        if (!$payment) {
            return $this->notFound('Payment not found');
        }

        // Update payment status
        $newStatus = $request->status === 'success' ? 'completed' : ($request->status === 'failed' ? 'failed' : 'pending');

        $payment->update([
            'status' => $newStatus,
            'transaction_id' => $request->transaction_id,
            'paid_at' => $newStatus === 'completed' ? now() : null,
        ]);

        // Update booking payment status
        $booking = $payment->booking;
        if ($booking) {
            $booking->update(['payment_status' => $newStatus === 'completed' ? 'paid' : $newStatus]);
        }

        return $this->success(
            new PaymentResource($payment),
            'Payment processed successfully'
        );
    }

    /**
     * Get Payment Methods
     * 
     * Retrieve available payment methods.
     * 
     * @return JsonResponse
     */
    public function getPaymentMethods(): JsonResponse
    {
        $methods = [
            'card' => [
                'label' => 'Credit/Debit Card',
                'description' => 'Pay with Visa, Mastercard, Verve',
                'icon' => 'credit-card',
                'enabled' => true,
            ],
            'bank_transfer' => [
                'label' => 'Bank Transfer',
                'description' => 'Pay via bank transfer',
                'icon' => 'bank',
                'enabled' => true,
            ],
            'ussd' => [
                'label' => 'USSD',
                'description' => 'Pay using USSD code',
                'icon' => 'mobile',
                'enabled' => true,
            ],
            'mobile_money' => [
                'label' => 'Mobile Money',
                'description' => 'Pay with mobile money',
                'icon' => 'wallet',
                'enabled' => false, // Not yet enabled
            ],
        ];

        return $this->success(
            ['payment_methods' => $methods],
            'Payment methods retrieved successfully'
        );
    }

    /**
     * Get Payment Statistics
     * 
     * Retrieve payment statistics for the user.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Payment::query();

        // Filter by user role
        if ($user->hasRole('employer')) {
            $query->where('employer_id', $user->id);
        } elseif ($user->hasRole('maid')) {
            $query->whereHas('booking', function ($q) use ($user) {
                $q->where('maid_id', $user->id);
            });
        }

        $stats = [
            'total_payments' => $query->count(),
            'total_amount_paid' => (clone $query)->where('status', 'completed')->sum('amount'),
            'pending_payments' => (clone $query)->where('status', 'pending')->count(),
            'pending_amount' => (clone $query)->where('status', 'pending')->sum('amount'),
            'failed_payments' => (clone $query)->where('status', 'failed')->count(),
            'completed_payments' => (clone $query)->where('status', 'completed')->count(),
            'by_payment_method' => (clone $query)
                ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
                ->where('status', 'completed')
                ->groupBy('payment_method')
                ->get(),
        ];

        return $this->success($stats, 'Payment statistics retrieved successfully');
    }

    /**
     * Retry Failed Payment
     * 
     * Retry a failed payment.
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function retry(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $payment = Payment::find($id);

        if (!$payment) {
            return $this->notFound('Payment not found');
        }

        // Check authorization
        if (!$user->hasRole('admin') && $payment->employer_id !== $user->id) {
            return $this->forbidden('You do not have permission to retry this payment');
        }

        if ($payment->status !== 'failed') {
            return $this->error(
                'Only failed payments can be retried',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                null,
                'INVALID_STATUS'
            );
        }

        // Generate new reference
        $newReference = 'PAY-' . strtoupper(uniqid() . mt_rand(1000, 9999));

        $payment->update([
            'status' => 'pending',
            'reference' => $newReference,
            'metadata' => array_merge($payment->metadata ?? [], [
                'retried_from' => $payment->reference,
                'retried_at' => now()->toIso8601String(),
            ]),
        ]);

        return $this->success(
            new PaymentResource($payment->load(['booking.employer', 'booking.maid'])),
            'Payment retry initialized',
            ['new_reference' => $newReference]
        );
    }
}
