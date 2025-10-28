<?php

namespace FluentCart\Support\Services;

use FluentCart\Support\Models\Inbox;
use FluentCart\Support\Models\Ticket;
use FluentCart\Support\Models\TicketReply;
use FluentCart\Support\SupportException;
use FluentCart\Support\Setup\SupportSetup;
use FluentCart\Framework\Support\Arr;

class TicketService
{
    public function createTicket(array $data): Ticket
    {
        SupportSetup::ensureDefaultInbox();

        $subject = sanitize_text_field(Arr::get($data, 'subject'));
        $message = wp_kses_post(Arr::get($data, 'message'));
        $customerName = sanitize_text_field(Arr::get($data, 'customer_name'));
        $customerEmail = sanitize_email(Arr::get($data, 'customer_email'));

        if (!$subject || !$message || !$customerEmail) {
            throw new SupportException(__('Subject, message and customer email are required.', 'fluent-cart'));
        }

        $inbox = $this->resolveInbox((int) Arr::get($data, 'inbox_id'));

        $now = current_time('mysql', true);
        $ticket = Ticket::create([
            'inbox_id'        => $inbox->id,
            'subject'         => $subject,
            'customer_name'   => $customerName ?: $customerEmail,
            'customer_email'  => $customerEmail,
            'status'          => 'open',
            'priority'        => sanitize_text_field(Arr::get($data, 'priority', 'normal')),
            'created_by'      => get_current_user_id() ?: null,
            'assigned_to'     => Arr::get($data, 'assigned_to'),
            'created_at'      => $now,
            'updated_at'      => $now,
            'last_reply_at'   => $now,
            'last_reply_by'   => get_current_user_id() ?: null,
            'meta'            => [],
        ]);

        $this->createReply($ticket, [
            'message'      => $message,
            'author_name'  => $customerName ?: $customerEmail,
            'author_email' => $customerEmail,
            'user_id'      => get_current_user_id() ?: null,
            'is_internal'  => false,
        ]);

        $ticket->setRelation('inbox', $inbox);
        NotificationService::notifyNewTicket($ticket);

        return $ticket->load(['replies', 'inbox']);
    }

    public function addReply(int $ticketId, array $data): Ticket
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            throw new SupportException(__('Ticket not found.', 'fluent-cart'));
        }

        $message = wp_kses_post(Arr::get($data, 'message'));
        if (!$message) {
            throw new SupportException(__('Reply message is required.', 'fluent-cart'));
        }

        $currentUser = wp_get_current_user();

        $reply = $this->createReply($ticket, [
            'message'      => $message,
            'author_name'  => $currentUser->display_name ?: get_bloginfo('name'),
            'author_email' => $currentUser->user_email,
            'user_id'      => get_current_user_id() ?: null,
            'is_internal'  => (bool) Arr::get($data, 'is_internal'),
        ]);

        $ticket->status = $reply->is_internal ? $ticket->status : 'open';
        $ticket->last_reply_at = $reply->created_at;
        $ticket->last_reply_by = $reply->user_id;
        $ticket->updated_at = $reply->created_at;
        $ticket->save();

        $ticket->loadMissing('inbox');
        NotificationService::notifyTicketReply($ticket, $reply);

        return $ticket->fresh(['replies', 'inbox']);
    }

    public function closeTicket(int $ticketId): Ticket
    {
        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            throw new SupportException(__('Ticket not found.', 'fluent-cart'));
        }

        $ticket->status = 'closed';
        $ticket->closed_at = current_time('mysql', true);
        $ticket->updated_at = $ticket->closed_at;
        $ticket->save();

        $settings = NotificationService::getSettings();
        if (!empty($settings['log_internal'])) {
            NotificationService::logInternal($ticket, __('Ticket closed', 'fluent-cart'));
        }

        return $ticket->fresh(['replies', 'inbox']);
    }

    public function saveInbox(array $data): Inbox
    {
        $title = sanitize_text_field(Arr::get($data, 'title'));
        $email = sanitize_email(Arr::get($data, 'email'));
        $signature = wp_kses_post(Arr::get($data, 'signature'));
        $isDefault = !empty($data['is_default']);

        if (!$title || !$email) {
            throw new SupportException(__('Inbox name and email are required.', 'fluent-cart'));
        }

        $attributes = [
            'title'      => $title,
            'email'      => $email,
            'is_default' => $isDefault ? 1 : 0,
            'settings'   => [
                'signature' => $signature,
            ],
        ];

        $inboxId = (int) Arr::get($data, 'id');
        if ($inboxId) {
            $inbox = Inbox::find($inboxId);
            if (!$inbox) {
                throw new SupportException(__('Inbox not found.', 'fluent-cart'));
            }
            $inbox->fill($attributes);
            $inbox->updated_at = current_time('mysql', true);
            $inbox->save();
        } else {
            $now = current_time('mysql', true);
            $inbox = Inbox::create($attributes + [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($isDefault) {
            Inbox::where('id', '!=', $inbox->id)->update(['is_default' => 0]);
        }

        return $inbox->fresh();
    }

    public function deleteInbox(int $inboxId): void
    {
        $inbox = Inbox::find($inboxId);
        if (!$inbox) {
            throw new SupportException(__('Inbox not found.', 'fluent-cart'));
        }

        if ($inbox->is_default) {
            throw new SupportException(__('Cannot delete the default inbox.', 'fluent-cart'));
        }

        $hasTickets = Ticket::where('inbox_id', $inbox->id)->exists();
        if ($hasTickets) {
            throw new SupportException(__('Cannot delete inbox with existing tickets.', 'fluent-cart'));
        }

        $inbox->delete();
    }

    public function getInboxOptions(): array
    {
        return $this->getAllInboxes()->pluck('title', 'id')->all();
    }

    public function getAllInboxes()
    {
        return Inbox::orderBy('is_default', 'desc')->orderBy('title')->get();
    }

    protected function resolveInbox(?int $inboxId): Inbox
    {
        if ($inboxId) {
            $inbox = Inbox::find($inboxId);
            if ($inbox) {
                return $inbox;
            }
        }

        $inbox = Inbox::where('is_default', 1)->first();
        if ($inbox) {
            return $inbox;
        }

        return SupportSetup::createDefaultInbox();
    }

    protected function createReply(Ticket $ticket, array $data): TicketReply
    {
        $now = current_time('mysql', true);
        $reply = TicketReply::create([
            'ticket_id'    => $ticket->id,
            'user_id'      => Arr::get($data, 'user_id'),
            'author_name'  => sanitize_text_field(Arr::get($data, 'author_name')),
            'author_email' => sanitize_email(Arr::get($data, 'author_email')),
            'message'      => wp_kses_post(Arr::get($data, 'message')),
            'is_internal'  => (bool) Arr::get($data, 'is_internal'),
            'source'       => Arr::get($data, 'source', 'admin'),
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        return $reply;
    }
}
