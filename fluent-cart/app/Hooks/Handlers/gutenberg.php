<?php
/**
 * The block editor page, customized to load Gutenberg with plugin blocks in an iframe.
 *
 * @since 5.0.0
 *
 * @package WordPress
 * @subpackage Administration
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

wp_add_inline_script(
    'wp-blocks',
    'wp.blocks.unstable__bootstrapServerSideBlockDefinitions(' . wp_json_encode( get_block_editor_server_block_settings() ) . ');'
);



$post = get_post(\FluentCart\App\App::request()->get('id'));
$custom_class = \FluentCart\App\App::request()->get('custom_class');
$custom_class = esc_attr($custom_class);

//\FluentCart\App\Vite::enqueueScript('gutenberg_admin_app_start2', 'public/gutenberg/gutenberg2.js', []);
//\FluentCart\App\Vite::enqueueScript('gutenberg_admin_app_start', 'public/gutenberg/gutenberg.js', []);

\FluentCart\App\Vite::enqueueStyle(
        'fluent_cart_gutenberg',
        'public/gutenberg/gutenberg.scss',
);

wp_add_inline_script(
        'gutenberg_admin_app_start',
        'wp.blocks.unstable__bootstrapServerSideBlockDefinitions(' . wp_json_encode( get_block_editor_server_block_settings() ) . ');'
);
require_once ABSPATH . 'wp-load.php';
if (!did_action('init')) {
    do_action('init');
}

if (!is_user_logged_in()) {
    auth_redirect();
}

global $post_type, $post_type_object, $post, $title, $wp_meta_boxes;


$post = get_post(\FluentCart\App\App::request()->get('id'));

$block_editor_context = new WP_Block_Editor_Context(array('post' => $post));


// Flag that we're loading the block editor.
$current_screen = get_current_screen();
$current_screen->is_block_editor( true );

// Default to is-fullscreen-mode to avoid jumps in the UI.
add_filter(
        'admin_body_class',
        static function ( $classes ) {
            return "$classes is-fullscreen-mode";
        }
);

/*
 * Emoji replacement is disabled for now, until it plays nicely with React.
 */
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );

/*
 * Block editor implements its own Options menu for toggling Document Panels.
 */
add_filter( 'screen_options_show_screen', '__return_false' );


wp_enqueue_script( 'heartbeat' );
wp_enqueue_script( 'wp-edit-post' );

$rest_path = rest_get_route_for_post($post);

$active_theme = get_stylesheet();


// Preload common data.
$preload_paths = array(
        '/wp/v2/types?context=view',
        '/wp/v2/taxonomies?context=view',
        add_query_arg( 'context', 'edit', $rest_path ),
        sprintf( '/wp/v2/types/%s?context=edit', $post_type ),
        '/wp/v2/users/me',
        array( rest_get_route_for_post_type_items( 'attachment' ), 'OPTIONS' ),
        array( rest_get_route_for_post_type_items( 'page' ), 'OPTIONS' ),
        array( rest_get_route_for_post_type_items( 'wp_block' ), 'OPTIONS' ),
        array( rest_get_route_for_post_type_items( 'wp_template' ), 'OPTIONS' ),
        sprintf( '%s/autosaves?context=edit', $rest_path ),
        '/wp/v2/settings',
        array( '/wp/v2/settings', 'OPTIONS' ),
        '/wp/v2/global-styles/themes/' . $active_theme . '?context=view',
        '/wp/v2/global-styles/themes/' . $active_theme . '/variations?context=view',
        '/wp/v2/themes?context=edit&status=active',
        array( '/wp/v2/global-styles/' . WP_Theme_JSON_Resolver::get_user_global_styles_post_id(), 'OPTIONS' ),
        '/wp/v2/global-styles/' . WP_Theme_JSON_Resolver::get_user_global_styles_post_id() . '?context=edit',
);

block_editor_rest_api_preload( $preload_paths, $block_editor_context );
// Ensure block definitions are bootstrapped
wp_add_inline_script(
        'wp-blocks',
        sprintf( 'wp.blocks.setCategories( %s );', wp_json_encode( get_block_categories( $post ) ) ),
        'after'
);




// Preload server-registered block schemas.
wp_add_inline_script(
        'wp-blocks',
        'wp.blocks.unstable__bootstrapServerSideBlockDefinitions(' . wp_json_encode( get_block_editor_server_block_settings() ) . ');'
);

// Preload server-registered block bindings sources.
$registered_sources = get_all_registered_block_bindings_sources();


if ( ! empty( $registered_sources ) ) {
    $filtered_sources = array();
    foreach ( $registered_sources as $source ) {
        $filtered_sources[] = array(
                'name'        => $source->name,
                'label'       => $source->label,
                'usesContext' => $source->uses_context,
        );
    }
    $script = sprintf( 'for ( const source of %s ) { wp.blocks.registerBlockBindingsSource( source ); }', wp_json_encode( $filtered_sources ) );
    wp_add_inline_script(
            'wp-blocks',
            $script
    );
}

