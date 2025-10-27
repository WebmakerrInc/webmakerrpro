<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
/**
 * @var $menuItems
 * @var $profileData
 */
?>

<div class="fc-customer-dashboard-navs-wrap">
    <?php if($profileData): ?>
    <div class="fc-customer-dashboard-customer-info">
        <img src="<?php echo esc_url($profileData['photo']); ?>" alt="<?php echo esc_attr($profileData['full_name']); ?>" />
        <div class="fc-customer-dashboard-customer-info-content">
            <h3><?php echo esc_attr($profileData['full_name']); ?></h3>
            <p><?php echo esc_attr($profileData['email']); ?></p>
        </div>
        <div id="fc-customer-logout-button">
            <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" title="Logout" class="fc-customer-logout-btn">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M5 22C4.44772 22 4 21.5523 4 21V3C4 2.44772 4.44772 2 5 2H19C19.5523 2 20 2.44772 20 3V6H18V4H6V20H18V18H20V21C20 21.5523 19.5523 22 19 22H5ZM18 16V13H11V11H18V8L23 12L18 16Z"></path></svg>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <div id="menu-container">
        <div id="fc-customer-menu-toggle">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3 4H21V6H3V4ZM3 11H21V13H3V11ZM3 18H21V20H3V18Z"></path></svg>
        </div>
        <div id="fc-customer-menu-holder">
            <div class="fc-customer-navs-wrap">
                <ul class="fc-customer-navs">
                    <?php foreach ($menuItems as $itemSlug => $menuItem): ?>
                        <li class="fc-customer-nav-item fc-customer-nav-item-<?php echo esc_attr($itemSlug); ?>">
                            <a
                                class="fc-customer-nav-link <?php echo esc_attr(\FluentCart\Framework\Support\Arr::get($menuItem, 'css_class')); ?>"
                                aria-label="<?php echo esc_attr($menuItem['label']) ?>"
                                href="<?php echo esc_url($menuItem['link']); ?>"
                            >
                                <?php echo esc_html($menuItem['label']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

