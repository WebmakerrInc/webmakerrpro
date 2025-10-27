<?php

namespace FluentCart\App\Modules\Integrations\MailChimp;

use FluentCart\App\Helpers\Helper;
use FluentCart\App\Modules\Integrations\BaseIntegrationManager;
use FluentCart\App\Modules\Integrations\MailChimp\MailChimpSubscriber as Subscriber;
use FluentCart\App\Vite;
use FluentCart\Framework\Support\Arr;

class MailChimpBaseIntegration extends BaseIntegrationManager
{
    /**
     * MailChimp Subscriber that handles & process all the subscribing logics.
     */
    use Subscriber;

    public function __construct($application)
    {
        parent::__construct(
            $application,
            'MailChimp',
            'mailchimp',
            '_fluent_cart_mailchimp_details',
            'mailchimp',
            12
        );

        $this->description = __('FluentCart Mailchimp module allows you to create Mailchimp newsletter signup forms in WordPress', 'fluent-cart');
        $this->logo = Vite::getAssetUrl('images/integrations/mailchimp.svg');

        $this->registerAdminHooks();

        add_action('fluent_cart/integration/chained_mailchimp_interest_groups', array($this, 'fetchInterestGroups'));

//        add_filter('fluent_cart/integration/notifying_async_mailchimp', '__return_false');
    }

    public function getGlobalFields($fields, $args): array
    {
        return [
            'logo' => esc_url($this->logo ?? ''),
            'menu_title' => __('Mailchimp Settings', 'fluent-cart'),
            'menu_description' => wp_kses( __( 'Mailchimp is a marketing platform for small businesses. Send beautiful emails, connect your e-commerce store, advertise, and build your brand. Use FluentCart to collect customer information and automatically add it to your Mailchimp campaign list. If you don\'t have a Mailchimp account, you can <a href="http://www.mailchimp.com/" target="_blank">sign up for one here.</a>', 'fluent-cart' ), [
                'a' => [
                    'href' => true,
                    'target' => true
                ]
            ] ),
            'valid_message' => __('Your Mailchimp API Key is valid', 'fluent-cart'),
            'invalid_message' => __('Your Mailchimp API Key is not valid', 'fluent-cart'),
            'save_button_text' => __('Save Settings', 'fluent-cart'),
            'fields' => [
                'apiKey' => [
                    'placeholder' => __("Your mailchimp api key", "fluent-cart"),
                    'type' => 'text',
                    'label_tips' => __("Enter your Mailchimp API Key if you do not have Please log in to your MailChimp account and go to Profile -> Extras -> Api Keys", 'fluent-cart'),
                    'label' => __('Mailchimp API Key', 'fluent-cart'),
                ]
            ],
            'hide_on_valid' => true,
            'discard_settings' => [
                'section_description' => __('Your Mailchimp API integration is up and running','fluent-cart'),
                'disconnect_button_text' => __('Disconnect Mailchimp','fluent-cart'),
                'verify_button_text' => __('Verify Connection Again','fluent-cart'),
                'data' => [
                    'apiKey' => ''
                ],
                'show_verify' => true
            ]
        ];
    }

    public function getGlobalSettings($settings, $args)
    {
        $globalSettings = fluent_cart_get_option($this->optionKey);
        if (!$globalSettings) {
            $globalSettings = [];
        }
        $defaults = [
            'apiKey' => '',
            'status' => ''
        ];
        return wp_parse_args($globalSettings, $defaults);
    }

    public function saveGlobalSettings($data)
    {
        $mailChimp = Arr::get($data, 'integration');
        if (!$mailChimp['apiKey']) {
            $mailChimpSettings = [
                'apiKey' => '',
                'status' => false
            ];
            // Update the reCAPTCHA details with siteKey & secretKey.
            fluent_cart_update_option($this->optionKey, $mailChimpSettings);
            wp_send_json([
                'message' => __('Your settings has been updated and disconnected', 'fluent-cart'),
                'status' => false
            ], 200);
        }

        // Verify an API key now
        try {
            $MailChimp = new MailChimp($mailChimp['apiKey']);
            $result = $MailChimp->get('lists');
            if (!$MailChimp->success()) {
                throw new \Exception($MailChimp->getLastError());
            }
        } catch (\Exception $exception) {
            wp_send_json([
                'message' => $exception->getMessage()
            ], 400);
        }

        // MailChimp key is verified now, Proceed now

        $mailChimpSettings = [
            'apiKey' => sanitize_text_field($mailChimp['apiKey']),
            'status' => true
        ];

        // Update the reCaptcha details with siteKey & secretKey.
        fluent_cart_update_option($this->optionKey, $mailChimpSettings);

        wp_send_json([
            'message' => __('Your mailchimp api key has been verified and successfully set', 'fluent-cart'),
            'status' => true
        ], 200);
    }

