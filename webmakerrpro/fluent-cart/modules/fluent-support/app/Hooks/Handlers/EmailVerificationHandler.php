<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;


class EmailVerificationHandler
{
    public static function sendSignupEmailVerificationHtml($formData)
    {
        try {
            $verifcationCode = str_pad(random_int(100123, 900987), 6, 0, STR_PAD_LEFT);
        } catch (\Exception $e) {
            $verifcationCode = str_pad(wp_rand(100123, 900987), 6, 0, STR_PAD_LEFT);
        }

        $string = $formData['email'] . '-' . wp_generate_uuid4() . wp_rand(1, 99999999);
        $hash = wp_hash_password($string);
        $hash = sanitize_title($hash, '', 'display');
        $hash .= $formData['email'] . '-' . time();

        $data = array(
            'login_hash'       => $hash,
            'status'           => 'issued',
            'ip_address'       => Helper::getIp(),
            'use_type'         => 'signup_verification',
            'used_count'       => 0,
            'two_fa_code_hash' => wp_hash_password($verifcationCode),
            'valid_till'       => date('Y-m-d H:i:s', current_time('timestamp') + 10 * 60),
            'created_at'       => current_time('mysql'),
            'updated_at'       => current_time('mysql')
        );

        Meta::insert([
            'object_type' => 'fs_login_hashes',
            'key'         => $hash,
            'value'       => maybe_serialize($data)
        ]);

        $mailSubject = apply_filters("fluent_support/signup_verification_mail_subject", sprintf(__('Your registration verification code for %s', 'fluent-support'), get_bloginfo('name')));

        $pStart = '<p style="font-family: Arial, sans-serif; font-size: 16px; font-weight: normal; margin: 0; margin-bottom: 16px;">';

        $message = $pStart . sprintf(__('Hello %s,', 'fluent-support'), Arr::get($formData, 'first_name')) . '</p>' .
            $pStart . __('Thank you for registering with us! To complete the setup of your account, please enter the verification code below on the registration page.', 'fluent-support') . '</p>' .
            $pStart . '<b>' . sprintf(__('Verification Code: %s', 'fluent-support'), $verifcationCode) . '</b></p>' .
            '<br />' .
            $pStart . __('This code is valid for 10 minutes and is meant to ensure the security of your account. If you did not initiate this request, please ignore this email.', 'fluent-support') . '</p>';

        $message = apply_filters('fluent_support/signup_verification_email_body', $message, $verifcationCode, $formData);

        $data = [
            'body'        => $message,
            'pre_header'  => __('Activate your account', 'fluent-support'),
            'show_footer' => false
        ];

        $message = Helper::loadView('notification', $data);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        \wp_mail($formData['email'], $mailSubject, $message, $headers);

        ob_start();
        ?>
            <div class="fs_signup_verification">
                <div class="fs_field_group fs_field_verification">
                    <p><?php echo esc_html(sprintf(__('A verification code has been sent to %s. Please provide the code below:', 'fluent-support'), $formData['email'])); ?></p>
                    <input type="hidden" name="_email_verification_hash" value="<?php echo esc_attr($hash); ?>"/>
                    <div class="fs_field_label is-required">
                        <label for="fs_field_verification"><?php esc_html_e('Verification Code', 'fluent-support'); ?></label>
                    </div>
                    <div class="fs_input_wrap">
                        <input type="text" id="fs_field_verification" placeholder="" name="_email_verification_token" required>
                    </div>
                </div>
                <button
                    style="display: inline-block; cursor: pointer; border: 0; background: #2271b1; color: #fff; text-decoration: none; text-shadow: none; min-height: 32px; padding: 8px 24px; font-size: 14px; border-radius: 3px; margin-top: 10px;"
                    id="fs_verification_submit" type="submit">
                    <?php esc_html_e('Complete Signup', 'fluent-support'); ?>
                </button>
            </div>
        <?php
        return ob_get_clean();
    }

}
