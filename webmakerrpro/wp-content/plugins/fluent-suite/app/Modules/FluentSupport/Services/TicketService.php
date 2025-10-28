<?php

namespace FluentSuite\Modules\FluentSupport\Services;

class TicketService
{
    protected \wpdb $wpdb;

    protected string $ticketTable;

    protected string $replyTable;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb        = $wpdb;
        $this->ticketTable = $this->wpdb->prefix . 'fluent_suite_tickets';
        $this->replyTable  = $this->wpdb->prefix . 'fluent_suite_ticket_replies';
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    public function createTicket(array $data): ?array
    {
        $now = current_time('mysql');
        $ticketData = [
            'customer_email' => sanitize_email($data['customer_email'] ?? ''),
            'subject'        => sanitize_text_field($data['subject'] ?? ''),
            'status'         => $data['status'] ?? 'open',
            'created_at'     => $now,
            'updated_at'     => $now,
        ];

        $this->wpdb->insert($this->ticketTable, $ticketData);
        $ticketId = (int) $this->wpdb->insert_id;

        if (!$ticketId) {
            return null;
        }

        if (!empty($data['message'])) {
            $this->addReply($ticketId, [
                'author'      => $data['customer_email'] ?? 'Customer',
                'author_type' => 'customer',
                'message'     => $data['message'],
                'created_at'  => $now,
            ], false);
        }

        $ticket = $this->getTicket($ticketId);
        if ($ticket) {
            do_action('fluent_suite_ticket_created', $ticket, [
                'message'     => $data['message'] ?? '',
                'author_type' => 'customer'
            ]);
        }

        return $ticket;
    }

    /**
     * @param int $ticketId
     *
     * @return array<string, mixed>|null
     */
    public function getTicket(int $ticketId): ?array
    {
        $ticket = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->ticketTable} WHERE id = %d", $ticketId),
            ARRAY_A
        );

        if (!$ticket) {
            return null;
        }

        $ticket['replies'] = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->replyTable} WHERE ticket_id = %d ORDER BY created_at ASC", $ticketId),
            ARRAY_A
        );

        return $ticket;
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTickets(array $args = []): array
    {
        $limit  = isset($args['limit']) ? (int) $args['limit'] : 20;
        $offset = isset($args['offset']) ? (int) $args['offset'] : 0;

        $tickets = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->ticketTable} ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        return $tickets ?: [];
    }

    /**
     * @param int                    $ticketId
     * @param array<string, mixed>   $data
     * @param bool                   $triggerHooks
     *
     * @return array<string, mixed>|null
     */
    public function addReply(int $ticketId, array $data, bool $triggerHooks = true): ?array
    {
        if (!$ticketId) {
            return null;
        }

        $now = isset($data['created_at']) ? $data['created_at'] : current_time('mysql');
        $replyData = [
            'ticket_id'   => $ticketId,
            'author'      => sanitize_text_field($data['author'] ?? ''),
            'author_type' => sanitize_text_field($data['author_type'] ?? 'customer'),
            'message'     => wp_kses_post($data['message'] ?? ''),
            'created_at'  => $now,
        ];

        $this->wpdb->insert($this->replyTable, $replyData);
        $replyId = (int) $this->wpdb->insert_id;

        if (!$replyId) {
            return null;
        }

        $this->wpdb->update(
            $this->ticketTable,
            [
                'updated_at' => $now,
                'status'     => $replyData['author_type'] === 'customer' ? 'open' : 'responded'
            ],
            ['id' => $ticketId]
        );

        if ($triggerHooks) {
            $ticket = $this->getTicket($ticketId);
            if ($ticket) {
                do_action('fluent_suite_ticket_reply_created', $ticket, $replyData);
            }
        }

        return $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->replyTable} WHERE id = %d", $replyId),
            ARRAY_A
        );
    }

    public function updateStatus(int $ticketId, string $status): void
    {
        $this->wpdb->update(
            $this->ticketTable,
            [
                'status'     => sanitize_text_field($status),
                'updated_at' => current_time('mysql')
            ],
            ['id' => $ticketId]
        );
    }
}