$meta_box_url = admin_url('post.php');
$meta_box_url = add_query_arg(
        array(
                'post' => $post->ID,
                'action' => 'edit',
                'meta-box-loader' => true,
                'meta-box-loader-nonce' => wp_create_nonce('meta-box-loader'),
        ),
        $meta_box_url
);
wp_add_inline_script(
        'wp-editor',
        sprintf('var _wpMetaBoxUrl = %s;', wp_json_encode($meta_box_url)),
        'before'
);

// Set Heartbeat interval to 10 seconds, used to refresh post locks.
wp_add_inline_script(
        'heartbeat',
        'jQuery( function() {
        wp.heartbeat.interval( 10 );
    } );',
        'after'
);

/*
 * Get all available templates for the post/page attributes meta-box.
 * The "Default template" array element should only be added if the array is
 * not empty so we do not trigger the template select element without any options
 * besides the default value.
 */
$available_templates = wp_get_theme()->get_page_templates( get_post( $post->ID ) );
$available_templates = ! empty( $available_templates ) ? array_replace(
        array(
            /** This filter is documented in wp-admin/includes/meta-boxes.php */
                '' => apply_filters( 'default_page_template_title', __( 'Default template' ), 'rest-api' ),
        ),
        $available_templates
) : $available_templates;

$user_id = wp_check_post_lock($post->ID);
if ($user_id) {
    $locked = apply_filters('show_post_locked_dialog', true, $post, $user_id);
    $user_details = $locked ? array(
            'avatar' => get_avatar_url($user_id, array('size' => 128)),
            'name' => get_userdata($user_id)->display_name,
    ) : null;
    $lock_details = array('isLocked' => $locked, 'user' => $user_details);
} else {
    $active_post_lock = wp_set_post_lock($post->ID);
    $lock_details = array(
            'isLocked' => false,
            'activePostLock' => $active_post_lock ? esc_attr(implode(':', $active_post_lock)) : null,
    );
}

$body_placeholder = apply_filters('write_your_story', __('Type / to choose a block'), $post);

// Define $editor_settings
$editor_settings = array(
        'availableTemplates' => $available_templates,
        'disablePostFormats' => !current_theme_supports('post-formats'),
        'titlePlaceholder' => apply_filters('enter_title_here', __('Add title'), $post),
        'bodyPlaceholder' => $body_placeholder,
        'autosaveInterval' => AUTOSAVE_INTERVAL,
        'richEditingEnabled' => user_can_richedit(),
        'postLock' => $lock_details,
        'postLockUtils' => array(
                'nonce' => wp_create_nonce('lock-post_' . $post->ID),
                'unlockNonce' => wp_create_nonce('update-post_' . $post->ID),
                'ajaxUrl' => admin_url('admin-ajax.php'),
        ),
        'supportsLayout' => wp_theme_has_theme_json(),
        'supportsTemplateMode' => current_theme_supports('block-templates'),
        'enableCustomFields' => (bool) get_user_meta(get_current_user_id(), 'enable_custom_fields', true),
        '__experimentalAdditionalBlockPatterns' => WP_Block_Patterns_Registry::get_instance()->get_all_registered(true),
        '__experimentalAdditionalBlockPatternCategories' => WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered(true),
);

$autosave = wp_get_post_autosave($post->ID);
if ($autosave && mysql2date('U', $autosave->post_modified_gmt, false) > mysql2date('U', $post->post_modified_gmt, false)) {
    $editor_settings['autosave'] = array('editLink' => get_edit_post_link($autosave->ID));
} else if ($autosave) {
    wp_delete_post_revision($autosave->ID);
}

$initial_edits = array();
$is_new_post = 'auto-draft' === $post->post_status;
if ($is_new_post) {
    if (post_type_supports($post->post_type, 'title')) $initial_edits['title'] = $post->post_title;
    if (post_type_supports($post->post_type, 'editor')) $initial_edits['content'] = $post->post_content;
    if (post_type_supports($post->post_type, 'excerpt')) $initial_edits['excerpt'] = $post->post_excerpt;
}

if (!empty($post_type_object->template)) {
    $editor_settings['template'] = $post_type_object->template;
    $editor_settings['templateLock'] = !empty($post_type_object->template_lock) ? $post_type_object->template_lock : false;
} elseif ($is_new_post && 'post' === $post->post_type) {
    $post_format = get_post_format($post);
    if (in_array($post_format, array('audio', 'gallery', 'image', 'quote', 'video'), true)) {
        $editor_settings['template'] = array(array("core/$post_format"));
    }
}

if (wp_is_block_theme() && $editor_settings['supportsTemplateMode']) {
    $editor_settings['defaultTemplatePartAreas'] = get_allowed_block_template_part_areas();
}

