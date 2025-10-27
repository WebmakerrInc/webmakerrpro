<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\TagPivot;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Models\TicketTag;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;

class TicketHelper
{
    /**
     * getActivity method will return the activity in a ticket by agent
     * @param $ticketId
     * @param false $currentAgentId
     * @return array
     */
    public static function getActivity($ticketId, $currentAgentId = false)
    {
        //Get the ticket meta information
        $meta = Meta::where('object_type', 'ticket_meta')
            ->where('key', '_live_activity')
            ->where('object_id', $ticketId)
            ->first();

        $activities = [];
        if ($meta) {
            $activities = Helper::safeUnserialize($meta->value);
        }

        foreach ($activities as $index => $activity) {
            if ((time() - $activity) > 60) {
                unset($activities[$index]);
            }
        }

        if (!$currentAgentId) {
            return self::getAgentsInfoFromActivities($activities);
        }

        $activities[$currentAgentId] = time();

        if ($meta) {
            $meta->value = maybe_serialize($activities);
            $meta->save();
        } else {
            Meta::insert([
                'object_type' => 'ticket_meta',
                'key' => '_live_activity',
                'object_id' => $ticketId,
                'value' => maybe_serialize($activities)
            ]);
        }

        return self::getAgentsInfoFromActivities($activities);
    }

    public static function getAgentsInfoFromActivities($activities)
    {
        if (!$activities) {
            return [];
        }

        return Agent::select(['email', 'first_name', 'last_name'])
            ->whereIn('id', array_keys($activities))
            ->get();
    }

    /**
     * removeFromActivities method will remove the old live activity by agent and ticket id
     * @param $ticketId
     * @param $agentId
     * @return bool
     */
    public static function removeFromActivities($ticketId, $agentId)
    {
        $meta = Meta::where('object_type', 'ticket_meta')
            ->where('key', '_live_activity')
            ->where('object_id', $ticketId)
            ->first();

        $activities = [];
        if ($meta) {
            $activities = Helper::safeUnserialize($meta->value);
        }

        if (!$activities) {
            return false;
        }

        unset($activities[$agentId]);
        foreach ($activities as $index => $activity) {
            if ((time() - $activity) > 60) {
                unset($activities[$index]);
            }
        }

        $meta->value = maybe_serialize($activities);
        $meta->save();

        return true;
    }

    /**
     * getSuggestedTickets method will return the list of Suggested tickets
     * This method will get the agent id as parameter and fetch ticket information that are waiting to reply or unassigned
     * @param $agentId
     * @param int $limit
     * @return mixed
     */
    public static function getSuggestedTickets($agentId, $limit = 5)
    {
        $restrictedBusinessBoxes = PermissionManager::currentUserRestrictedBusinessBoxes();

        //Get lis of tickets which are waiting for reply
        $tickets = Ticket::where('agent_id', $agentId)
            ->whereNotIn('mailbox_id', $restrictedBusinessBoxes)
            ->where('status', '!=', 'closed')
            ->applyFilters([
                'waiting_for_reply' => 'yes'
            ])
            ->oldest('last_customer_response')
            ->limit($limit)
            ->with('customer')
            ->get();

        //If no ticket is available for reply and logged-in user has permission to manage unassigned tickets
        if($tickets->isEmpty() && PermissionManager::currentUserCan('fst_manage_unassigned_tickets')) {
            //Get the ticket list which status is not closed and agent id is null or 0
            $tickets = Ticket::where('status', '!=', 'closed')
                ->oldest('id')
                ->whereNotIn('mailbox_id', $restrictedBusinessBoxes)
                ->where(function ($q) {
                    $q->whereNull('agent_id');
                    $q->orWhere('agent_id', '0');
                })
                ->with('customer')
                ->limit($limit)
                ->get();
        }


        return $tickets;

    }

    // This method will return all tagged/mentioned/watcher ticket's ids for filtering
    public static function getWatcherTicketIds($agentId)
    {
        $mentioned  = TagPivot::where('source_type', 'ticket_watcher')
            ->where('tag_id', $agentId)
            ->latest('id')
            ->get(['source_id']);

        $ticketIds = array_column($mentioned->toArray(), 'source_id');
        return $ticketIds;
    }

    // This method will return all tagged/mentioned/watcher tickets of logged in agent
    public static function getTicketsToWatch()
    {
        $agent = Helper::getCurrentAgent();
        $restrictedBusinessBoxes = PermissionManager::currentUserRestrictedBusinessBoxes();

        $tickets = Ticket::with('customer')
            ->limit(5)
            ->whereNotIn('mailbox_id', $restrictedBusinessBoxes)
            ->join('fs_tag_pivot', 'fs_tag_pivot.source_id', '=', 'fs_tickets.id')
            ->where('fs_tag_pivot.source_type', '=', 'ticket_watcher')
            ->where('fs_tag_pivot.tag_id', '=', $agent->id)
            ->select(['fs_tickets.*'])
            ->get();

        return $tickets;
    }

    // This method will return all ticket watcher inside a ticket
    public static function getWatchers($watchers)
    {
        $watcherAgents = [];

        foreach ($watchers as $watcher) {
            $watcherAgents[] = Agent::where('id', absint($watcher->tag_id))->select(['id', 'first_name', 'last_name'])->first();
        }

        return $watcherAgents;
    }

