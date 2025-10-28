<?php

namespace FluentCart\App\Services\Theme;

use FluentCart\App\App;

class AdminTheme
{
    public static function applyTheme()
    {
        $currentPage = App::request()->get('page');
        $allowedSlugs = [App::slug(), 'fluent-cart'];

        if (in_array($currentPage, $allowedSlugs, true)) {
            add_action('admin_head', function () {
                ?>
                <script>
                    (function() {
                        const theme = localStorage.getItem('fcart_admin_theme');
        
                        if (theme) {
                            document.documentElement.classList.add(theme.split(':').pop());
                        }
                    })();
                </script>
                <?php
            });
        }
    }
}