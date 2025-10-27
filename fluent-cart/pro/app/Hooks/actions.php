<?php

/**
 * All registered action's handlers should be in app\Hooks\Handlers,
 * addAction is similar to add_action and addCustomAction is just a
 * wrapper over add_action which will add a prefix to the hook name
 * using the plugin slug to make it unique in all wordpress plugins,
 * ex: $app->addCustomAction('foo', ['FooHandler', 'handleFoo']) is
 * equivalent to add_action('slug-foo', ['FooHandler', 'handleFoo']).
 */

/**
 * @var $app FluentCartPro\App\Core\Application
 */


use FluentCartPro\App\Models\OrderItem;
use FluentCartPro\App\Models\ProductDetail;
use FluentCartPro\App\Models\ProductVariation;
use FluentCartPro\Framework\Support\Arr;
use FluentCartPro\Framework\Support\Collection;

$app->addAction('admin_menu', 'AdminMenuHandler@add');

(new \FluentCartPro\App\Hooks\Handlers\UpgradeHandler())->register();
(new \FluentCartPro\App\Hooks\Handlers\SubscriptionRenewalHandler())->register();
