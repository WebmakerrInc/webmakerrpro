<?php if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
/**
 * @var $colors string
 * @var $active_tab string
 *
 */
?>
<div class="fct_customer_profile_wrap">
    <div class="fc-customer-root-container"
         style="position: relative; min-height: 700px; <?php echo esc_attr($colors); ?>">
        <div id="fc-customer-loader"
             style="position: absolute; left: 0;top: 0;width: 100%;height: 100%;z-index: 4;background: #ffffff;display: flex;gap: 16px;">
            <div class="fc-customer-loader-left" style="max-width: 272px;width: 100%;flex: none;">
                <div class="el-skeleton is-animated">
                    <div class="el-skeleton__item el-skeleton__p is-first"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph" style="width: 70%"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph" style="width: 50%"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph" style="width: 80%"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph is-last"></div>
                </div>
            </div>
            <div class="fc-customer-loader-right" style="flex: 1">
                <div class="el-skeleton is-animated">
                    <div class="el-skeleton__item el-skeleton__p is-first"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph" style="width: 50%"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph" style="width: 80%"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph" style="width: 70%"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph" style="width: 50%"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph" style="width: 80%"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph" style="width: 70%"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph"></div>
                    <div class="el-skeleton__item el-skeleton__p el-skeleton__paragraph is-last"></div>
                </div>
            </div>
        </div>

        <div class="fc-customer-dashboard-app-container fluent-cart-customer-profile-app">
            <?php do_action('fluent_cart/customer_menu'); ?>
            <div class="fc-customer-dashboard-main-content">
                <?php do_action('fluent_cart/customer_app'); ?>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function () {
        var navLink = document.querySelectorAll('.fc-customer-nav-link');
        var menuToggle = document.getElementById('fc-customer-menu-toggle');
        var menuHolder = document.getElementById('fc-customer-menu-holder');
        var loader = document.getElementById('fc-customer-loader');
        if (loader) {
            loader.remove();
        }

        // see if we have active tab
        var activeTab = '<?php echo esc_attr($active_tab); ?>';
        if (activeTab) {
            var menuItem = document.querySelector('.fc-customer-nav-item-' + activeTab);
            if (menuItem) {
                menuItem.classList.add('active_customer_menu');
            }
        }

        // Toggle menu on button click
        menuToggle.addEventListener('click', function (e) {
            e.stopPropagation(); // prevent click from bubbling to document
            menuHolder.classList.toggle('is-active');
        });

        // nav link click
        if (navLink) {
            navLink.forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.stopPropagation();
                    menuHolder.classList.remove('is-active');
                });
            });
        }

        // Close menu on outside click
        document.addEventListener('click', function (event) {
            if (
                menuHolder.classList.contains('is-active') &&
                !menuHolder.contains(event.target) &&
                event.target !== menuToggle
            ) {
                menuHolder.classList.remove('is-active');
            }
        });
    });
</script>
