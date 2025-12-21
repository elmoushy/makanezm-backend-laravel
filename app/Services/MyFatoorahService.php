<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MyFatoorah Payment Gateway Service
 *
 * Handles integration with MyFatoorah payment gateway (Saudi Arabia).
 * Documentation: https://myfatoorah.readme.io/docs
 *
 * Flow:
 * 1. InitiatePayment - Create payment session and get payment URL
 * 2. User redirected to MyFatoorah to complete payment
 * 3. User redirected back to callback URL with PaymentId
 * 4. GetPaymentStatus - Verify payment was successful
 */
class MyFatoorahService
{
    private string $apiKey;

    private string $baseUrl;

    private string $countryIso;

    private bool $verifySSL;

    public function __construct()
    {
        $this->apiKey = config('services.myfatoorah.api_key');
        $this->baseUrl = config('services.myfatoorah.base_url');
        $this->countryIso = config('services.myfatoorah.country_iso');
        // Disable SSL verification in local/development environment
        $this->verifySSL = config('app.env') === 'production';
    }

    /**
     * Get configured HTTP client with proper headers and SSL settings.
     */
    private function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ]);

        // Disable SSL verification for development (NOT for production!)
        if (! $this->verifySSL) {
            $client = $client->withOptions([
                'verify' => false,
            ]);
        }

        return $client;
    }

    /**
     * Initiate a payment session with MyFatoorah.
     *
     * @param  array  $data  Payment data including:
     *                       - InvoiceValue: float (required) - Amount to charge
     *                       - CustomerName: string (required)
     *                       - CustomerEmail: string (optional)
     *                       - CustomerMobile: string (optional)
     *                       - CallBackUrl: string (required) - Success redirect URL
     *                       - ErrorUrl: string (required) - Error redirect URL
     *                       - Language: string (optional) - 'en' or 'ar'
     *                       - InvoiceItems: array (optional) - Line items
     *                       - CustomerReference: string (optional) - Order reference
     * @return array{success: bool, data?: array, error?: string}
     */
    public function initiatePayment(array $data): array
    {
        try {
            $payload = [
                'InvoiceValue' => $data['InvoiceValue'],
                'CustomerName' => $data['CustomerName'] ?? 'Customer',
                'DisplayCurrencyIso' => 'SAR',
                'MobileCountryCode' => '+966',
                'CallBackUrl' => $data['CallBackUrl'],
                'ErrorUrl' => $data['ErrorUrl'],
                'Language' => $data['Language'] ?? 'ar',
                'CustomerReference' => $data['CustomerReference'] ?? '',
                'SourceInfo' => 'Makanezm E-Commerce',
                'NotificationOption' => 'Lnk',
            ];

            if (! empty($data['CustomerMobile'])) {
                $payload['CustomerMobile'] = $data['CustomerMobile'];
            }

            if (! empty($data['CustomerEmail'])) {
                $payload['CustomerEmail'] = $data['CustomerEmail'];
            }

            // Add invoice items if provided
            if (! empty($data['InvoiceItems'])) {
                $payload['InvoiceItems'] = $data['InvoiceItems'];
            }

            Log::info('MyFatoorah: Initiating payment', [
                'invoice_value' => $payload['InvoiceValue'],
                'customer_reference' => $payload['CustomerReference'],
            ]);

            $response = $this->httpClient()->post($this->baseUrl.'/v2/SendPayment', $payload);

            $result = $response->json();

            if ($response->successful() && isset($result['IsSuccess']) && $result['IsSuccess'] === true) {
                Log::info('MyFatoorah: Payment initiated successfully', [
                    'invoice_id' => $result['Data']['InvoiceId'] ?? null,
                ]);

                return [
                    'success' => true,
                    'data' => $result['Data'],
                ];
            }

            $errorMessage = $result['Message'] ?? 'Unknown error from MyFatoorah';
            Log::error('MyFatoorah: Failed to initiate payment', [
                'error' => $errorMessage,
                'validation_errors' => $result['ValidationErrors'] ?? [],
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'validation_errors' => $result['ValidationErrors'] ?? [],
            ];

        } catch (\Exception $e) {
            Log::error('MyFatoorah: Exception during payment initiation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment gateway connection failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get payment status from MyFatoorah.
     *
     * @param  string  $paymentId  The PaymentId returned in callback
     * @param  string  $keyType  The type of key - 'PaymentId' or 'InvoiceId'
     * @return array{success: bool, data?: array, error?: string}
     */
    public function getPaymentStatus(string $paymentId, string $keyType = 'PaymentId'): array
    {
        try {
            Log::info('MyFatoorah: Getting payment status', [
                'key' => $paymentId,
                'key_type' => $keyType,
            ]);

            $response = $this->httpClient()->post($this->baseUrl.'/v2/GetPaymentStatus', [
                'Key' => $paymentId,
                'KeyType' => $keyType,
            ]);

            $result = $response->json();

            if ($response->successful() && isset($result['IsSuccess']) && $result['IsSuccess'] === true) {
                $paymentData = $result['Data'];
                $invoiceStatus = $paymentData['InvoiceStatus'] ?? 'Unknown';

                Log::info('MyFatoorah: Payment status retrieved', [
                    'invoice_id' => $paymentData['InvoiceId'] ?? null,
                    'status' => $invoiceStatus,
                    'invoice_value' => $paymentData['InvoiceValue'] ?? null,
                ]);

                return [
                    'success' => true,
                    'data' => $paymentData,
                    'is_paid' => $invoiceStatus === 'Paid',
                    'invoice_status' => $invoiceStatus,
                ];
            }

            $errorMessage = $result['Message'] ?? 'Unknown error from MyFatoorah';
            Log::error('MyFatoorah: Failed to get payment status', [
                'error' => $errorMessage,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
            ];

        } catch (\Exception $e) {
            Log::error('MyFatoorah: Exception getting payment status', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to verify payment: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Execute payment using a specific payment method.
     * Alternative to SendPayment - provides more control over payment methods.
     *
     * @param  array  $data  Payment data
     * @param  int|null  $paymentMethodId  Specific payment method ID (null for all methods)
     * @return array{success: bool, data?: array, error?: string}
     */
    public function executePayment(array $data, ?int $paymentMethodId = null): array
    {
        try {
            $payload = [
                'InvoiceValue' => $data['InvoiceValue'],
                'PaymentMethodId' => $paymentMethodId, // null = show all available methods
                'CustomerName' => $data['CustomerName'] ?? 'Customer',
                'DisplayCurrencyIso' => 'SAR',
                'MobileCountryCode' => '+966',
                'CustomerMobile' => $data['CustomerMobile'] ?? '',
                'CustomerEmail' => $data['CustomerEmail'] ?? '',
                'CallBackUrl' => $data['CallBackUrl'],
                'ErrorUrl' => $data['ErrorUrl'],
                'Language' => $data['Language'] ?? 'ar',
                'CustomerReference' => $data['CustomerReference'] ?? '',
                'SourceInfo' => 'Makanezm E-Commerce',
            ];

            // Add invoice items if provided
            if (! empty($data['InvoiceItems'])) {
                $payload['InvoiceItems'] = $data['InvoiceItems'];
            }

            Log::info('MyFatoorah: Executing payment', [
                'invoice_value' => $payload['InvoiceValue'],
                'payment_method_id' => $paymentMethodId,
            ]);

            $response = $this->httpClient()->post($this->baseUrl.'/v2/ExecutePayment', $payload);

            $result = $response->json();

            if ($response->successful() && isset($result['IsSuccess']) && $result['IsSuccess'] === true) {
                return [
                    'success' => true,
                    'data' => $result['Data'],
                ];
            }

            return [
                'success' => false,
                'error' => $result['Message'] ?? 'Unknown error from MyFatoorah',
                'validation_errors' => $result['ValidationErrors'] ?? [],
            ];

        } catch (\Exception $e) {
            Log::error('MyFatoorah: Exception during execute payment', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment execution failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get available payment methods.
     *
     * @param  float  $invoiceValue  The invoice amount (some methods have min/max limits)
     * @return array{success: bool, data?: array, error?: string}
     */
    public function getPaymentMethods(float $invoiceValue = 100): array
    {
        try {
            $response = $this->httpClient()->post($this->baseUrl.'/v2/InitiatePayment', [
                'InvoiceAmount' => $invoiceValue,
                'CurrencyIso' => 'SAR',
            ]);

            $result = $response->json();

            if ($response->successful() && isset($result['IsSuccess']) && $result['IsSuccess'] === true) {
                return [
                    'success' => true,
                    'data' => $result['Data']['PaymentMethods'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => $result['Message'] ?? 'Failed to get payment methods',
            ];

        } catch (\Exception $e) {
            Log::error('MyFatoorah: Exception getting payment methods', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to get payment methods: '.$e->getMessage(),
            ];
        }
    }
}
