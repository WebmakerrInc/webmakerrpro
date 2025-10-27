<?php
namespace FluentSupport\App\Http\Controllers;

use FluentSupport\Framework\Request\Request;
use FluentSupport\App\Hooks\Handlers\TwoFaHandler;



class TwofaController extends Controller
{
    public function verify2fa  ( Request $request )
    {
        $data = $request->get();
        $data['login_passcode'] = sanitize_text_field($data['login_passcode']);
        $data['login_hash'] = sanitize_text_field($data['login_hash']);

        $verify = (new TwoFaHandler)->verify2FaEmailCode($data);
        if(!$verify){
            return $this->response([
                'message' => __('Your provided code is not valid. Please try again', 'fluent-support')
            ], 423);
        }
    }

}
