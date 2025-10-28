<?php

namespace FluentCart\Support\Http\Controllers;

use FluentCart\Support\Models\Ticket;
use FluentCart\Support\Services\TicketService;
use WP_REST_Request;
use WP_Error;

class TicketController
{
    protected TicketService $service;

    public function __construct()
    {
        $this->service = new TicketService();
    }

    public function index(WP_REST_Request $request)
    {
        $perPage = (int) $request->get_param('per_page') ?: 20;
        $tickets = Ticket::with(['inbox', 'replies'])->orderBy('created_at', 'desc')->limit($perPage)->get();

        return rest_ensure_response([
            'data' => $tickets->map(fn($ticket) => $ticket->toApiArray())->all(),
        ]);
    }

    public function store(WP_REST_Request $request)
    {
        $data = $request->get_params();

        try {
            $ticket = $this->service->createTicket($data);
            return rest_ensure_response($ticket->toApiArray());
        } catch (\Exception $exception) {
            return new WP_Error('fluentcart_support_error', $exception->getMessage(), ['status' => 400]);
        }
    }

    public function show(WP_REST_Request $request)
    {
        $ticketId = (int) $request->get_param('id');
        $ticket = Ticket::with(['replies', 'inbox'])->find($ticketId);

        if (!$ticket) {
            return new WP_Error('fluentcart_ticket_not_found', __('Ticket not found.', 'fluent-cart'), ['status' => 404]);
        }

        return rest_ensure_response($ticket->toApiArray());
    }

    public function reply(WP_REST_Request $request)
    {
        $ticketId = (int) $request->get_param('id');

        try {
            $ticket = $this->service->addReply($ticketId, $request->get_params());
            return rest_ensure_response($ticket->toApiArray());
        } catch (\Exception $exception) {
            return new WP_Error('fluentcart_support_error', $exception->getMessage(), ['status' => 400]);
        }
    }

    public function close(WP_REST_Request $request)
    {
        $ticketId = (int) $request->get_param('id');

        try {
            $ticket = $this->service->closeTicket($ticketId);
            return rest_ensure_response($ticket->toApiArray());
        } catch (\Exception $exception) {
            return new WP_Error('fluentcart_support_error', $exception->getMessage(), ['status' => 400]);
        }
    }
}
