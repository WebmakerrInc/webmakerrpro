<?php

namespace FluentCart\Support\Http\Controllers;

use FluentCart\Support\Models\Ticket;
use FluentCart\Support\Services\AiService;
use WP_REST_Request;
use WP_Error;

class AiController
{
    protected AiService $service;

    public function __construct()
    {
        $this->service = new AiService();
    }

    public function suggest(WP_REST_Request $request)
    {
        $ticketId = (int) $request->get_param('ticket_id');
        $prompt = (string) $request->get_param('prompt');
        $ticket = $ticketId ? Ticket::with('replies')->find($ticketId) : null;

        try {
            $suggestion = $this->service->suggestReply($ticket, $prompt);
            return rest_ensure_response(['suggestion' => $suggestion]);
        } catch (\Exception $exception) {
            return new WP_Error('fluentcart_support_ai_error', $exception->getMessage(), ['status' => 400]);
        }
    }
}
