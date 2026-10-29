<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Services;

use Madgeek\LaravelShiprocket\Exceptions\InsufficientBalanceException;
use Madgeek\LaravelShiprocket\Exceptions\RequestFailedException;
use Madgeek\LaravelShiprocket\Exceptions\ShipmentPipelineException;
use Madgeek\LaravelShiprocket\Support\Delivery;
use Madgeek\LaravelShiprocket\Support\FulfillmentResult;
use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;
use Throwable;

final class FulfillmentService
{
    private const string FORWARD_SHIPMENT_ENDPOINT = '/v1/external/shipments/create/forward-shipment';

    /** @var list<string> */
    private const array WRAPPER_OPTION_KEYS = [
        'mode',
        'request_pickup',
        'print_label',
        'generate_manifest',
        'courier_id',
        'reseller_name',
        'ewaybill_no',
        'vendor_details',
        'isd_code',
        'billing_isd_code',
    ];

    public function __construct(
        private readonly Delivery $delivery,
        private readonly AccountService $account,
        private readonly CourierService $couriers,
        private readonly DocumentService $documents,
    ) {}

    /**
     * Create an order, assign AWB, and generate label/manifest/pickup/invoice in one flow.
     *
     * Checks wallet balance first. Uses Shiprocket's forward-shipment wrapper, then
     * optionally generates an invoice. On later-step failures, throws
     * {@see ShipmentPipelineException} with a partial {@see FulfillmentResult}.
     *
     * @param  array<string, mixed>  $order
     * @param  array{
     *     courier_id?: int,
     *     request_pickup?: bool,
     *     print_label?: bool,
     *     generate_manifest?: bool,
     *     generate_invoice?: bool,
     *     min_balance?: float|int|string,
     *     pickup_postcode?: int|string,
     *     mode?: string,
     *     reseller_name?: string,
     *     ewaybill_no?: string,
     *     vendor_details?: array<string, mixed>,
     *     isd_code?: string,
     *     billing_isd_code?: string
     * }  $options
     */
    public function create(array $order, array $options = []): FulfillmentResult
    {
        $generateInvoice = (bool) ($options['generate_invoice'] ?? true);

        $walletBalance = $this->requireSufficientBalance($order, $options);

        $payload = $this->forwardShipmentPayload($order, $options);
        $forward = $this->delivery->send('POST', self::FORWARD_SHIPMENT_ENDPOINT, $payload);

        $result = $this->resultFromForward($forward, $walletBalance);

        if ((int) $forward->json('status', 0) !== 1 || $result->orderId() === null) {
            throw new ShipmentPipelineException(
                $this->forwardErrorMessage($forward),
                'forward',
                $result,
            );
        }

        if (! $generateInvoice) {
            return $result;
        }

        return $this->attachInvoice($result);
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $options
     */
    private function requireSufficientBalance(array $order, array $options): float
    {
        $wallet = $this->account->walletBalance();
        $balance = $this->toFloat($wallet->json('data.balance_amount', 0));
        $required = $this->requiredBalance($order, $options);

        if ($balance < $required) {
            throw new InsufficientBalanceException(
                "Shiprocket wallet balance {$balance} is below the required amount {$required}.",
                $balance,
                $required,
            );
        }

        return $balance;
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $options
     */
    private function requiredBalance(array $order, array $options): float
    {
        if (array_key_exists('min_balance', $options) && $options['min_balance'] !== null) {
            return $this->toFloat($options['min_balance']);
        }

        $pickup = $options['pickup_postcode'] ?? null;
        $delivery = $this->deliveryPostcode($order);
        $weight = $order['weight'] ?? null;

        if ($pickup === null || $delivery === null || $weight === null || $weight === '') {
            return 0.0;
        }

        $cod = $this->isCod($order);
        $courierId = isset($options['courier_id']) ? (int) $options['courier_id'] : null;

        $couriers = $this->couriers->availableCouriers($pickup, $delivery, $cod, $weight)
            ->json('data.available_courier_companies', []);

        if (! is_array($couriers) || $couriers === []) {
            return 0.0;
        }

        $rate = $this->estimateRate($couriers, $courierId);

        return $rate ?? 0.0;
    }

    /**
     * @param  list<array<array-key, mixed>>|array<array-key, mixed>  $couriers
     */
    private function estimateRate(array $couriers, ?int $courierId): ?float
    {
        $rates = [];

        foreach ($couriers as $courier) {
            if (! is_array($courier)) {
                continue;
            }

            $id = isset($courier['courier_company_id']) ? (int) $courier['courier_company_id'] : null;
            $rate = isset($courier['rate']) ? $this->toFloat($courier['rate']) : null;

            if ($rate === null) {
                continue;
            }

            if ($courierId !== null) {
                if ($id === $courierId) {
                    return $rate;
                }

                continue;
            }

            $rates[] = $rate;
        }

        return $rates === [] ? null : min($rates);
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function forwardShipmentPayload(array $order, array $options): array
    {
        $payload = $order;

        $payload['request_pickup'] = (bool) ($options['request_pickup'] ?? true);
        $payload['print_label'] = (bool) ($options['print_label'] ?? true);
        $payload['generate_manifest'] = (bool) ($options['generate_manifest'] ?? true);

        foreach (self::WRAPPER_OPTION_KEYS as $key) {
            if ($key === 'request_pickup' || $key === 'print_label' || $key === 'generate_manifest') {
                continue;
            }

            if (array_key_exists($key, $options) && $options[$key] !== null && $options[$key] !== '') {
                $payload[$key] = $options[$key];
            }
        }

        return $payload;
    }

    private function resultFromForward(ShiprocketResponse $forward, float $walletBalance): FulfillmentResult
    {
        $payload = $forward->json('payload', []);
        $payload = is_array($payload) ? $payload : [];

        return new FulfillmentResult(
            orderId: $payload['order_id'] ?? null,
            shipmentId: $payload['shipment_id'] ?? null,
            awbCode: $this->stringOrNull($payload['awb_code'] ?? null),
            courierCompanyId: $payload['courier_company_id'] ?? null,
            courierName: $this->stringOrNull($payload['courier_name'] ?? null),
            labelUrl: $this->stringOrNull($payload['label_url'] ?? null),
            manifestUrl: $this->stringOrNull($payload['manifest_url'] ?? null),
            pickupScheduledDate: $payload['pickup_scheduled_date'] ?? null,
            pickupTokenNumber: $payload['pickup_token_number'] ?? null,
            walletBalance: $walletBalance,
            steps: [
                'order_created' => (int) ($payload['order_created'] ?? 0) === 1,
                'awb_generated' => (int) ($payload['awb_generated'] ?? 0) === 1,
                'label_generated' => (int) ($payload['label_generated'] ?? 0) === 1,
                'manifest_generated' => (int) ($payload['manifest_generated'] ?? 0) === 1,
                'pickup_generated' => (int) ($payload['pickup_generated'] ?? 0) === 1,
                'invoice_generated' => false,
            ],
        );
    }

    private function attachInvoice(FulfillmentResult $result): FulfillmentResult
    {
        $orderId = $result->orderId();

        if (! is_int($orderId) && ! (is_string($orderId) && is_numeric($orderId))) {
            throw new ShipmentPipelineException(
                'Cannot generate invoice without a Shiprocket order id.',
                'invoice',
                $result,
            );
        }

        try {
            $invoice = $this->documents->generateInvoice((int) $orderId);
        } catch (RequestFailedException $exception) {
            throw new ShipmentPipelineException(
                $exception->getMessage(),
                'invoice',
                $result,
                $exception,
            );
        } catch (Throwable $exception) {
            throw new ShipmentPipelineException(
                $exception->getMessage(),
                'invoice',
                $result,
                $exception,
            );
        }

        $invoiceUrl = $this->stringOrNull($invoice->json('invoice_url'));

        if ($invoiceUrl === null || $invoice->json('is_invoice_created') === false) {
            throw new ShipmentPipelineException(
                $invoice->message ?? 'Shiprocket invoice was not created.',
                'invoice',
                $result,
            );
        }

        return $result->with([
            'invoiceUrl' => $invoiceUrl,
            'steps' => ['invoice_generated' => true],
        ]);
    }

    private function forwardErrorMessage(ShiprocketResponse $forward): string
    {
        $payloadMessage = $forward->json('payload.error_message');

        if (is_string($payloadMessage) && $payloadMessage !== '') {
            return $payloadMessage;
        }

        return $forward->message ?? 'Shiprocket forward shipment failed.';
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function deliveryPostcode(array $order): int|string|null
    {
        $shippingIsBilling = $order['shipping_is_billing'] ?? true;

        if ($shippingIsBilling === true || $shippingIsBilling === 1 || $shippingIsBilling === '1') {
            $pincode = $order['billing_pincode'] ?? null;
        } else {
            $pincode = $order['shipping_pincode'] ?? $order['billing_pincode'] ?? null;
        }

        if ($pincode === null || $pincode === '') {
            return null;
        }

        if (is_int($pincode) || is_string($pincode)) {
            return $pincode;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function isCod(array $order): bool
    {
        $method = $order['payment_method'] ?? null;

        if (! is_string($method)) {
            return false;
        }

        return strcasecmp($method, 'COD') === 0;
    }

    private function toFloat(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
