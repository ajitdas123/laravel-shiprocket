<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Support;

final readonly class FulfillmentResult
{
    /**
     * @param  array{
     *     order_created?: bool,
     *     awb_generated?: bool,
     *     label_generated?: bool,
     *     manifest_generated?: bool,
     *     pickup_generated?: bool,
     *     invoice_generated?: bool
     * }  $steps
     */
    public function __construct(
        public mixed $orderId = null,
        public mixed $shipmentId = null,
        public ?string $awbCode = null,
        public mixed $courierCompanyId = null,
        public ?string $courierName = null,
        public ?string $labelUrl = null,
        public ?string $manifestUrl = null,
        public ?string $invoiceUrl = null,
        public mixed $pickupScheduledDate = null,
        public mixed $pickupTokenNumber = null,
        public float|int|string|null $walletBalance = null,
        public array $steps = [],
    ) {}

    public function orderId(): mixed
    {
        return $this->orderId;
    }

    public function shipmentId(): mixed
    {
        return $this->shipmentId;
    }

    public function awbCode(): ?string
    {
        return $this->awbCode;
    }

    public function courierCompanyId(): mixed
    {
        return $this->courierCompanyId;
    }

    public function courierName(): ?string
    {
        return $this->courierName;
    }

    public function labelUrl(): ?string
    {
        return $this->labelUrl;
    }

    public function manifestUrl(): ?string
    {
        return $this->manifestUrl;
    }

    public function invoiceUrl(): ?string
    {
        return $this->invoiceUrl;
    }

    public function pickupScheduledDate(): mixed
    {
        return $this->pickupScheduledDate;
    }

    public function walletBalance(): float|int|string|null
    {
        return $this->walletBalance;
    }

    /**
     * @return array{
     *     order_id: mixed,
     *     shipment_id: mixed,
     *     awb_code: ?string,
     *     courier_company_id: mixed,
     *     courier_name: ?string,
     *     label_url: ?string,
     *     manifest_url: ?string,
     *     invoice_url: ?string,
     *     pickup_scheduled_date: mixed,
     *     pickup_token_number: mixed,
     *     wallet_balance: float|int|string|null,
     *     order_created: bool,
     *     awb_generated: bool,
     *     label_generated: bool,
     *     manifest_generated: bool,
     *     pickup_generated: bool,
     *     invoice_generated: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'shipment_id' => $this->shipmentId,
            'awb_code' => $this->awbCode,
            'courier_company_id' => $this->courierCompanyId,
            'courier_name' => $this->courierName,
            'label_url' => $this->labelUrl,
            'manifest_url' => $this->manifestUrl,
            'invoice_url' => $this->invoiceUrl,
            'pickup_scheduled_date' => $this->pickupScheduledDate,
            'pickup_token_number' => $this->pickupTokenNumber,
            'wallet_balance' => $this->walletBalance,
            'order_created' => (bool) ($this->steps['order_created'] ?? false),
            'awb_generated' => (bool) ($this->steps['awb_generated'] ?? false),
            'label_generated' => (bool) ($this->steps['label_generated'] ?? false),
            'manifest_generated' => (bool) ($this->steps['manifest_generated'] ?? false),
            'pickup_generated' => (bool) ($this->steps['pickup_generated'] ?? false),
            'invoice_generated' => (bool) ($this->steps['invoice_generated'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        return new self(
            orderId: array_key_exists('orderId', $overrides) ? $overrides['orderId'] : $this->orderId,
            shipmentId: array_key_exists('shipmentId', $overrides) ? $overrides['shipmentId'] : $this->shipmentId,
            awbCode: array_key_exists('awbCode', $overrides) ? $overrides['awbCode'] : $this->awbCode,
            courierCompanyId: array_key_exists('courierCompanyId', $overrides) ? $overrides['courierCompanyId'] : $this->courierCompanyId,
            courierName: array_key_exists('courierName', $overrides) ? $overrides['courierName'] : $this->courierName,
            labelUrl: array_key_exists('labelUrl', $overrides) ? $overrides['labelUrl'] : $this->labelUrl,
            manifestUrl: array_key_exists('manifestUrl', $overrides) ? $overrides['manifestUrl'] : $this->manifestUrl,
            invoiceUrl: array_key_exists('invoiceUrl', $overrides) ? $overrides['invoiceUrl'] : $this->invoiceUrl,
            pickupScheduledDate: array_key_exists('pickupScheduledDate', $overrides) ? $overrides['pickupScheduledDate'] : $this->pickupScheduledDate,
            pickupTokenNumber: array_key_exists('pickupTokenNumber', $overrides) ? $overrides['pickupTokenNumber'] : $this->pickupTokenNumber,
            walletBalance: array_key_exists('walletBalance', $overrides) ? $overrides['walletBalance'] : $this->walletBalance,
            steps: array_key_exists('steps', $overrides) && is_array($overrides['steps'])
                ? [...$this->steps, ...$overrides['steps']]
                : $this->steps,
        );
    }
}
