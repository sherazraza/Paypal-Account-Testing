<?php
    namespace App\Http\Controllers;

    use App\Models\Payment;
    use App\Services\PayPalService;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Log;
    use Throwable;

    class PayPalController extends Controller
    {
    public function index()
    {
        return view('payment', [
            'clientId' => config('services.paypal.client_id'),
            'currency' => config('services.paypal.currency'),
            'amount'   => config('services.paypal.amount'),
        ]);
    }

    /**
     * Create PayPal order.
     */
    public function createOrder(PayPalService $paypal): JsonResponse
    {
        try {
            // Never accept the payment amount from JavaScript.
            $amount   = (string) config('services.paypal.amount');
            $currency = config('services.paypal.currency');

            if (! is_numeric($amount) || (float) $amount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment configuration.',
                ], 500);
            }

            // Save local transaction first.
            $payment = Payment::create([
                'amount'   => $amount,
                'currency' => $currency,
                'status'   => 'pending',
            ]);

            // Create order with PayPal.
            $order = $paypal->createOrder($amount, $currency);

            if (
                empty($order['id']) ||
                ($order['status'] ?? null) !== 'CREATED'
            ) {
                throw new \RuntimeException(
                    'Invalid PayPal order response.'
                );
            }

            $payment->update([
                'paypal_order_id' => $order['id'],
                'response_data'   => $order,
            ]);

            return response()->json([
                'success' => true,
                'orderID' => $order['id'],
            ]);

        } catch (Throwable $e) {
            Log::error('PayPal Create Order Error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create PayPal order.',
            ], 500);
        }
    }

    /**
     * Capture PayPal payment.
     */
    public function captureOrder(
        Request $request,
        PayPalService $paypal
    ): JsonResponse {
        $validated = $request->validate([
            'orderID' => ['required', 'string', 'max:255'],
        ]);

        try {
            $payment = Payment::where(
                'paypal_order_id',
                $validated['orderID']
            )->first();

            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment order not found.',
                ], 404);
            }

            // Prevent duplicate successful captures.
            if ($payment->status === 'completed') {
                return response()->json([
                    'success'     => true,
                    'message'     => 'Payment already completed.',
                    'transaction' => [
                        'order_id'   => $payment->paypal_order_id,
                        'capture_id' => $payment->paypal_capture_id,
                        'amount'     => $payment->amount,
                        'currency'   => $payment->currency,
                        'status'     => $payment->status,
                    ],
                ]);
            }

            if ($payment->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This payment cannot be captured.',
                ], 409);
            }

            $result = $paypal->captureOrder(
                $payment->paypal_order_id
            );

            if (($result['status'] ?? null) !== 'COMPLETED') {
                $payment->update([
                    'status'        => 'failed',
                    'response_data' => $result,
                ]);

                return response()->json([
                    'success'       => false,
                    'message'       => 'Payment was not completed.',
                    'paypal_status' => $result['status'] ?? 'UNKNOWN',
                ], 422);
            }

            // Extract actual capture information.
            $capture = $result['purchase_units'][0]
            ['payments']['captures'][0] ?? null;

            if (! $capture || ($capture['status'] ?? null) !== 'COMPLETED') {
                throw new \RuntimeException(
                    'PayPal capture details are invalid.'
                );
            }

            $capturedAmount   = $capture['amount']['value'] ?? null;
            $capturedCurrency = $capture['amount']['currency_code'] ?? null;

            // Verify amount and currency against our database.
            if (
                $capturedAmount !== $payment->amount ||
                $capturedCurrency !== $payment->currency
            ) {
                throw new \RuntimeException(
                    'Captured payment amount or currency mismatch.'
                );
            }

            $captureId = $capture['id'] ?? null;

            if (! $captureId) {
                throw new \RuntimeException(
                    'PayPal capture ID missing.'
                );
            }

            $payerEmail = $result['payer']['email_address'] ?? null;

            // Update the database only after verification.
            DB::transaction(function () use (
                $payment,
                $captureId,
                $payerEmail,
                $result
            ) {
                $lockedPayment = Payment::whereKey($payment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedPayment->status === 'completed') {
                    return;
                }

                $lockedPayment->update([
                    'paypal_capture_id' => $captureId,
                    'payer_email'       => $payerEmail,
                    'status'            => 'completed',
                    'response_data'     => $result,
                ]);
            });

            return response()->json([
                'success'     => true,
                'message'     => 'Payment completed successfully!',

                'transaction' => [
                    'order_id'    => $payment->paypal_order_id,
                    'capture_id'  => $captureId,
                    'payer_email' => $payerEmail,
                    'amount'      => $payment->amount,
                    'currency'    => $payment->currency,
                    'status'      => 'completed',
                ],
            ]);

        } catch (Throwable $e) {
            Log::error('PayPal Capture Error', [
                'order_id' => $validated['orderID'],
                'message'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to verify or capture payment.',
            ], 500);
        }
    }
}