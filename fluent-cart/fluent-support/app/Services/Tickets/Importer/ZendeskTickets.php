<?php

namespace FluentSupport\App\Services\Tickets\Importer;
class ZendeskTickets extends BaseImporter
{
    protected $handler = 'zendesk';
    public $accessToken;
    public $mailbox_id;
    private $domain;
    private $email;
    protected $limit;
    private $hasMore;
    private $currentPage;
    private $totalTickets;
    private $originId;
    private $responseCount;
    private $totalPage;

    public function stats()
    {
        return [
            'name' => esc_html('Zendesk'),
            'handler' => $this->handler,
            'type' => 'sass',
            'last_migrated' => get_option('_fs_migrate_zendesk')
        ];
    }

    public function doMigration($page, $handler)
    {

        $this->currentPage = $page;
        $this->handler = $handler;
        $tickets = $this->ticketsWithReply();
        $results = $this->migrateTickets($tickets);

        $this->totalPage = $this->totalTickets / $this->limit;
        $this->hasMore = $this->currentPage < $this->totalPage;
        $completedNow = isset($results['inserts']) ? count($results['inserts']) : 0;
        $completedTickets = $completedNow + (($this->currentPage - 1) * $this->limit);
        $remainingTickets = $this->totalTickets - $completedTickets;
        $completed = intval(($completedTickets / $this->totalTickets) * 100);

        $response = [
            'handler' => $this->handler,
            'insert_ids' => $results['inserts'],
            'skips' => count($results['skips']),
            'has_more' => $this->hasMore,
            'completed' => $completed,
            'imported_page' => $page,
            'total_pages' => $this->totalPage,
            'next_page' => $page + 1,
            'total_tickets' => $this->totalTickets,
            'remaining' => $remainingTickets
        ];

        if (!$this->hasMore) {
            $response['message'] = __('All tickets has been imported successfully', 'fluent-support');
            update_option('_fs_migrate_zendesk', current_time('mysql'), 'no');
        }

        return $response;
    }

    private function ticketsWithReply()
    {
        try {
            $this->totalTickets = $this->countTotalTickets();
            $url = "{$this->domain}/api/v2/tickets?per_page={$this->limit}&page={$this->currentPage}";
            $tickets = $this->makeRequest($url);

            $formattedTickets = [];
            if (empty($tickets)) {
                $this->hasMore = false;
                return [];
            }

            $this->hasMore = true;
            foreach ($tickets->tickets as $ticket) {
                $singleTicketUrl = $this->domain . '/api/v2/tickets/' . $ticket->id . '/comments.json?include=attachments,users';
                $singleTicket = $this->makeRequest($singleTicketUrl);
                $this->originId = $ticket->id;
                $ticketAttacments  = [];
                if (!empty($singleTicket->comments[0]->attachments)) {
                    $ticketAttacments = $this->getAttachments($singleTicket->comments[0]->attachments);
                }

                $formattedTickets[] = [
                    'title' => sanitize_text_field($ticket->subject),
                    'content' => wp_kses_post($ticket->description),
                    'origin_id' => intval($ticket->id),
                    'source' => sanitize_text_field($this->handler),
                    'customer' => $this->fetchPerson($ticket->requester_id),
                    'replies' => $this->getReplies($singleTicket),
                    'status' => $this->getStatus($ticket->status),
                    'client_priority' => $this->getPriority($ticket->priority),
                    'priority' => $this->getPriority($ticket->priority),
                    'created_at' => date('Y-m-d h:i:s', strtotime($ticket->created_at)),
                    'updated_at' => date('Y-m-d h:i:s', strtotime($ticket->updated_at)),
                    'last_customer_response' => NULL,
                    'last_agent_response' => NULL,
                    'attachments' => $ticketAttacments
                ];
            }

            return $formattedTickets;

        } catch (\Exception $e) {
            return [];
        }
    }

    private function getReplies($replies)
    {
        unset($replies->comments[0]);
        $formattedReplies = [];
        foreach ($replies->comments as $reply) {
            $ticketReply = [
                'content' => wp_kses_post($reply->body),
                'conversation_type' => 'response',
                'created_at' => date('Y-m-d h:i:s', strtotime($reply->created_at)),
                'updated_at' => isset($reply->updated_at) ? date('Y-m-d h:i:s', strtotime($reply->updated_at)) : NULL,
            ];

            $ticketReply = $this->populatePersonInfo($ticketReply, $reply, $replies->users);

            if (count($reply->attachments)) {
                $ticketReply['attachments'] = $this->getAttachments($reply->attachments);
            }

            $formattedReplies[] = $ticketReply;
        }

        return $formattedReplies;
    }

    private function populatePersonInfo($ticketReply,$reply,$users)
    {
        foreach ($users as $user) {
            if ($user->id !== $reply->author_id) {
                continue;
            }

            $ticketReply['is_customer_reply'] = $user->role === 'end-user';
            $type = $user->role === 'end-user' ? 'user' : 'agent';
            $ticketReply['user'] = Common::formatPersonData($user, $type);
            break;
        }

        return $ticketReply;
    }

    private function makeRequest($url)
    {
        $token = base64_encode($this->email . '/token:' . $this->accessToken);

        $request = wp_remote_get($url, [
            'headers' => [
                'Authorization' => "Basic {$token}",
                'Content-Type' => 'application/json'
            ]
        ]);

        if (is_wp_error($request)) {
            throw new \Exception('Error while making request');
        }

        $response = json_decode(wp_remote_retrieve_body($request));

        return $response;
    }

    private function fetchPerson($requesterId)
    {
        $userUrl = $this->domain . '/api/v2/users/' . $requesterId . '.json';
        $fetchUser = $this->makeRequest($userUrl);

        $user = (object)[
            'name' => $fetchUser->user->name,
            'address' => $fetchUser->user->email
        ];

        $personArray = Common::formatPersonData($user, 'customer');
        return Common::updateOrCreatePerson($personArray);
    }

    private function countTotalTickets()
    {
        $url = "{$this->domain}/api/v2/tickets/count.json";
        $count = $this->makeRequest($url);
        return $count->count->value;
    }

    private function getAttachments($attachments)
    {
        $wpUploadDir = wp_upload_dir();
        $baseDir = $wpUploadDir['basedir'] . '/fluent-support/zendesk-ticket-' . $this->originId . '/';

        $formattedAttachments = [];
        foreach ($attachments as $attachment) {
            $filePath = Common::downloadFile($attachment->content_url, $baseDir, $attachment->file_name);
            $fileUrl = $wpUploadDir['baseurl'] . '/fluent-support/zendesk-ticket-' . $this->originId . '/' . $attachment->file_name;
            $formattedAttachments[] = [
                'full_url' => $fileUrl,
                'title' => $attachment->file_name,
                'file_path' => $filePath,
                'driver' => 'local',
                'status' => 'active',
                'file_type' => $attachment->content_type
            ];
        }

        return $formattedAttachments;
    }

    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    private function setResponseCount($count)
    {
        $this->responseCount = $count;
    }

    private function getStatus($status)
    {
        switch ($status) {
            case 'open':
                return 'active';
            case 'pending':
                return 'waiting';
            case 'solved':
                return 'closed';
            default:
                return 'active';
        }

    }

    private function getPriority($priority)
    {
        switch ($priority) {
            case 'low':
            case 'normal':
                return 'normal';
            case 'high':
                return 'medium';
            case 'urgent':
                return 'critical';
            default:
                return 'normal';
        }
    }

    public function deleteTickets($page)
    {
        return;
    }
}
