<?php

namespace FluentSuite\Modules\FluentSupport\Http;

use FluentSuite\Modules\FluentSupport\Services\TicketService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class TicketController
{
    protected TicketService $ticketService;

    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    public function registerRoutes(): void
    {
        register_rest_route('fluent-suite/v1', '/support/tickets', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canManageTickets']
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'store'],
                'permission_callback' => [$this, 'allowPublicTicketCreation']
            ],
        ]);

        register_rest_route('fluent-suite/v1', '/support/tickets/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'show'],
                'permission_callback' => [$this, 'canViewTicket']
            ],
        ]);

        register_rest_route('fluent-suite/v1', '/support/tickets/(?P<id>\d+)/reply', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'reply'],
                'permission_callback' => [$this, 'canReply']
            ],
        ]);
    }

    public function index(WP_REST_Request $request)
    {
        $this->ensureManagePermission();

        $tickets = $this->ticketService->getTickets([
            'limit'  => $request->get_param('per_page') ? (int) $request->get_param('per_page') : 20,
            'offset' => $request->get_param('offset') ? (int) $request->get_param('offset') : 0,
        ]);

        return rest_ensure_response([
            'data' => $tickets,
        ]);
    }

    public function store(WP_REST_Request $request)
    {
        $data = [
            'customer_email' => (string) $request->get_param('email'),
            'subject'        => (string) $request->get_param('subject'),
            'message'        => (string) $request->get_param('message'),
        ];

        if (!$data['customer_email'] || !$data['subject'] || !$data['message']) {
            return new WP_Error('invalid_ticket', __('Email, subject, and message are required.', 'fluent-suite'), ['status' => 422]);
        }

        $ticket = $this->ticketService->createTicket($data);

        return new WP_REST_Response(['data' => $ticket], 201);
    }

    public function show(WP_REST_Request $request)
    {
        $this->ensureManagePermission();

        $ticket = $this->ticketService->getTicket((int) $request['id']);
        if (!$ticket) {
            return new WP_Error('ticket_not_found', __('Ticket not found', 'fluent-suite'), ['status' => 404]);
        }

        return rest_ensure_response(['data' => $ticket]);
    }

    public function reply(WP_REST_Request $request)
    {
        $this->ensureManagePermission();

        $message = (string) $request->get_param('message');
        if (!$message) {
            return new WP_Error('invalid_message', __('Reply message is required.', 'fluent-suite'), ['status' => 422]);
        }

        $ticketId = (int) $request['id'];
        $reply    = $this->ticketService->addReply($ticketId, [
            'author'      => wp_get_current_user()->display_name ?: 'Agent',
            'author_type' => 'agent',
            'message'     => $message,
        ]);

        if (!$reply) {
            return new WP_Error('ticket_not_found', __('Ticket not found', 'fluent-suite'), ['status' => 404]);
        }

        return rest_ensure_response(['data' => $reply]);
    }

    public function canManageTickets(): bool
    {
        return current_user_can('manage_options');
    }

    public function canViewTicket(): bool
    {
        return $this->canManageTickets();
    }

    public function canReply(): bool
    {
        return $this->canManageTickets();
    }

    public function allowPublicTicketCreation(): bool
    {
        return true;
    }

    protected function ensureManagePermission(): void
    {
        if (!$this->canManageTickets()) {
            wp_die(__('You are not allowed to manage tickets.', 'fluent-suite'));
        }
    }
}
