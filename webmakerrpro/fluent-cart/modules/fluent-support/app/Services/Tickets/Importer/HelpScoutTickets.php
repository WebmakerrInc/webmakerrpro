<?php
namespace FluentSupport\App\Services\Tickets\Importer;
use FluentSupport\App\Models\Person;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;

class HelpScoutTickets extends BaseImporter
{
    protected $handler = 'helpscout';
    public $accessToken;
    public $mailbox_id;
    private $apiUrl = 'https://api.helpscout.net/v2/';
    protected $limit = 25;
    private $totalPage;
    private $currentPage;
    private $totalTickets;
    private $originId;

    public function stats()
    {
        $metadata = Meta::where('object_type', '_fs_helpscout_migration_info')->first();
        $previouslyImported = Helper::safeUnserialize($metadata->value ?? []);
        if (isset($metadata->key)) {
            $previouslyImported['mailbox_id'] = $metadata->key;
        }

        return [
            'name'          => esc_html('Help Scout'),
            'handler'       => $this->handler,
            'type'          => 'sass',
            'last_migrated' => get_option('_fs_migrate_helpscout'),
            'previously_imported' => $previouslyImported,
        ];
    }

    public function doMigration($page, $handler)
    {
        $this->handler = $handler;

        $tickets = $this->getTickets($page);
        $results = $this->migrateTickets($tickets);

        $hasMore = $this->currentPage < $this->totalPage;
        $completedNow = isset($results['inserts']) ? count($results['inserts']) : 0;
        $completedTickets = $completedNow + (($this->currentPage - 1) * 25);
        $remainingTickets = $this->totalTickets - $completedTickets;
        $completed = intval(($completedTickets / $this->totalTickets) * 100);

        $response = [
            'handler'       => $this->handler,
            'insert_ids'    => $results['inserts'],
            'skips'         => count($results['skips']),
            'has_more'      => $hasMore,
            'completed'     => $completed,
            'imported_page' => $page,
            'total_pages'   => $this->totalPage,
            'next_page'     => $page + 1,
            'total_tickets' => $this->totalTickets,
            'remaining'     => $remainingTickets,
        ];

        if ($hasMore) {
            $previousValue = Meta::where('object_type', '_fs_helpscout_migration_info')->first();
            if ($previousValue) {
                Meta::where('object_type', '_fs_helpscout_migration_info')->update([
                    'key' => $this->mailbox_id,
                    'value' => maybe_serialize($response)
                ]);
            } else {
                Meta::insert([
                    'object_type' => '_fs_helpscout_migration_info',
                    'key'         => $this->mailbox_id,
                    'value'       => maybe_serialize($response)
                ]);
            }
            return $response;
        }

        Meta::where('object_type', '_fs_helpscout_migration_info')->delete();
        $response['message'] = __('All tickets have been imported successfully', 'fluent-support');
        update_option('_fs_migrate_helpscout', current_time('mysql'), 'no');
        return $response;
    }

