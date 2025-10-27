<?php

namespace FluentSupport\App\Services\Tickets\Importer;

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;

class FreshDeskTickets extends BaseImporter
{
    protected $handler = 'freshdesk';
    public $accessToken;
    public $mailbox_id;
    private $domain;
    protected $limit = 10;
    private $hasMore;
    private $currentPage;
    private $totalTickets;
    private $originId;
    private $responseCount;
    protected $ticketUpdatedSince;

    public function stats()
    {
        $metadata = Meta::where('object_type', '_fs_freshdesk_migration_info')->first();
        $previouslyImported = Helper::safeUnserialize($metadata->value ?? []);
        $previouslyImported['domain'] = $metadata->key ?? '';
        return [
            'name' => esc_html('Freshdesk'),
            'handler' => $this->handler,
            'type' => 'sass',
            'last_migrated' => get_option('_fs_migrate_freshdesk'),
            'previously_imported' => $previouslyImported,
        ];
    }

    public function doMigration($page, $handler)
    {
        $this->currentPage = $page;
        $this->handler = $handler;
        $tickets = $this->ticketsWithReply();

        if (is_wp_error($tickets)) {
            throw new \Exception(esc_html($tickets->get_error_message()));
        }

        $results = $this->migrateTickets($tickets);

        $completedNow = isset($results['inserts']) ? count($results['inserts']) : 0;

        $response = [
            'handler' => $this->handler,
            'insert_ids' => $results['inserts'],
            'skips' => count($results['skips']),
            'has_more' => $this->hasMore,
            'completed' => $completedNow,
            'imported_page' => $page,
            'total_pages' => null,
            'next_page' => $page + 1,
            'total_tickets' => null,
            'remaining' => 0,
        ];

        if ($this->hasMore) {
            $previousValue = Meta::where('object_type', '_fs_freshdesk_migration_info')->first();
            if ($previousValue) {
                Meta::where('object_type', '_fs_freshdesk_migration_info')->update([
                    'key' => $this->domain,
                    'value' => maybe_serialize($response)
                ]);
            } else {
                Meta::insert([
                    'object_type' => '_fs_freshdesk_migration_info',
                    'key' => $this->domain,
                    'value' => maybe_serialize($response)
                ]);
            }
            return $response;
        }

        Meta::where('object_type', '_fs_freshdesk_migration_info')->delete();
        $response['message'] = __('All tickets have been imported successfully', 'fluent-support');
        update_option('_fs_migrate_freshdesk', current_time('mysql'), 'no');
        return $response;
    }

    private function ticketsWithReply()
    {
        try {
            $url = "{$this->domain}/api/v2/tickets?updated_since={$this->ticketUpdatedSince}&per_page={$this->limit}&page={$this->currentPage}&include=stats,requester,description";
            $tickets = $this->makeRequest($url);

            if (is_wp_error($tickets)) {
                return $tickets;
            }

            $formattedTickets = [];
            if (empty($tickets)) {
                $this->hasMore = false;
                return [];
            }

            $this->hasMore = true;

            foreach ($tickets as $ticket) {
                $singleTicketUrl = "{$this->domain}/api/v2/tickets/{$ticket->id}?include=conversations,requester,stats";
                $singleTicket = $this->makeRequest($singleTicketUrl);

                if (is_wp_error($singleTicket)) {
                    return $singleTicket;
                }

                if (!$singleTicket) {
                    continue;
                }
                $this->originId = $singleTicket->id;
                $attachments = [];

                if ($singleTicket->attachments) {
                    $attachments = $this->getAttachments($singleTicket->attachments);

                    if (is_wp_error($attachments)) {
                        return $attachments;
                    }
                }

                $lastCustomerResponse = $singleTicket->stats->requester_responded_at ?? $singleTicket->stats->status_updated_at;
                $lastAgentResponse = $singleTicket->stats->agent_responded_at ? date('Y-m-d h:i:s', strtotime($singleTicket->stats->agent_responded_at)) : NULL;

                $formattedTickets[] = [
                    'title' => sanitize_text_field($ticket->subject),
                    'content' => wp_kses_post($ticket->description),
                    'origin_id' => intval($ticket->id),
                    'source' => sanitize_text_field($this->handler),
                    'customer' => $this->fetchPerson($singleTicket->requester),
                    'replies' => $this->getReplies($singleTicket->conversations, $singleTicket->requester),
                    'response_count' => $this->responseCount,
                    'status' => $this->getStatus($ticket->status),
                    'client_priority' => $this->getPriority($ticket->priority),
                    'priority' => $this->getPriority($ticket->priority),
                    'created_at' => date('Y-m-d h:i:s', strtotime($ticket->created_at)),
                    'updated_at' => date('Y-m-d h:i:s', strtotime($ticket->updated_at)),
                    'last_customer_response' => date('Y-m-d h:i:s', strtotime($lastCustomerResponse)),
                    'last_agent_response' => $lastAgentResponse,
                    'attachments' => $attachments
                ];
            }

            return $formattedTickets;

        } catch (\Exception $e) {
            return new \WP_Error('freshdesk_api_error', $e->getMessage());
        }
    }

