<?php

namespace FluentCart\App\Modules\Templating\Bricks;

use Bricks\Frontend;
use FluentCart\Framework\Support\Arr;

class BricksHelper
{

    static public $forcedPost = null;

    public static function getFormCurrentPost()
    {
        return self::$forcedPost;
    }

    public static function setFormCurrentPost($post)
    {
        self::$forcedPost = $post;
    }

    public static function getCategoriesOptions()
    {
        $categories = get_terms(array(
            'taxonomy'   => 'product-categories',
            'hide_empty' => false,
            'orderby'    => 'name'
        ));

        $options = [];
        if (!is_wp_error($categories) && !empty($categories)) {
            foreach ($categories as $category) {
                $options[$category->term_id] = $category->name;
            }
        }

        return $options;
    }

    public static function renderCollectionCard($settings, $post, $post_index = 1, $uid = '')
    {
        $content = Frontend::get_content_wrapper($settings, Arr::get($settings, 'fields', []), $post);

        if ($post_index === 1) {
            echo "<div data-fluent-client-id='" . $uid . "' data-template-provider='bricks' data-fc-product-card class='fc-product-card repeater-item'>";
        } else {
            echo "<div data-fc-product-card class='fc-product-card repeater-item'>";
        }

        $linkedProduct = isset($settings['linkProduct']) ? $settings['linkProduct'] : false;

        if ($linkedProduct && strpos($content, '<a ') === false) {
            echo '<a href="' . get_the_permalink($post) . '">';
        }

        echo $content;

        if ($linkedProduct && strpos($content, '<a ') === false) {
            echo '</a>';
        }

        echo '</div>';
    }
}