    public static function getCarbonCopyCustomerInfo($ticketId){
        $existing = Meta::where('object_type', 'beginning_cc_info')->where('object_id', $ticketId)->first();
        if($existing){
            return Helper::safeUnserialize($existing->value, []);
        }

        return [];
    }

    // This method will count total tickets
    public static function countAllTickets()
    {
        return (new Ticket())->count();
    }
    // This method will count all un-assigned tickets
    public static function countUnassignedTickets()
    {
        return Ticket::whereNull('agent_id')->count();
    }
    // This method will count all closed tickets
    public static function countClosedTickets()
    {
        return Ticket::where('status', 'closed')->count();
    }

    // This method will count all New tickets
    public static function countNewTickets()
    {
        return Ticket::where('status', 'new')->count();
    }

    // This method will count all Active tickets
    public static function countActiveTickets()
    {
        return Ticket::where('status', 'active')->count();
    }

    public static function getTicketEssentials($type = '')
    {
        $agents = Agent::select(['id', 'first_name', 'last_name'])
            ->where('person_type', 'agent')
            ->get()->toArray();

        $data = [
            'client_priorities'          => Helper::customerTicketPriorities(),
            'ticket_statuses_group'      => Helper::ticketStatusGroups(),
            'agents'                     => $agents,
            'changeable_ticket_statuses' => Helper::changeableTicketStatuses(),
            'admin_priorities'           => Helper::adminTicketPriorities(),
            'enable_draft_mode'          => Helper::getBusinessSettings('enable_draft_mode', 'no'),
            'max_file_upload'            => Helper::getBusinessSettings('max_file_upload', 3),
            'support_products'           => Product::select(['id', 'title'])->get(),
            'ticket_tags'                => TicketTag::select(['id', 'title'])->get()->toArray(),
            'mailboxes'                  => MailBox::select(['id', 'name', 'settings'])->get(),
            'ticket_statuses'            => Helper::ticketStatuses(),
        ];

        return $type ? $data[$type] : $data;
    }

    public static function getLabelSearch($agentId)
    {
        $lists = Meta::where('object_id', $agentId)
                        ->where('object_type', 'search_meta')
                        ->where('key', 'label_search')
                        ->first();
        $unserialize = [];
        if ($lists) {
            $unserialize = Helper::safeUnserialize($lists->value);
        }

        return $unserialize;
    }

    public static function saveSearchLabel($agent_id, $searchData, $filterType) {
        
        $agent_id = get_current_user_id();
        $existingRecord = Meta::where('object_id', $agent_id)
                                ->where('object_type', 'search_meta')
                                ->where('key', 'label_search')
                                ->first();

        // If record exists, unserialize the data or initialize an empty array
        $existingData = $existingRecord ? Helper::safeUnserialize($existingRecord->value) ?: [] : [];

        // Check if it's an update or new entry
        $isUpdate = isset($searchData['id']) && array_filter($existingData, function ($item) use ($searchData) {
            return isset($item['id']) && $item['id'] == $searchData['id'];
        });

        // Handle adding or updating
        $updatedData = self::addOrUpdateSearchData($existingData, $searchData);

        // Save the updated data back to the database
        if ($existingRecord) {
            $existingRecord->update([
                'value' => maybe_serialize($updatedData)
            ]);
        } else {
            Meta::insert([
                'object_type' => 'search_meta',
                'key'         => 'label_search',
                'object_id'   => $agent_id,
                'value'       => maybe_serialize($updatedData)
            ]);
        }

        $message = $isUpdate 
        ? __('Your saved search has been updated successfully!', 'fluent-support') 
        : __('Your search has been saved successfully!', 'fluent-support');

        return [
            'message' => $message,
        ];
    }

    private static function addOrUpdateSearchData(array $existingData, array $searchData): array
    {
        if (isset($searchData['id'])) {
            $updated = false;

            // Update the existing record with the matching ID
            foreach ($existingData as &$record) {
                if (isset($record['id']) && $record['id'] == $searchData['id']) {
                    $record = array_merge($record, $searchData);
                    $updated = true;
                    break;
                }
            }

            // If no matching record found, append the new data
            if (!$updated) {
                $existingData[] = $searchData;
            }
        } else {
            // If no ID provided, generate a new one and add the data
            $newId = self::generateNewId($existingData);
            $searchData['id'] = $newId;
            $existingData[] = $searchData;
        }

        return $existingData;
    }

    public static function deleteSavedSearch($search_id) {
        $agent_id = get_current_user_id();
        $existingRecord = Meta::where('object_id', $agent_id)
                                ->where('object_type', 'search_meta')
                                ->where('key', 'label_search')
                                ->first();

        if ($existingRecord) {
            $existingData = Helper::safeUnserialize($existingRecord->value) ?: [];

            $updatedData = array_filter($existingData, function ($item) use ($search_id) {
                return isset($item['id']) && $item['id'] != $search_id;
            });

            if (count($existingData) !== count($updatedData)) {
                $existingRecord->update([
                    'value' => maybe_serialize(array_values($updatedData))
                ]);
                return [
                    'message' => __('Search deleted successfully.', 'fluent-support'),
                ];
            }

            return [
                'message' => __('Search ID not found.', 'fluent-support'),
            ];
        }
    }

    private static function generateNewId(array $existingData): int
    {
        if (empty($existingData)) {
            return 1;
        }

        $maxId = max(array_column($existingData, 'id'));
        return $maxId + 1;
    }
}