    public function pushIntegration($integrations)
    {
        $integrations['mailchimp'] = [
            'title' => __('Mailchimp Feed', 'fluent-cart'),
            'logo' => $this->logo,
            'is_active' => $this->isConfigured(),
            'configure_title' => __('Configuration required!', 'fluent-cart'),
            'global_configure_url' => admin_url('admin.php?page=fluent-cart#/integrations/mailchimp'),
            'configure_message' => __('Mailchimp is not configured yet! Please configure your mailchimp api first', 'fluent-cart'),
            'configure_button_text' => __('Set Mailchimp API', 'fluent-cart')
        ];

        return $integrations;
    }

    public function getIntegrationDefaults($settings)
    {
        $settings = [
            // 'conditionals' => [
            //     'conditions' => [],
            //     'status' => false,
            //     'type' => 'all'
            // ],
            'enabled' => true,
            'list_id' => '',
            'list_name' => '',
            'name' => '',
            'tags' => '',
            'tag_routers' => [],
            'tag_ids_selection_type' => 'simple',
            'markAsVIP' => false,
            'fieldEmailAddress' => '',
            'doubleOptIn' => false,
            'resubscribe' => false,
            'note' => ''
        ];

        return $settings;
    }

    public function getSettingsFields($settings)
    {
        $fields = [
                [
                    'key' => 'name',
                    'label' => __('Name', 'fluent-cart'),
                    'required' => true,
                    'placeholder' => __('Your Name', 'fluent-cart'),
                    'component' => 'text',
                    'inline_tip' => __('Name of this feed, it will be used to identify this feed in the list of feeds', 'fluent-cart')
                ],
                [
                    'key' => 'list_id',
                    'label' => __('List', 'fluent-cart'),
                    'placeholder' => __('Select Mailchimp List', 'fluent-cart'),
                    'inline_tip' => esc_html__('Select the Mailchimp list you would like to add your contacts to.', 'fluent-cart'),
                    'component' => 'list_ajax_options',
                    'options' => $this->getLists(),
                ],
                [
                    'key' => 'primary_fields',
                    'require_list' => true,
                    'label' => __('Primary Fields', 'fluent-cart'),
                    'inline_tip' => esc_html__('Associate your Mailchimp merge tags to the appropriate FluentCart fields by selecting the appropriate form field from the list.', 'fluent-cart'),
                    'component' => 'map_fields',
                    'field_label_remote' => __('Mailchimp Field', 'fluent-cart'),
                    'field_label_local' => __('Form Field', 'fluent-cart'),
                    'primary_fileds' => [
                        [
                            'key' => 'fieldEmailAddress',
                            'label' => __('Email Address', 'fluent-cart'),
                            'required' => true,
                            'input_options' => 'emails'
                        ]
                    ]
                ],
                [
                    'key' => 'interest_group',
                    'require_list' => true,
                    'label' => __('Interest Group', 'fluent-cart'),
                    'component' => 'chained_fields',
                    'sub_type' => 'radio',
                    'category_label' => __('Select Interest Category', 'fluent-cart'),
                    'subcategory_label' => __('Select Interest', 'fluent-cart'),
                    // 'remote_url'   => admin_url('admin-ajax.php?action=fluent_cart_mailchimp_interest_groups'),
                    'remote_url' => 'mailchimp_interest_groups',
                    'inline_tip' => __('Select the mailchimp interest category and interest', 'fluent-cart')
                ],
                [
                    'key' => 'tags',
                    'require_list' => true,
                    'label' => __('Tags', 'fluent-cart'),
                    'tips' => esc_html__('Associate tags to your MailChimp contacts with a comma separated list (e.g. new lead, FluentCart, web source). Commas within a merge tag value will be created as a single tag.', 'fluent-cart'),
                    'component' => 'selection_routing',
                    'simple_component' => 'value_text',
                    'routing_input_type' => 'text',
                    'routing_key' => 'tag_ids_selection_type',
                    'settings_key' => 'tag_routers',
                    'labels' => [
                        'choice_label' => __('Enable Dynamic Tag Input', 'fluent-cart'),
                        'input_label' => '',
                        'input_placeholder' => __('Tag', 'fluent-cart')
                    ],
                ],
                [
                    'key' => 'note',
                    'require_list' => true,
                    'label' => __('Note', 'fluent-cart'),
                    'tips' => __('You can write a note for this contact', 'fluent-cart'),
                    'component' => 'value_textarea'
                ],
                [
                    'key' => 'doubleOptIn',
                    'require_list' => true,
                    'label' => __('Double Opt-in', 'fluent-cart'),
                    'tips' => esc_html__('When the double opt-in option is enabled. Mailchimp will send a confirmation email to the user and will only add them to your Mailchimp list upon confirmation.', 'fluent-cart'),
                    'component' => 'checkbox-single',
                    'checkbox_label' => __('Enable Double Opt-in', 'fluent-cart'),
                    'inline_tip' => __('When the double opt-in option is enabled. Mailchimp will send a confirmation email to the user and will only add them to your Mailchimp list upon confirmation.', 'fluent-cart')
                ],
                [
                    'key' => 'resubscribe',
                    'require_list' => true,
                    'label' => __('ReSubscribe', 'fluent-cart'),
                    'tips' => esc_html__('When this option is enabled, if the subscriber is in an inactive state or has previously been unsubscribed, they will be re-added to the active list. Therefore, this option should be used with caution and only when appropriate.', 'fluent-cart'),
                    'component' => 'checkbox-single',
                    'checkbox_label' => __('Enable ReSubscription', 'fluent-cart'),
                    'inline_tip' => __('When this option is enabled, if the subscriber is in an inactive state or has previously been unsubscribed, they will be re-added to the active list. Therefore, this option should be used with caution and only when appropriate.', 'fluent-cart')
                ],
                [
                    'key' => 'markAsVIP',
                    'require_list' => true,
                    'label' => __('VIP', 'fluent-cart'),
                    'tips' => esc_html__('When enabled, This contact will be marked as VIP.', 'fluent-cart'),
                    'component' => 'checkbox-single',
                    'checkbox_label' => __('Mark as VIP Contact', 'fluent-cart'),
                    'inline_tip' => __('When enabled, This contact will be marked as VIP.', 'fluent-cart')
                ],
                [
                    'key' => 'enabled',
                    'label' => 'Status',
                    'component' => 'checkbox-single',
                    'checkbox_label' => __('Enable This feed', 'fluent-cart')
                ],
                // [
                //     'require_list' => true,
                //     'key' => 'conditionals',
                //     'label' => 'Conditional Logics',
                //     'tips' => 'Allow mailchimp integration conditionally based on your submission values',
                //     'component' => 'conditional_block'
                // ],
        ];

        $fields[] = $this->actionFields();
        return [
            'fields' => $fields,
            'button_require_list' => true,
            'integration_title' => __('Mailchimp', 'fluent-cart')
        ];
    }

