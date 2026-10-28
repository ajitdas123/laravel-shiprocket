<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Services;

use Madgeek\LaravelShiprocket\Support\Delivery;
use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;

final class DocumentService
{
    public function __construct(private readonly Delivery $delivery) {}

    /**
     * @param  int|list<int>  $shipmentIds
     */
    public function generateLabel(int|array $shipmentIds): ShiprocketResponse
    {
        return $this->delivery->send('POST', '/v1/external/courier/generate/label', [
            'shipment_id' => (array) $shipmentIds,
        ]);
    }

    /**
     * @param  int|list<int>  $shipmentIds
     */
    public function generateManifest(int|array $shipmentIds): ShiprocketResponse
    {
        return $this->delivery->send('POST', '/v1/external/manifests/generate', [
            'shipment_id' => (array) $shipmentIds,
        ]);
    }

    /**
     * @param  int|list<int>  $orderIds
     */
    public function printManifest(int|array $orderIds): ShiprocketResponse
    {
        return $this->delivery->send('POST', '/v1/external/manifests/print', [
            'order_ids' => (array) $orderIds,
        ]);
    }

    /**
     * @param  int|list<int>  $orderIds
     */
    public function generateInvoice(int|array $orderIds): ShiprocketResponse
    {
        return $this->delivery->send('POST', '/v1/external/orders/print/invoice', [
            'ids' => (array) $orderIds,
        ]);
    }

    /**
     * Shiprocket accepts up to 200 shipment ids per request.
     *
     * @param  int|list<int>  $shipmentIds
     */
    public function generateLabelAndInvoice(int|array $shipmentIds): ShiprocketResponse
    {
        return $this->delivery->send('POST', '/v1/external/courier/generate/label-invoice', [
            'shipment_ids' => (array) $shipmentIds,
        ]);
    }
}
