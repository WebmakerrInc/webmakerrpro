<?php

namespace FluentCart\App\Services\CustomPayment;

use ReflectionClass;
use ReflectionProperty;

class PaymentItem
{
    protected string $itemName;
    protected int $lineTotal;
    protected int $price = 0;
    protected int $quantity = 1;
    protected string $paymentType = 'onetime';
    protected array $subscriptionInfo = [];

    public function setItemName(string $productName): self
    {
        if (empty($productName)) {
            throw new \InvalidArgumentException(__("Item name cannot be empty.", 'fluent-cart'));
        }
        $this->itemName = $productName;
        return $this;
    }

    public function setQuantity(int $quantity): self
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException(__("Quantity must be greater than 0.", 'fluent-cart'));
        }
        $this->quantity = $quantity;
        $this->updateLineTotal();
        return $this;
    }

    public function setPrice(int $price): self
    {
        if ($price <= 0) {
            throw new \InvalidArgumentException(__("Price must be greater than 0 and use in cent!.", 'fluent-cart'));
        }
        $this->price = $price;
        $this->updateLineTotal();
        return $this;
    }

    public function setPaymentType(string $paymentType): self
    {
        $validPaymentTypes = ['onetime', 'subscription'];
        if (!in_array($paymentType, $validPaymentTypes, true)) {
            throw new \InvalidArgumentException(__("Invalid payment type provided.", 'fluent-cart'));
        }
        $this->paymentType = $paymentType;
        return $this;
    }

    public function setSubscriptionInfo(array $subscriptionInfo): self
    {
        if ($this->paymentType === 'onetime') {
            throw new \InvalidArgumentException(__("Please set payment type subscription first!", 'fluent-cart'));
        }

        $requiredKeys = ['signup_fee', 'times', 'repeat_interval'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $subscriptionInfo)) {
                throw new \InvalidArgumentException(__("Missing required subscription info key: $key.", 'fluent-cart'));
            }
        }

        if (!is_numeric($subscriptionInfo['signup_fee']) || $subscriptionInfo['signup_fee'] < 0) {
            throw new \InvalidArgumentException(__("Invalid signup fee. It must be a non-negative number.", 'fluent-cart'));
        }

        if (!is_int($subscriptionInfo['times']) || $subscriptionInfo['times'] < 0) {
            throw new \InvalidArgumentException(__("Invalid times. It must be a non-negative integer.", 'fluent-cart'));
        }

        if (!is_string($subscriptionInfo['repeat_interval']) || empty($subscriptionInfo['repeat_interval'])) {
            throw new \InvalidArgumentException(__("Invalid repeat interval. It must be a non-empty string ie. daily, monthly, yearly..)", 'fluent-cart'));
        }

        $this->subscriptionInfo = $subscriptionInfo;
        return $this;
    }

    public function getItem(): array
    {
        if (empty($this->itemName)) {
            throw new \RuntimeException(__("Item name is not set.", 'fluent-cart'));
        }

        if (empty($this->price)) {
            throw new \RuntimeException(__("price is not set.", 'fluent-cart'));
        }

        if ($this->paymentType === 'subscription' && empty($this->subscriptionInfo)) {
            throw new \InvalidArgumentException(__("Please set subscription required infos ['signup_fee', 'times', 'repeat_interval'].", 'fluent-cart') );
        }

        $otherInfo = array_merge(
            [
                "payment_type" => $this->paymentType,
            ],
            $this->subscriptionInfo
        );

        $this->checkoutItem = [
            "id" => null,
            "quantity" => $this->quantity,
            "title" => $this->itemName,
            "price" => $this->price,
            "line_total" => $this->lineTotal,
            "other_info" => $otherInfo
        ];

        return $this->checkoutItem;
    }

    private function updateLineTotal(): void
    {
        $this->lineTotal = (int)($this->quantity * $this->price);
    }

    public function toArray() : array
    {
        return [
            'item_name' => $this->itemName,
            'line_total' => $this->lineTotal,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'payment_type' => $this->paymentType,
            'subscription_info' => $this->subscriptionInfo,
        ];
    }
}
