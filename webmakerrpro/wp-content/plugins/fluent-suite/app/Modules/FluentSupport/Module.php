<?php

namespace FluentSuite\Modules\FluentSupport;

use FluentSuite\Modules\Module as BaseModule;
use FluentSuite\Modules\FluentSupport\Database\Migrations;
use FluentSuite\Modules\FluentSupport\Http\TicketController;
use FluentSuite\Modules\FluentSupport\Services\OpenAiResponder;
use FluentSuite\Modules\FluentSupport\Services\TicketService;

class Module extends BaseModule
{
    /**
     * @var TicketService
     */
    protected $ticketService;

    /**
     * @var OpenAiResponder
     */
    protected $openAiResponder;

    public function __construct(string $slug)
    {
        parent::__construct($slug);
        $this->ticketService    = new TicketService();
        $this->openAiResponder = new OpenAiResponder($this->ticketService);
    }

    public function getName(): string
    {
        return 'Fluent Support Core';
    }

    public function getDescription(): string
    {
        return 'Ticket management, agent UI, and OpenAI auto-responses.';
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('init', [$this, 'registerPostHooks']);
        add_action('fluent_suite_ticket_created', [$this, 'maybeHandleAutoReply'], 10, 2);
        add_action('fluent_suite_ticket_reply_created', [$this, 'maybeHandleAutoReply'], 10, 2);
    }

    public function activate(): void
    {
        (new Migrations())->migrate();
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'fluent-suite',
            __('Support Tickets', 'fluent-suite'),
            __('Support', 'fluent-suite'),
            'manage_options',
            'fluent-suite-support',
            [$this, 'renderAdminPage']
        );
    }

    public function registerRestRoutes(): void
    {
        $controller = new TicketController($this->ticketService);
        $controller->registerRoutes();
    }

    public function registerPostHooks(): void
    {
        if (did_action('fluent_suite_support_post_hooks')) {
            return;
        }

        do_action('fluent_suite_support_post_hooks');
    }

    public function renderAdminPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to access this page.', 'fluent-suite'));
        }

        if (isset($_POST['fluent_suite_support_action'])) {
            $this->handleAdminForm();
        }

        $tickets = $this->ticketService->getTickets();
        $currentTicket = null;
        if (!empty($_GET['ticket_id'])) {
            $currentTicket = $this->ticketService->getTicket(absint($_GET['ticket_id']));
        }

        include FLUENT_SUITE_PATH . 'views/support.php';
    }

    protected function handleAdminForm(): void
    {
        check_admin_referer('fluent_suite_support_action');

        $action = sanitize_text_field(wp_unslash($_POST['fluent_suite_support_action'] ?? ''));

        if ($action === 'reply') {
            $ticketId = absint($_POST['ticket_id'] ?? 0);
            $message  = wp_kses_post(wp_unslash($_POST['reply_message'] ?? ''));
            if ($ticketId && $message) {
                $this->ticketService->addReply($ticketId, [
                    'author'      => wp_get_current_user()->display_name ?: 'Agent',
                    'author_type' => 'agent',
                    'message'     => $message
                ]);
            }
        }

        if ($action === 'status') {
            $ticketId = absint($_POST['ticket_id'] ?? 0);
            $status   = sanitize_text_field(wp_unslash($_POST['ticket_status'] ?? 'open'));
            if ($ticketId) {
                $this->ticketService->updateStatus($ticketId, $status);
            }
        }

        wp_safe_redirect(add_query_arg([
            'page'      => 'fluent-suite-support',
            'ticket_id' => absint($_POST['ticket_id'] ?? 0),
            'updated'   => 'yes'
        ], admin_url('admin.php')));
        exit;
    }

    public function maybeHandleAutoReply(array $ticket, array $context): void
    {
        if (($context['author_type'] ?? '') !== 'customer') {
            return;
        }

        $message = $context['message'] ?? '';
        if (!$message) {
            return;
        }

        $reply = $this->openAiResponder->maybeGenerateReply($ticket, $message);
        if (!$reply) {
            return;
        }

        $this->ticketService->addReply((int) $ticket['id'], [
            'author'      => __('Virtual Assistant', 'fluent-suite'),
            'author_type' => 'assistant',
            'message'     => $reply
        ]);
    }
}
