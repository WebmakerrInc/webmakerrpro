<?php

namespace FluentCart\Support\Http\Controllers;

use FluentCart\Support\Services\TicketService;
use WP_REST_Request;
use WP_Error;

class InboxController
{
    protected TicketService $service;

    public function __construct()
    {
        $this->service = new TicketService();
    }

    public function index()
    {
        return rest_ensure_response([
            'data' => $this->service->getAllInboxes()->map->toApiArray()->all(),
        ]);
    }

    public function store(WP_REST_Request $request)
    {
        try {
            $inbox = $this->service->saveInbox($request->get_params());
            return rest_ensure_response($inbox->toApiArray());
        } catch (\Exception $exception) {
            return new WP_Error('fluentcart_support_error', $exception->getMessage(), ['status' => 400]);
        }
    }

    public function update(WP_REST_Request $request)
    {
        $data = $request->get_params();
        $data['id'] = (int) $request->get_param('id');

        try {
            $inbox = $this->service->saveInbox($data);
            return rest_ensure_response($inbox->toApiArray());
        } catch (\Exception $exception) {
            return new WP_Error('fluentcart_support_error', $exception->getMessage(), ['status' => 400]);
        }
    }

    public function delete(WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');

        try {
            $this->service->deleteInbox($id);
            return rest_ensure_response(['deleted' => true]);
        } catch (\Exception $exception) {
            return new WP_Error('fluentcart_support_error', $exception->getMessage(), ['status' => 400]);
        }
    }
}