    public function setFeedAtributes($feed)
    {
        $feed['provider'] = 'mailchimp';
        $feed['provider_logo'] = $this->logo;
        return $feed;
    }

    public function prepareIntegrationFeed($setting, $feed)
    {
        $defaults = $this->getIntegrationDefaults([]);

        foreach ($setting as $settingKey => $settingValue) {
            if ($settingValue == 'true') {
                $setting[$settingKey] = true;
            } elseif ($settingValue == 'false') {
                $setting[$settingKey] = false;
            } elseif ($settingKey == 'conditionals') {
                if ($settingValue['status'] == 'true') {
                    $settingValue['status'] = true;
                } elseif ($settingValue['status'] == 'false') {
                    $settingValue['status'] = false;
                }
                $setting['conditionals'] = $settingValue;
            }
        }

        if (!empty($setting['list_id'])) {
            $setting['list_id'] = (string)$setting['list_id'];
        }

        $settings['markAsVIP'] = Helper::isTrue($setting, 'markAsVIP');
        $settings['doubleOptIn'] = Helper::isTrue($setting, 'doubleOptIn');

        return wp_parse_args($setting, $defaults);
    }

    private function getLists()
    {
        $settings = fluent_cart_get_option('_fluent_cart_mailchimp_details');
        try {
            $MailChimp = new MailChimp($settings['apiKey']);
            $lists = $MailChimp->get('lists', array('count' => 9999));
            if (!$MailChimp->success()) {
                return [];
            }
        } catch (\Exception $exception) {
            return [];
        }

        $formattedLists = [];
        foreach ($lists['lists'] as $list) {
            $formattedLists[$list['id']] = $list['name'];
        }

        return $formattedLists;
    }