    private function getReplies($replies, $requester)
    {
        if (!$requester || !$replies) {
            return [];
        }

        $formattedReplies = [];
        $user = $this->fetchPerson($requester);

        // Check if fetchPerson returned an error
        if (is_wp_error($user)) {
            return $user; // Return the WP_Error object
        }

        $this->setResponseCount(count($replies));
        foreach ($replies as $reply) {
            $ticketReply = [
                'content' => wp_kses_post($reply->body),
                'conversation_type' => ($reply->source == 2) ? 'note' : 'response',
                'created_at' => date('Y-m-d h:i:s', strtotime($reply->created_at)),
                'updated_at' => date('Y-m-d h:i:s', strtotime($reply->updated_at)),
                'is_customer_reply' => ($requester->id === $reply->user_id),
            ];

            if ($requester->id == $reply->user_id) {
                $ticketReply['user'] = $user;
            } else {
                $ticketReply['user'] = $this->fetchPerson($reply->user_id, 'agent', $reply->support_email);

                if (is_wp_error($ticketReply['user'])) {
                    return $ticketReply['user'];
                }
            }

            if (count($reply->attachments)) {
                $ticketReply['attachments'] = $this->getAttachments($reply->attachments);

                if (is_wp_error($ticketReply['attachments'])) {
                    return $ticketReply['attachments'];
                }
            }
            $formattedReplies[] = $ticketReply;
        }
        return $formattedReplies;
    }

    private function makeRequest($url)
    {
        $token = base64_encode($this->accessToken . ':X');
        $request = wp_remote_get($url, [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json'
            ],
            'timeout' => 600
        ]);

        if (is_wp_error($request)) {
            return new \WP_Error('api_request_error', $request->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($request);

        if ($response_code == 429) {
            $retry_after = wp_remote_retrieve_header($request, 'retry-after');
            if ($retry_after) {
                $minutes = floor($retry_after / 60);
                $seconds = $retry_after % 60;
                $error_message = "Rate limit exceeded. Please retry after {$minutes} minutes and {$seconds} seconds.";
            } else {
                $error_message = "Rate limit exceeded. Please try again later.";
            }
            return new \WP_Error($response_code, $error_message);
        }

        if ($response_code >= 400) {
            $body = wp_remote_retrieve_body($request);
            $decoded_body = json_decode($body);
            $error_message = isset($decoded_body->message) ? $decoded_body->message : "API request failed with status code: {$response_code}";
            return new \WP_Error($response_code, $error_message);
        }

        $response = json_decode(wp_remote_retrieve_body($request));

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('json_parse_error', 'Failed to parse API response: ' . json_last_error_msg());
        }

        return $response;
    }

    private function fetchPerson($personData, $type = 'customer', $email = null)
    {
        if ('agent' == $type) {
            try {
                $url = "{$this->domain}/api/v2/agents/{$personData}";
                $agent = $this->makeRequest($url);

                if (is_wp_error($agent)) {
                    return $agent;
                }

                if (!isset($agent->contact)) {
                    $personArray = [
                        'first_name' => 'Freshdesk anonymous agent',
                        'last_name' => '',
                        'email' => $email,
                        'person_type' => 'agent',
                    ];
                    return Common::updateOrCreatePerson($personArray);
                }
                $personArray = Common::formatPersonData($agent->contact, $type);

                return Common::updateOrCreatePerson($personArray);
            } catch (\Exception $e) {
                return new \WP_Error('person_fetch_error', $e->getMessage());
            }
        } else {
            try {
                $personArray = Common::formatPersonData($personData, $type);
                return Common::updateOrCreatePerson($personArray);
            } catch (\Exception $e) {
                return new \WP_Error('person_format_error', $e->getMessage());
            }
        }
    }

    private function getAttachments($attachments)
    {
        try {
            $wpUploadDir = wp_upload_dir();
            $baseDir = $wpUploadDir['basedir'] . '/fluent-support/freshdesk-ticket-' . $this->originId . '/';

            $formattedAttachments = [];
            foreach ($attachments as $attachment) {
                $filePath = Common::downloadFile($attachment->attachment_url, $baseDir, $attachment->name);

                // Check if downloadFile returned an error
                if (is_wp_error($filePath)) {
                    return $filePath; // Return the WP_Error object
                }

                $fileUrl = $wpUploadDir['baseurl'] . '/fluent-support/freshdesk-ticket-' . $this->originId . '/' . $attachment->name;
                $formattedAttachments[] = [
                    'full_url' => $fileUrl,
                    'title' => $attachment->name,
                    'file_path' => $filePath,
                    'driver' => 'local',
                    'status' => 'active',
                    'file_type' => $attachment->content_type
                ];
            }

            return $formattedAttachments;
        } catch (\Exception $e) {
            return new \WP_Error('attachment_error', $e->getMessage());
        }
    }

    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    private function setResponseCount($count)
    {
        $this->responseCount = $count;
    }

    private function getStatus($statusCode)
    {
        switch ($statusCode) {
            case 2:
                return 'active';
            case 3:
                return 'pending';
            case 4 || 5:
                return 'closed';
            default:
                return 'new';
        }
    }

    private function getPriority($priorityCode)
    {
        switch ($priorityCode) {
            case 1:
                return 'normal';
            case 2:
                return 'medium';
            case 3 || 4:
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
