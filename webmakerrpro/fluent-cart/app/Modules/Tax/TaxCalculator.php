<?php

namespace FluentCart\App\Modules\Tax;

use FluentCart\App\App;
use FluentCart\App\Models\TaxRate;
use FluentCart\App\Models\TaxClass;
use FluentCart\Framework\Support\Arr;
use FluentCart\App\Services\Tax\TaxManager;
use FluentCart\Framework\Support\Collection;
use FluentCart\App\Services\Localization\LocalizationManager;

class TaxCalculator
{

    protected $productIds = [];

    protected $taxMaps = [];

    protected $country = '';
    protected $state = '';
    protected $postCode = '';

    protected $lineItems = [];

    protected $formattedLineItems = [];

    protected $products = [];

    protected $inclusive = true;

    protected $cart;

    protected $manualDiscounts = 0;

    protected $taxSettings = [];

    public function __construct($lineItems, $config = [])
    {
        $this->inclusive = Arr::get($config, 'inclusive', true);
        $this->manualDiscounts = Arr::get($config, 'manual_discounts', 0);
        $this->country = Arr::get($config, 'country');
        $this->state = Arr::get($config, 'state');
        $this->postCode = Arr::get($config, 'postcode');

        $taxSettings = (new TaxModule())->getSettings();

        $this->taxSettings = $taxSettings;

        if (Arr::get($taxSettings, 'enable_tax') !== 'yes') {
            return;
        }

        $this->inclusive = Arr::get($taxSettings, 'tax_inclusion') === 'included';

        if ($lineItems) {
            $this->lineItems = $lineItems;
            $this->productIds = array_values(array_unique(array_column($lineItems, 'post_id')));

            if ($this->productIds) {
                $this->products = \FluentCart\App\Models\Product::query()->whereIn('id', $this->productIds)
                    ->with(['detail'])
                    ->get()
                    ->keyBy('ID');
            }
            $this->setupMaps();
        }

    }

    public function getTaxBahaviorValue()
    {
        if ($this->inclusive) {
            return 2;
        }

        return 1;
    }

    public function setupMaps()
    {
        foreach ($this->productIds as $productId) {
            // we have to check if the product has specific tax rate assigned!
            $this->taxMaps[$productId] = $this->getRatesByProductId($productId);
        }

        $formattedLineItems = [];
        foreach ($this->lineItems as $lineItem) {
            $productId = Arr::get($lineItem, 'post_id');
            $rates = Arr::get($this->taxMaps, $productId, []);
            $taxLines = [];
            $signupFeeTaxLines = [];
            $lineTaxTotal = 0;
            $signupFeeTax = 0;
            $recurringTax = 0;
            $signupFee = 0;
            $recurringAmount = 0;
            $isSubscription = Arr::get($lineItem, 'other_info.payment_type') === 'subscription';

            $taxableAmount = Arr::get($lineItem, 'subtotal', 0) - Arr::get($lineItem, 'discount_total', 0);


            if ($isSubscription) {
                $signupFee = Arr::get($lineItem, 'other_info.signup_fee', 0);
                // as long discount is not recurring discount
                $recurringAmount = Arr::get($lineItem, 'subtotal', 0);

                $havePredefinedTrialDays = Arr::get($lineItem, 'other_info.trial_days', 0) > 0;
                if ($havePredefinedTrialDays) {
                    $taxableAmount = 0;
                }
            }


            if ($rates) {
                foreach ($rates as $rate) {
                    $rateSignupFeeTax = 0;
                    if ($this->inclusive) {
                        $taxAmount = ($taxableAmount * $rate->rate) / (100 + $rate->rate);
                        if ($recurringAmount) {
                            $recurringTax = ($recurringAmount * $rate->rate) / (100 + $rate->rate);
                        }
                        if ($signupFee) {
                            $rateSignupFeeTax += ($signupFee * $rate->rate) / (100 + $rate->rate);
                        }
                    } else {
                        $taxAmount = ($taxableAmount * $rate->rate) / 100;
                        if ($recurringAmount) {
                           $recurringTax = ($recurringAmount * $rate->rate) / 100;
                        }
                        if ($signupFee) {
                            $rateSignupFeeTax += ($signupFee * $rate->rate) / 100;
                        }
                    }

                    $taxLines[] = [
                        'rate_id'    => $rate->id,
                        'label'      => $rate->name,
                        'tax_amount' => ceil($taxAmount),
                        'recurring_tax' => ceil($recurringTax),
                        'rate'       => $rate->rate,
                        'for_shipping' => $rate->for_shipping,
                        'country' => $rate->country,
                    ];

                    if ($rateSignupFeeTax) {
                        $signupFeeTaxLines[] = [
                            'rate_id'    => $rate->id,
                            'label'      => $rate->name,
                            'tax_amount' => ceil($rateSignupFeeTax),
                            'rate'       => $rate->rate,
                            'for_shipping' => $rate->for_shipping,
                            'country' => $rate->country,
                        ];

                        $signupFeeTax += $rateSignupFeeTax;
                    }


                    $lineTaxTotal += $taxAmount;
                }
            }

            if (empty($lineItem['line_meta'])) {
                $lineItem['line_meta'] = [];
            }

            $lineItem['line_meta']['tax_config'] = [
                'inclusive' => $this->inclusive,
                'rates'     => $taxLines,
            ];

            if ($isSubscription) {
                Arr::set($lineItem, 'other_info.recurring_tax', ceil($recurringTax));
                if ($signupFeeTax) {
                    Arr::set($lineItem, 'other_info.signup_fee_tax', ceil($signupFeeTax));
                    $lineItem['signup_fee_tax_config'] = [
                        'inclusive' => $this->inclusive,
                        'rates'     => $signupFeeTaxLines,
                    ];
                }

            } else {
                unset($lineItem['other_info']['signup_fee_tax']);
                unset($lineItem['signup_fee_tax_lines']);
            }

            $lineItem['tax_amount'] = ceil($lineTaxTotal);

            $formattedLineItems[] = $lineItem;
        }

        $this->formattedLineItems = $formattedLineItems;
    }

