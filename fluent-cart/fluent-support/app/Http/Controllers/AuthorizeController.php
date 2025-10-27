<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\Framework\Request\Request;

class AuthorizeController extends Controller
{
    public function handleHelpScoutAuthorization(Request $request)
    {
        wp_redirect(admin_url('admin.php?page=fluent-support#/help_scout?code=' . $request->get('code')));
    }
}