    public function getMergeFields($list, $listId)
    {
        if (!$this->isConfigured()) {
            return false;
        }
        $settings = fluent_cart_get_option('_fluent_cart_mailchimp_details');

        try {
            $MailChimp = new MailChimp($settings['apiKey']);
            $list = $MailChimp->get('lists/' . $listId . '/merge-fields', array('count' => 9999));
            if (!$MailChimp->success()) {
                return false;
            }
        } catch (\Exception $exception) {
            return false;
        }

        $mergedFields = $list['merge_fields'];
        $fields = array();
        
        foreach ($mergedFields as $merged_field) {
            $fields[$merged_field['tag']] = $merged_field['name'];
        }

        return $fields;
    }

    public function fetchInterestGroups($data)
    {
        $settings = wp_unslash(Arr::get($data, 'data.settings'));

        $listId = Arr::get($settings, 'list_id');
        if (!$listId) {
            wp_send_json([
                'categories' => [],
                'subcategories' => [],
                'reset_values' => true
            ]);
        }

        $categoryId = Arr::get($settings, 'interest_group.category');
        $categories = $this->getInterestCategories($listId);

        $subCategories = [];
        if ($categoryId) {
            $subCategories = $this->getInterestSubCategories($listId, $categoryId);
        }

        wp_send_json([
            'categories' => $categories,
            'subcategories' => $subCategories,
            'reset_values' => !$categories && !$subCategories
        ]);
    }

    private function getInterestCategories($listId)
    {
        $settings = fluent_cart_get_option('_fluent_cart_mailchimp_details');
        try {
            $MailChimp = new MailChimp($settings['apiKey']);
            $categories = $MailChimp->get('/lists/' . $listId . '/interest-categories', array(
                'count' => 9999,
                'fields' => 'categories.id,categories.title'
            ));
            if (!$MailChimp->success()) {
                return [];
            }
        } catch (\Exception $exception) {
            return [];
        }
        $categories = Arr::get($categories, 'categories', []);
        $formattedLists = [];
        foreach ($categories as $list) {
            $formattedLists[] = [
                'value' => $list['id'],
                'label' => $list['title']
            ];
        }
        return $formattedLists;
    }

    private function getInterestSubCategories($listId, $categoryId)
    {
        $settings = fluent_cart_get_option('_fluent_cart_mailchimp_details');
        try {
            $MailChimp = new MailChimp($settings['apiKey']);
            $categories = $MailChimp->get('/lists/' . $listId . '/interest-categories/' . $categoryId . '/interests', array(
                'count' => 9999,
                'fields' => 'interests.id,interests.name'
            ));
            if (!$MailChimp->success()) {
                return [];
            }
        } catch (\Exception $exception) {
            return [];
        }
        $categories = Arr::get($categories, 'interests', []);
        $formattedLists = [];
        foreach ($categories as $list) {
            $formattedLists[] = [
                'value' => $list['id'],
                'label' => $list['name']
            ];
        }
        return $formattedLists;
    }


    /*
    * For Handling Notifications broadcast
    */
    public function notify($feed, $order, $customer)
    {
        return $this->subscribe($feed, $order, $customer);
    }
}