    public function getTaxedLines()
    {
        return $this->formattedLineItems;
    }

    public function getTaxLinesByRates($lineItems = [])
    {
        if (!$lineItems) {
            $lineItems = $this->formattedLineItems;
        }

        $taxLines = [];
        foreach ($lineItems as $lineItem) {
            $lineMeta = Arr::get($lineItem, 'line_meta', []);
            $taxConfig = Arr::get($lineMeta, 'tax_config', []);
            $rates = Arr::get($taxConfig, 'rates', []);
            if ($rates) {
                foreach ($rates as $rate) {
                    $rateId = Arr::get($rate, 'rate_id');
                    if (!isset($taxLines[$rateId])) {
                        $taxLines[$rateId] = [
                            'rate_id'    => $rateId,
                            'label'      => Arr::get($rate, 'label'),
                            'tax_amount' => 0,
                        ];
                    }
                    $taxLines[$rateId]['tax_amount'] += Arr::get($rate, 'tax_amount', 0);
                }
            }
        }

        return array_values($taxLines);
    }

    public function getTotalTax()
    {
        $taxTotal = 0;
        foreach ($this->formattedLineItems as $lineItem) {
            $mainTaxAmount = Arr::get($lineItem, 'tax_amount', 0);
            $taxTotal += $mainTaxAmount;
            $isSubscription = Arr::get($lineItem, 'other_info.payment_type') === 'subscription';
            if ($isSubscription) {
                $signupFeeTax = Arr::get($lineItem, 'other_info.signup_fee_tax', 0);
                if ($signupFeeTax) {
                    $taxTotal += $signupFeeTax;
                }
            }
        }

        return ceil($taxTotal);
    }

    public function getRecurringTax()
    {
        $recurringTaxTotal = 0;
        foreach ($this->formattedLineItems as $lineItem) {
            $recurringTax = Arr::get($lineItem, 'other_info.recurring_tax', 0);
            $recurringTaxTotal += $recurringTax;
        }

        return ceil($recurringTaxTotal);
    }

    public function getTaxCountry()
    {
        return $this->country;
    }

    public function getShippingTax()
    {
        $shippingTaxTotal = 0;
        foreach($this->formattedLineItems as $item) {
            $taxMeta = Arr::get($item, 'line_meta.tax_config.rates.0', []);
            $rate = Arr::get($taxMeta, 'rate', 0);
            $forShipping = Arr::get($taxMeta, 'for_shipping', null);

            $totalShippingCharge = Arr::get($item, 'shipping_charge', 0) + Arr::get($item, 'itemwise_shipping_charge', 0);
            $shippingTax = $totalShippingCharge * ($rate / 100);

            if ($forShipping !== null) {
                $shippingTax = $totalShippingCharge * ($forShipping / 100);
            }

            $shippingTaxTotal += ceil($shippingTax);
        }

        return ceil($shippingTaxTotal);
    }

