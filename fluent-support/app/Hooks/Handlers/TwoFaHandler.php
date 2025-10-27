<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;


class TwoFaHandler
{
    public function maybe2FaRedirect($user = null)
    {
        $return = $this->sendAndGet2FaConfirmFormUrl($user, 'both');

        if (!$return) {
            return false;
        }

        $getForm = $this->get2faForm($return);

        wp_send_json([
            'load_2fa' => 'yes',
            'two_fa_form' => $getForm
        ]);
    }

    public function sendAndGet2FaConfirmFormUrl($user, $return = 'url')
    {
        try {
            $twoFaCode = str_pad(random_int(100123, 900987), 6, 0, STR_PAD_LEFT);
        } catch (\Exception $e) {
            $twoFaCode = str_pad(wp_rand(100123, 900987), 6, 0, STR_PAD_LEFT);
        }

        $string = $user->ID . '-' . wp_generate_uuid4() . wp_rand(1, 99999999);
        $hash = wp_hash_password($string);
        $hash = sanitize_title($hash, '', 'display');
        $hash .= $user->ID . '-' . time();

        $data = array(
            'login_hash' => $hash,
            'user_id' => $user->ID,
            'status' => 'issued',
            'ip_address' => $_SERVER['HTTP_USER_AGENT'],
            'use_type' => 'email_2_fa',
            'user_email' => $user->user_email,
            'two_fa_code_hash' => wp_hash_password($twoFaCode),
            'valid_till' => date('Y-m-d H:i:s', current_time('timestamp') + 10 * 30),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'used_count' => 0
        );

        $existingRecord = Meta::where('key', $hash)->first();

        if ($existingRecord) {
            $saveSettingsData = Meta::where('key', $hash)->update([
                'value' => maybe_serialize($data)
            ]);
        } else {
            $saveSettingsData = Meta::updateOrInsert([
                'object_type' => 'fs_2fa',
                'key' => $hash,
            ], [
                'value' => maybe_serialize($data)
            ]);
        }

        if (!$saveSettingsData) {
            return false;
        }
        $data['twoFaCode'] = $twoFaCode;
        $this->send2FaEmail($data, $user, '');

        return [
            'redirect_to' => add_query_arg([
                'fs_2fa' => 'email',
                'login_hash' => $hash,
                'action' => 'fs_2fa_email'
            ], wp_login_url()),
            'login_hash' => $hash,
        ];
    }

    public function verify2FaEmailCode($data)
    {
        $redirectUrl = Helper::getPortalBaseUrl();

        $code = $data['login_passcode'];
        $hash = $data['login_hash'];

        if (!$code || !$hash) {
            wp_send_json([
                'message' => __('Please provide a valid login code', 'fluent-support')
            ], 423);
        }

        $logHash = Meta::where('key', $hash)->first();
        $logHash = Helper::safeUnserialize($logHash->value, []);

        if (!$logHash) {
            wp_send_json([
                'message' => __('Your provided code or url is not valid', 'fluent-support')
            ], 423);
        }
        if (!wp_check_password($code, $logHash['two_fa_code_hash'])) {

            $logHash['used_count'] += 1;

            Meta::where('key', $hash)->update([
                'value' => maybe_serialize($logHash)
            ]);

            return false;
        }

        if (strtotime($logHash['created_at']) < current_time('timestamp') - 600 || $logHash['used_count'] > 5 || $logHash['status'] != 'issued') {
            wp_send_json([
                'message' => __('Sorry, your login code has been expired. Please try to login again', 'fluent-support')
            ], 423);
        }
        $user = get_user_by('email', $logHash['user_email']);

        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        if (is_user_logged_in()) {
            $logHash['status'] = 'used';

            Meta::where('key', $hash)->update([
                'value' => maybe_serialize($logHash)
            ]);
        }

        wp_send_json([
            'redirect' => $redirectUrl
        ], 200);
    }

    private function send2FaEmail($data, $user, $autoLoginUrl = false)
    {
        $emailTo = $user->user_email;
        $emailSubject = sprintf(__('Your Login code for %1s', 'fluent-support'), get_bloginfo('name'));

        $pStart = '<p style="font-family: Arial, sans-serif; font-size: 16px; font-weight: normal; margin: 0; margin-bottom: 16px;">';

        $message = $pStart . sprintf(__('Hello %s,', 'fluent-support'), $user->display_name) . '</p>' .
            $pStart . sprintf(__('Someone requested to login to %s and here is the Login code that you can use in the login form', 'fluent-support'), get_bloginfo('name')) . '</p>' .
            $pStart . '<b>' . sprintf(__('Verification Code: %s', 'fluent-security'), $data['twoFaCode']) . '</b></p>' .
            '<br />' .
            $pStart . __('This code is valid for 10 minutes and is meant to ensure the security of your account. If you did not initiate this request, please ignore this email.', 'fluent-security') . '</p>';

        $message = apply_filters('fluent_support/signup_verification_email_body', $message, $data['twoFaCode'], $data);

        $data = [
            'body'        => $message,
            'pre_header'  => __('Activate your account', 'fluent-security'),
            'show_footer' => false
        ];

        $message = Helper::loadView('notification', $data);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        \wp_mail($emailTo, $emailSubject, $message, $headers);
    }

    public function get2faForm($data = [])
    {
        ob_start();
        ?>
            <form
                style="margin-top: 20px; padding: 20px; font-weight: 400; overflow: hidden; background: #f6f6f6; border: 1px solid #ccc; box-shadow: 0 0 10px rgba(0,0,0,.15);"
                class="fs_2fa" id="fs_2fa_form">
                <input type="hidden" name="login_hash" value="<?php echo esc_attr($data['login_hash']); ?>"/>
                <div style="margin-bottom: 10px;">
                    <?php esc_html_e('Please check your email inbox and enter the two-factor verification code below:', 'fluent-support'); ?>
                </div>
                <div style="margin-bottom: 10px;">
                    <label for="login_passcode"><?php esc_html_e('Verification Code', 'fluent-support'); ?></label>
                    <div>
                        <input
                            style="font-size: 14px; padding: 8px; border: 1px solid #ccc; border-radius: 3px; width: 100%; box-sizing: border-box;"
                            placeholder="<?php esc_html_e('Login Code', 'fluent-support'); ?>" type="text" name="login_passcode"
                            id="login_passcode" class="input" size="20"/>
                    </div>
                </div>
                <div>
                    <button
                        style="display: inline-block; cursor: pointer; border: 0; background: #2271b1; color: #fff; text-decoration: none; text-shadow: none; min-height: 32px; padding: 8px 24px; font-size: 14px; border-radius: 3px;"
                        id="fs_2fa_confirm" type="submit">
                        <?php esc_html_e('Verify and Login', 'fluent-support'); ?>
                    </button>
                </div>
            </form>
        <?php

        return ob_get_clean();
    }

}
