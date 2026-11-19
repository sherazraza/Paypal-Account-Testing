
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>PayPal Payment | Laravel</title>

    <script src="https://cdn.tailwindcss.com"></script>

    {{-- <script src="https://www.paypal.com/sdk/js?client-id={{ urlencode($clientId) }}&currency={{ urlencode($currency) }}&intent=capture"></script> --}}

    <script
    src="https://www.paypal.com/sdk/js?client-id={{ $clientId }}&currency={{ $currency }}&intent=capture"
    data-sdk-integration-source="button-factory">
</script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center p-5">

    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl p-8">

        <!-- Header -->
        <div class="text-center mb-8">

            <div class="text-3xl font-bold text-blue-600 mb-2">
                Secure Checkout
            </div>

            <p class="text-gray-500">
                Complete your payment securely with PayPal.
            </p>

        </div>

        <!-- Payment Details -->
        <div class="bg-gray-50 rounded-xl p-5 mb-6">

            <div class="flex justify-between mb-3">

                <span class="text-gray-600">
                    Product
                </span>

                <span class="font-semibold">
                    Premium Service
                </span>

            </div>

            <div class="flex justify-between items-center">

                <span class="text-gray-600">
                    Total Amount
                </span>

                <span class="text-2xl font-bold text-gray-900">
                    {{ $currency }} {{ number_format((float) $amount, 2) }}
                </span>

            </div>

        </div>

        <!-- Loading -->
        <div id="loading" class="hidden text-center text-blue-600 mb-4">
            Processing your payment...
        </div>

        <!-- PayPal Button -->
        <div id="paypal-button-container"></div>

        <!-- Response -->
        <div id="payment-message" class="hidden mt-6 rounded-lg p-4 text-sm">
        </div>

        <!-- Transaction Details -->
        <div id="transaction-details" class="hidden mt-5 border-t pt-5">

            <h3 class="font-bold text-gray-800 mb-3">
                Transaction Details
            </h3>

            <div id="transaction-content" class="text-sm space-y-2">
            </div>

        </div>

        <div class="text-center text-xs text-gray-400 mt-8">
            Secured by PayPal | Powered by Laravel
        </div>

    </div>


    <script>

        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]'
        ).content;

        const loading = document.getElementById('loading');

        const message = document.getElementById('payment-message');

        const transactionDetails = document.getElementById(
            'transaction-details'
        );

        function showMessage(text, type = 'success') {

            message.classList.remove('hidden');

            message.className = 'mt-6 rounded-lg p-4 text-sm';

            if (type === 'success') {
                message.classList.add(
                    'bg-green-100',
                    'text-green-800'
                );
            } else {
                message.classList.add(
                    'bg-red-100',
                    'text-red-800'
                );
            }

            message.textContent = text;
        }


        async function postRequest(url, data = {}) {

            const response = await fetch(url, {

                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },

                body: JSON.stringify(data),

            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message || 'Something went wrong.'
                );
            }

            return result;
        }

if (typeof paypal !== 'undefined') {
        paypal.Buttons({

            // Create order on Laravel backend.
            createOrder: async function () {

                loading.classList.remove('hidden');

                try {

                    const result = await postRequest(
                        '/paypal/create-order'
                    );

                    return result.orderID;

                } catch (error) {

                    showMessage(error.message, 'error');

                    throw error;

                } finally {

                    loading.classList.add('hidden');

                }

            },


            // Capture approved payment on Laravel backend.
            onApprove: async function (data) {

                loading.classList.remove('hidden');

                message.classList.add('hidden');

                try {

                    const result = await postRequest(
                        '/paypal/capture-order',
                        {
                            orderID: data.orderID
                        }
                    );

                    showMessage(
                        result.message,
                        'success'
                    );

                    // Display transaction details.
                    const transaction = result.transaction;

                    const content = document.getElementById(
                        'transaction-content'
                    );

                    content.replaceChildren();

                    const details = [
                        ['Order ID', transaction.order_id],
                        ['Capture ID', transaction.capture_id],
                        ['Amount', transaction.currency + ' ' + transaction.amount],
                        ['Status', transaction.status],
                    ];

                    details.forEach(([label, value]) => {

                        const row = document.createElement('div');

                        row.className = 'flex justify-between gap-3';

                        const name = document.createElement('span');
                        name.className = 'text-gray-500';
                        name.textContent = label;

                        const detail = document.createElement('span');
                        detail.className = 'font-medium text-right break-all';
                        detail.textContent = value ?? '';

                        row.append(name, detail);

                        content.appendChild(row);

                    });

                    transactionDetails.classList.remove('hidden');

                    document.getElementById(
                        'paypal-button-container'
                    ).classList.add('hidden');

                } catch (error) {

                    showMessage(error.message, 'error');

                } finally {

                    loading.classList.add('hidden');

                }

            },


            onCancel: function () {

                showMessage(
                    'Payment was cancelled by the user.',
                    'error'
                );

            },


            onError: function (error) {

                console.error('PayPal Error:', error);

                showMessage(
                    'An unexpected payment error occurred. Please try again.',
                    'error'
                );

            }

        }).render('#paypal-button-container');
} else {

    showMessage(
        'PayPal SDK failed to load. Please check your Client ID and internet connection.',
        'error'
    );

}
    </script>

</body>
</html>