    protected function getRatesByProductId($productId)
    {
        $taxClass = $this->getTaxClassByProductId($productId);

        if (!$taxClass) {
            return [];
        }

        // check EU country
        $euCountryCodes = LocalizationManager::getInstance()->taxContinents('EU');
        $euCountryCodes = Arr::get($euCountryCodes, 'countries');
        $isEuCountry = in_array($this->country, $euCountryCodes);

        $taxClassSlug = $taxClass->slug;

        if ($isEuCountry) {
            $rates = $this->getEuTaxRates($taxClass->id, $taxClassSlug);
        } else {
            $rates = TaxRate::query()->where('class_id', $taxClass->id)
            ->orderBy('priority', 'asc')
            ->where('country', $this->country)
            ->get();
        }

        if ($rates->isEmpty()) {
            return [];
        }

        $validRates = [];
        foreach ($rates as $rate) {
            if ($rate->state && $rate->state !== $this->state) {
                continue;
            }

            if ($rate->postcode) {
                $hasRange = strpos($rate->postcode, '...') !== false;
                $postcodes = array_map('trim', explode(',', $rate->postcode));

                if ($hasRange) {
                    $rangedPostcodes = [];
                    foreach ($postcodes as $postcode) {
                        if (strpos($postcode, '...') !== false) {
                            list($start, $end) = explode('...', $postcode);
                            if($end > $start) {
                                $rangedPostcodes = array_merge($rangedPostcodes, range($start, $end));
                            }
                        } else {
                            $rangedPostcodes[] = $postcode;
                        }
                    }

                    $postcodes = $rangedPostcodes;
                }

                if (!in_array($this->postCode, $postcodes)) {
                    continue;
                }
            }

            $validRates[] = $rate;
        }

        return $validRates;
    }

    protected function getEuTaxRates($taxClassId, $taxClassSlug)
    {
        $euVatSettings = Arr::get($this->taxSettings, 'eu_vat_settings', []);
        $vatCollectionMethod = Arr::get($euVatSettings, 'method', '');
        $taxManager = TaxManager::getInstance();

        if ($vatCollectionMethod === 'oss' || $vatCollectionMethod === 'home') {
            if ($vatCollectionMethod === 'home') { 
                $this->country = Arr::get($euVatSettings, 'home_country', '');
            }

            $rates = TaxRate::query()->where('class_id', $taxClassId)
                ->orderBy('priority', 'asc')
                ->where('country', $this->country)
                ->get();
                
            if ($rates->isEmpty()) {
                $rates = $taxManager->getEuTaxRatesFromPhp($this->country, $taxClassSlug);
                return Collection::make($rates)->map(function ($rate) {
                    $rate['country'] = $this->country;
                    return new TaxRate($rate);
                });
            }
            return $rates;
        } else if ($vatCollectionMethod === 'specific') {
            return TaxRate::query()->where('class_id', $taxClassId)
                ->orderBy('priority', 'asc')
                ->where('country', $this->country)
                ->get();
        } else {
            return Collection::make([]);
        }
    }

    protected function getTaxClassByProductId($productId)
    {
        $product = Arr::get($this->products, $productId);
        if (!$product) {
            return null;
        }

        $taxClasId = Arr::get($product->detail->other_info, 'tax_class', '');

        if ($taxClasId) {
            $class = TaxClass::query()->find($taxClasId);
            if ($class) {
                return $class;
            }
        }

        // let's get the tax class from the product category
        return $this->getTaxClassByTermIds($this->getTermsByProductId($productId));
    }

    protected function getTaxClassByTermIds($termIds)
    {
        if (!$termIds) {
            return null;
        }

        $taxClasses = null;

        $formattedTaxClasses = [];

        if ($taxClasses === null) {
            $taxClasses = TaxClass::query()->whereNotNull('meta')->get();

            foreach ($taxClasses as $taxClass) {
                $categories = Arr::get($taxClass->meta, 'categories', []);
                if (!$categories || !array_intersect($termIds, $categories)) {
                    continue;
                }
                $priority = Arr::get($taxClass->meta, 'priority', 0);
                $formattedTaxClasses[$priority] = $taxClass;
            }
        }

        if (!$formattedTaxClasses) {
            return null;
        }

        // get the highest priority tax class and return that
        krsort($formattedTaxClasses);
        $taxClasses = array_values($formattedTaxClasses);
        return $taxClasses[0];
    }

    protected function getTermsByProductId($productId)
    {
        static $formattedTerms = null;

        if ($formattedTerms === null) {
            $terms = App::make('db')->table('term_relationships')
                ->whereIn('object_id', $this->productIds)
                ->get();

            $formattedTerms = [];

            foreach ($terms as $term) {
                if (!isset($formattedTerms[$term->object_id])) {
                    $formattedTerms[$term->object_id] = [];
                }
                $formattedTerms[$term->object_id][] = $term->term_taxonomy_id;
            }
        }

        return Arr::get($formattedTerms, $productId, []);
    }

}