    // This method will get all tickets from Help Scout
    private function getTickets($page)
    {
        $request = wp_remote_get(
            $this->apiUrl . 'conversations?mailbox=' . $this->mailbox_id . '&page=' . $page . '&status=all',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken
                ],
                'timeout' => 60
            ]
        );

        $response = wp_remote_retrieve_body($request);
        $response = json_decode($response, true);
        $tickets = $response['_embedded']['conversations'];

        $this->totalPage = (int) $response['page']['totalPages'];
        $this->currentPage = (int) $response['page']['number'];
        $this->totalTickets = (int) $response['page']['totalElements'];

        $formattedTickets = [];
        foreach ($tickets as $ticket) {
            $formattedTickets[] = $this->bindOrginalTicketAndReplies($ticket['id']);
        }

        return $formattedTickets;
    }

    // This method will make ticket data with associated replies and attachments
    private function bindOrginalTicketAndReplies($ticketId)
    {
        try{
            $request = wp_remote_get(
                $this->apiUrl. 'conversations/' . $ticketId . '?embed=threads',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken
                    ],
                    'timeout' => 60
                ]
            );

            $response = wp_remote_retrieve_body($request);
            $response = json_decode($response, true);

            $formattedReplies = [];
            $customerData = $response['primaryCustomer'] ?: [];

            if ($replies = $response['_embedded']['threads']) {
                $formattedCustomerData = $this->formatPersonData($customerData);
                $customer = $this->fetchPerson($formattedCustomerData);
                $this->originId = intval($response['id']);

                if (isset(end($replies)['body'])) {
                    $content = wp_kses_post(end($replies)['body']);
                } else {
                    $content = wp_kses_post($response['preview']);
                }


                $waitingSince = isset($response['customerWaitingSince']['time']) ?
                    date('Y-m-d h:i:s', strtotime($response['customerWaitingSince']['time'])) :
                    date('Y-m-d h:i:s', strtotime($response['createdAt']));

                $ticketData = [
                    'origin_id'              => $this->originId,
                    'source'                 => 'helpscout',
                    'title'                  => isset($response['subject']) ? $response['subject'] : $response['preview'],
                    'content'                => $content,
                    'customer'               => $customer,
                    'response_count'         => intval($response['threads']),
                    'created_at'             => date('Y-m-d h:i:s', strtotime($response['createdAt'])),
                    'updated_at'             => date('Y-m-d h:i:s', strtotime($response['createdAt'])),
                    'waiting_since'          => $waitingSince,
                    'last_customer_response' => NULL,
                    'last_agent_response'    => NULL,
                ];

                if($response['threads'] == 0 && $response['status'] == 'active'){
                    $ticketData['status'] = 'closed';
                } elseif ($response['status'] == 'closed') {
                    $ticketData['status'] = 'closed';
                } else {
                    $ticketData['status'] = 'active';
                }

                if ($attachments = end($replies)['_embedded']['attachments']){
                    $ticketData['attachments'] = $this->download($attachments);
                }

                array_pop($replies);
                $replies = array_reverse($replies);

                foreach ($replies as $reply) {
                    $repliedBy = $reply['createdBy'] ?? [];
                    $isLineItem = $reply['type'] == 'lineitem';
                    $user = ($customer->email === ($repliedBy['email'] ?? null)) ? $customer : $this->fetchPerson($this->formatPersonData($repliedBy), 'agent');

                    $formattedReplies[] = [
                        'content' => $isLineItem ? wp_kses_post($reply['action']['text']) : wp_kses_post($reply['body']),
                        'conversation_type' => $isLineItem ? 'internal_info' : 'response',
                        'created_at' => date('Y-m-d H:i:s', strtotime($reply['createdAt'])),
                        'updated_at' => date('Y-m-d H:i:s', strtotime($reply['createdAt'])),
                        'is_customer_reply' => $isLineItem ? false : ($customer->email === $repliedBy['email']),
                        'user' => $user,
                        'attachments' => $this->download($reply['_embedded']['attachments']),
                    ];
                }
                $ticketData['replies'] = $formattedReplies;
            }

            return $ticketData;
        } catch (\Exception $e) {
            throw new \Exception(esc_html($e->getMessage()));
        }

    }


    private function fetchPerson($personData, $type='customer')
    {
        $person = Person::updateOrCreate(
            [
                'email' => $personData['email']
            ],
            [
                'first_name'  => $personData['first_name'],
                'last_name'   => $personData['last_name'],
                'email' => $personData['email'],
                'type'  => $type
            ]
        );

        return $person;
    }

    private function formatPersonData($personData)
    {
        return[
            'first_name' => $personData['first'],
            'last_name'  => $personData['last'],
            'email'      => isset($personData['email']) ? $personData['email'] : null,
        ];
    }

    // This method will download attachments from Help Scout
    // It will create a new folder for each ticket and store attachments in it with their original name
    // Example folder name: helpscout-ticket-{helpscout_ticket_id}
    private function download($attachments)
    {
        $formattedAttachments = [];

        if (count($attachments) < 1) {
            return $formattedAttachments;
        }

        $wpUploadDir = wp_upload_dir();
        $baseDir = $wpUploadDir['basedir'] . '/fluent-support/helpscout-ticket-'. $this->originId . '/';

        foreach ($attachments as $attachment){
            $remoteUrl = $attachment['_links']['web']['href'];
            $fileName = basename($remoteUrl);

            $filePath = $this->downloadFile($remoteUrl, $baseDir);

            if ($filePath) {
                $fileInfo = wp_check_filetype($filePath);
                $fileUrl = $wpUploadDir['baseurl'] . '/fluent-support/helpscout-ticket-'. $this->originId . '/' . $fileName;

                $formattedAttachments[] = [
                    'full_url'  => $fileUrl,
                    'title'     => $fileName,
                    'file_path' => $filePath,
                    'driver'    => 'local',
                    'status'    => 'active',
                    'file_type' => (!empty($fileInfo['type'])) ? $fileInfo['type'] : ''
                ];
            }
        }
        return $formattedAttachments;
    }

    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    public function setMailboxId($mailbox_id)
    {
        $this->mailbox_id = $mailbox_id;
    }

    public function deleteTickets($page)
    {
        return;
    }

    // Download a file from a remote URL and create a new directory for this if not exists
    // Then save the file to the new directory and move this directory to a new given directory
    private function downloadFile($remoteUrl, $baseDir)
    {
        $fileName = basename($remoteUrl);
        $filePath = $baseDir . $fileName;

        if (!file_exists($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        if (!file_exists($filePath)) {
            $file = file_get_contents($remoteUrl);
            file_put_contents($filePath, $file);
        }

        return $filePath;
    }
}
