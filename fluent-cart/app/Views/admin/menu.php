
<div class="fc-menu">
    <ul class="fc-menu-list">
        <?php foreach ($menuItems as $itemSlug => $menuItem): ?>
            <li class="fc-menu-list fc-menu-list_<?php echo esc_attr($itemSlug); ?>">
                <a type="button" aria-label="<?php echo esc_attr($menuItem['label']) ?>"
                   href="<?php echo esc_url($menuItem['link']); ?>">
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="10" viewBox="0 0 16 10" fill="none">
                            <path d="M2.1665 5L14.6665 4.9998" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M5.49992 0.833008L2.04036 4.29257C1.70703 4.6259 1.54036 4.79257 1.54036 4.99967C1.54036 5.20678 1.70703 5.37345 2.04036 5.70678L5.49992 9.16634" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </span>
                    <?php echo esc_attr($menuItem['label']); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="fc-logo-wrap">
        <a
                aria-label="<?php __('WebmakerrPro Logo', 'fluent-cart'); ?>"
                title="<?php __('WebmakerrPro Logo', 'fluent-cart'); ?>"
                href="<?php echo esc_url(admin_url('admin.php?page=fluent-cart#/')); ?>">
            <img src="<?php echo $logo; ?>"
                 alt="<?php __('WebmakerrPro Logo', 'fluent-cart'); ?>"/>
        </a>
    </div>
</div>

<style>
    .fc-menu {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-family: Inter, sans-serif;
        padding: 10px 20px;
    }
    .fc-logo-wrap a {
        width: 140px;
        display: block;
    }
    .fc-logo-wrap a img{
        width: 100%;
    }
    .fc-menu-list{
        margin: 0;
        padding: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .fc-menu-list a span{
        margin-right: 10px;
    }
    .fc-menu-list a {
        margin-bottom: 0px;
        margin-right: 4px;
        box-sizing: border-box;
        display: flex;
        cursor: pointer;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border-width: 1px;
        border-style: solid;
        border-color: transparent;
        padding-top: 10px;
        padding-bottom: 10px;
        padding-left: 12px;
        padding-right: 12px;
        text-align: center;
        font-size: 16px;
        font-weight: 500;
        line-height: 1;
        letter-spacing: 0.025em;
        transition-property: none;
        color: #253241;
        text-decoration: none;
    }
    .fc-menu-list a:hover{
        background: #eeecec;
    }
    .button-wrap {
        text-align: center;
        margin-top: 100px;
        font-family: Inter, sans-serif;
    }
    .button-wrap .button{
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 500;
        color: #fff;
        text-decoration: none;
        background: #253241;
    }
</style>