wp_enqueue_media(array('post' => $post->ID));
wp_tinymce_inline_scripts();
wp_enqueue_style('wp-edit-post');
wp_enqueue_editor();

/**
 * Styles
 */
wp_enqueue_style( 'wp-edit-post' );

/**
 * Fires after block assets have been enqueued for the editing interface.
 *
 * Call `add_action` on any hook before 'admin_enqueue_scripts'.
 *
 * In the function call you supply, simply use `wp_enqueue_script` and
 * `wp_enqueue_style` to add your functionality to the block editor.
 *
 * @since 5.0.0
 */
do_action( 'enqueue_block_editor_assets' );

require_once ABSPATH . 'wp-admin/includes/meta-boxes.php';
register_and_do_post_meta_boxes($post);

// Check if the Custom Fields meta box has been removed at some point.
$core_meta_boxes = $wp_meta_boxes[ $current_screen->id ]['normal']['core'];
if ( ! isset( $core_meta_boxes['postcustom'] ) || ! $core_meta_boxes['postcustom'] ) {
    unset( $editor_settings['enableCustomFields'] );
}

// Finalize editor settings
$editor_settings = get_block_editor_settings( $editor_settings, $block_editor_context );




$init_script = <<<JS
( function() {
    window._wpLoadBlockEditor = new Promise( function( resolve ) {
        wp.domReady( function() {
            wp.editPost.initializeEditor( 'editor', "%s", %d, %s, %s );
            wp.data.subscribe(() => {
                const content = wp.data.select("core/editor").getEditedPostContent();
                window.parent.postMessage(
                    {
                        type: 'gutenbergContentChanged',
                        content: content
                    },
                    '*'
                );
            });
            resolve(true);
        } );
    } );
} )();
JS;

$init_script = <<<JS
( function() {
    window._wpLoadBlockEditor = new Promise( function( resolve ) {
        wp.domReady( function() {
            //test-nuhel
            %s
             wp.editPost.initializeEditor( 'editor', "%s", %d, %s, %s );
            wp.data.subscribe(() => {
                const content = wp.data.select("core/editor").getEditedPostContent();
                window.parent.postMessage(
                    {
                        type: 'gutenbergContentChanged',
                        content: content
                    },
                    '*'
                );
            });
            resolve(true);
        } );
    } );
} )();
JS;

$script = sprintf(
        $init_script,
        'wp.blocks.unstable__bootstrapServerSideBlockDefinitions(' . wp_json_encode( get_block_editor_server_block_settings() ) . ');',
        $post->post_type,
        $post->ID,
        wp_json_encode($editor_settings),
        wp_json_encode($initial_edits)
);

//echo "<pre>";
//
//print_r($editor_settings);
//print_r($initial_edits);
//echo "</pre>";
//
//die();
wp_add_inline_script('wp-edit-post', $script);

require_once ABSPATH . 'wp-admin/admin-header.php';
?>

<div class="block-editor <?php echo $custom_class ?>">
    <h1 class="screen-reader-text hide-if-no-js"><?php echo esc_html($title); ?></h1>
    <div id="editor" class="block-editor__container hide-if-no-js"></div>
    <div id="metaboxes" class="hidden">
        <?php the_block_editor_meta_boxes(); ?>
    </div>

    <div class="wrap hide-if-js block-editor-no-js">
        <h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
        <?php
        $classic_editor_installed = file_exists(WP_PLUGIN_DIR . '/classic-editor/classic-editor.php');
        $url = $classic_editor_installed
                ? wp_nonce_url('plugins.php?action=activate&plugin=classic-editor/classic-editor.php', 'activate-plugin_classic-editor/classic-editor.php')
                : wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=classic-editor'), 'install-plugin_classic-editor');
        $message = sprintf(
                __('The block editor requires JavaScript. Please enable JavaScript in your browser settings, or %s the <a href="%s">Classic Editor plugin</a>.'),
                $classic_editor_installed ? 'activate' : 'install',
                esc_url($url ?? '')
        );
        $message = apply_filters('block_editor_no_javascript_message', $message, $post, $classic_editor_installed);
        wp_admin_notice($message, array('type' => 'error'));
        ?>
    </div>


</div>

<?php

if (function_exists('wp_enqueue_block_template_skip_link')) {
    wp_enqueue_block_template_skip_link();
}
//wp_footer();
?>
<style>
    body {
        background: transparent;
    }
    html #wpadminbar {
        display: none;
    }
    #gutenberg-editor .interface-interface-skeleton {
        top: 0;
    }
    html.interface-interface-skeleton__html-container {
        padding-top: 0;
    }
    .components-button.editor-post-publish-button,
    .interface-navigable-region.interface-interface-skeleton__footer,
    .wp-editor #wpfooter {
        display: none;
    }
</style>
