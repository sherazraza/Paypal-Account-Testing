<?php
    namespace App\Services;

    use Illuminate\Support\Facades\Http;
    use RuntimeException;

    class PayPalService
    {
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.paypal.base_url');
    }

    /**
     * Generate PayPal OAuth access token.
     */
    private function getAccessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(
                config('services.paypal.client_id'),
                config('services.paypal.client_secret')
            )
            ->acceptJson()
            ->timeout(30)
            ->post($this->baseUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'PayPal authentication failed: ' .
                $response->body()
            );
        }

        $token = $response->json('access_token');

        if (! $token) {
            throw new RuntimeException('PayPal access token missing.');
        }

        return $token;
    }

    /**
     * Create a PayPal order.
     */
    public function createOrder(string $amount, string $currency): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->withHeaders([
                'PayPal-Request-Id' => (string) str()->uuid(),
            ])
            ->timeout(30)
            ->post($this->baseUrl . '/v2/checkout/orders', [
                'intent'         => 'CAPTURE',

                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => $currency,
                            'value'         => $amount,
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'PayPal order creation failed: ' .
                $response->body()
            );
        }

        return $response->json();
    }

    /**
     * Capture an approved PayPal order.
     */
    public function captureOrder(string $orderId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->withHeaders([
                'PayPal-Request-Id' => 'capture-' . $orderId,
            ])
            ->timeout(30)
            ->withBody('', 'application/json')
            ->post(
                $this->baseUrl .
                '/v2/checkout/orders/' .
                rawurlencode($orderId) .
                '/capture'
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'PayPal capture failed: ' .
                $response->body()
            );
        }

        return $response->json();
    }
}