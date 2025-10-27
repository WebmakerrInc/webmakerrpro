<?php

namespace FluentCart\App\Http\Policies;

use FluentCart\Framework\Http\Request\Request;

class ProductPolicy extends Policy
{
    public function verifyRequest(Request $request): bool
    {
        return $this->hasRoutePermissions();
    }

    public function index(Request $request): bool
    {
        return $this->userCan('products/view');
    }

    public function store(Request $request): bool
    {
        return $this->userCan('products/create');
    }

    public function update(Request $request): bool
    {
        return $this->userCan('products/edit');
    }

    public function delete(Request $request): bool
    {
        return $this->userCan('products/delete');
    }
}
