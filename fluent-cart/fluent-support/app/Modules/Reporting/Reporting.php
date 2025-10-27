<?php

namespace FluentSupport\App\Modules\Reporting;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Person;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Database\Orm\Builder;
use FluentSupport\Framework\Support\Arr;
use FluentSupport\Framework\Support\DateTime;

/**
 * Reporting class is responsible for getting data related to report
 * @package FluentSupport\App\Modules\Reporting
 *
 * @version 1.0.0
 */
class Reporting
{
    use ReportingHelperTrait;

    /**
     * getTicketsGrowth will generate tickets statistics and return
     * @param false $from
     * @param false $to
     * @param array $filters
     * @return array
     */
    public function getTicketsGrowth($from = false, $to = false, $filters = [])
    {
        //Generate report period
        $period = $this->makeDatePeriod(
            $from = $this->makeFromDate($from),//Date from
            $to = $this->makeToDate($to),//Date to
            $frequency = $this->getFrequency($from, $to)// frequency P1D, P1W, P1M
        );

        //Get group by and order by i.e date,week, month
        list($groupBy, $orderBy) = $this->getGroupAndOrder($frequency);

        //get all tickets statistics within the date range
        $query = $this->db()->table('fs_tickets')
            ->select($this->prepareSelect($frequency))
            ->whereBetween('created_at', $this->prepareBetween($frequency, $from, $to))
            ->groupBy($groupBy)
            ->oldest($orderBy);

        //If filter by product or agent or status selected
        if ($filters) {
            if (!empty($filters['statuses'])) {
                $query->whereIn('status', $filters['statuses']);
            }

            if (!empty($filters['product_id'])) {
                $query->where('product_id', $filters['product_id']);
            }

            if (!empty($filters['agent_id'])) {
                $query->where('agent_id', $filters['agent_id']);
            }

            if (!empty($filters['mailbox_id'])) {
                $query->where('mailbox_id', $filters['mailbox_id']);
            }
        }

        $items = $query->get();

        return $this->getResult($period, $items);
    }

    /**
     * getTicketResolveGrowth method will get the statistics for resolved/closed tickets
     * @param false $from
     * @param false $to
     * @param array $filters
     * @return array
     */
    public function getTicketResolveGrowth($from = false, $to = false, $filters = [], $type = '')
    {
        $period = $this->makeDatePeriod(
            $from = $this->makeFromDate($from),//Date from
            $to = $this->makeToDate($to),//date to
            $frequency = $this->getFrequency($from, $to)// frequency P1D, P1W, P1M
        );

        list($groupBy, $orderBy) = $this->getGroupAndOrder($frequency);//Get group by and order by i.e date,week, month

        $filterColumn = (!empty($type)) ? $type.'_id' : 'id';

        //get the closed ticket statistics within the date range
        $query = $this->db()->table('fs_tickets')
            ->select($this->prepareSelect($frequency, 'resolved_at'))
            ->whereBetween('resolved_at', $this->prepareBetween($frequency, $from, $to))
            ->where('status', 'closed')
            ->where($filterColumn, '>', 0)
            ->groupBy($groupBy)
            ->oldest($orderBy);

        //If filter by product or agent is selected
        if ($filters) {
            if (!empty($filters['product_id'])) {
                $query->where('product_id', $filters['product_id']);
            }

            if (!empty($filters['agent_id'])) {
                $query->where('agent_id', $filters['agent_id']);
            }

            if (!empty($filters['mailbox_id'])) {
                $query->where('mailbox_id', $filters['mailbox_id']);
            }
        }

        $items = $query->get();

        return $this->getResult($period, $items);
    }

    /**
     * getResponseGrowth method will generate the statistics for response
     * @param false $from
     * @param false $to
     * @param array $filters
     * @return array
     */
    public function getResponseGrowth($from = false, $to = false, $filters = [])
    {
        $period = $this->makeDatePeriod(
            $from = $this->makeFromDate($from),
            $to = $this->makeToDate($to),
            $frequency = $this->getFrequency($from, $to)
        );

        list($groupBy, $orderBy) = $this->getGroupAndOrder($frequency);

        $query = Conversation::query()
            ->select($this->prepareSelect($frequency))
            ->whereBetween('created_at', $this->prepareBetween($frequency, $from, $to))
            ->where('conversation_type', 'response')
            ->whereHas('person', function ($q) {
                $q->where('person_type', 'agent');
            })
            ->groupBy($groupBy)
            ->oldest($orderBy);

        if ($filters) {
            if (!empty($filters['person_id'])) {
                $query->where('person_id', $filters['person_id']);
            }

            if (!empty($filters['product_id'])) {
                $query->where('product_id', $filters['product_id']);
            }
        }

        $items = $query->get();

        return $this->getResult($period, $items);
    }

    public function getResponseGrowthChart($from = false, $to = false, $filters = [], $type = ''): array
    {
        $period = $this->makeDatePeriod(
            $from = $this->makeFromDate($from),
            $to = $this->makeToDate($to),
            $frequency = $this->getFrequency($from, $to)
        );

        list($groupBy, $orderBy) = $this->getGroupAndOrder($frequency);

        $filterColumn = $type."_id";

        $query = $this->db()->table('fs_tickets')
              ->select($this->prepareSelect($frequency,'created_at','response_count'))
              ->whereBetween('created_at', $this->prepareBetween($frequency, $from, $to))
              ->havingRaw('COUNT(response_count)> 0')
              ->where($filterColumn, '>', 0)
              ->groupBy($groupBy)
              ->oldest($orderBy);

        if ($filters) {
            if (!empty($filters['product_id'])) {
                $query->where('product_id', $filters['product_id']);
            }

            if (!empty($filters['mailbox_id'])) {
                $query->where('mailbox_id', $filters['mailbox_id']);
            }
        }

        $items = $query->get();

        return $this->getResult($period, $items);
    }

    /**
     * agentSummary method will prepare ticket summary with responses by agent
     * @param false $from
     * @param false $to
     * @param false $agent
     * @return mixed
     */
    public function agentSummary($from = false, $to = false, $agent = false)
    {
        if(!$from) {
            $from = current_time('Y-m-d');
        }

        if(!$to) {
            $to = current_time('Y-m-d');
        }

        $from .= ' 00:00:00';
        $to .= ' 23:59:59';
        $reports = [];

        //Get tickets statistics that are closed
        $resolves = $this->db()->table('fs_tickets')
            ->select([
                $this->db()->raw('COUNT(id) AS count'),
                'agent_id',
            ])
            ->groupBy('agent_id')
            ->where('status', 'closed')
            ->whereBetween('resolved_at', [$from, $to])
            ->get();

        $reports = $this->pushReportData('closed', $resolves, $reports, 'agent_id');

        //get statistics for all except closed ticket
        $openTickets = $this->db()->table('fs_tickets')
            ->select([
                $this->db()->raw('COUNT(id) AS count'),
                'agent_id'
            ])
            ->groupBy('agent_id')
            ->where('status', '!=', 'closed')
            ->get();

        $reports = $this->pushReportData('opens', $openTickets, $reports, 'agent_id');
        //Get response by agent
        $responses = Conversation::select([
            $this->db()->raw('COUNT(id) AS count'),
            $this->db()->raw('person_id as agent_id'),
            $this->db()->raw('created_at')
        ])
            ->whereHas('person', function ($q) {
                $q->where('person_type', '=', 'agent');
            })
            ->whereBetween('created_at', [$from, $to])
            ->where('conversation_type', 'response')
            ->groupBy('agent_id')
            ->get();

        $reports = $this->pushReportData('responses', $responses, $reports, 'agent_id');
        //Get interactions/responses by individual agents
        foreach ($responses as $response) {
            $reports[$response->agent_id]['interactions'] = Conversation::where('person_id', $response->agent_id)
                ->where('conversation_type', 'response')
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('ticket_id')
                ->get()
                ->count();
        }

        $agentIds = array_keys($reports);

        if ($agent) {
            $agentIds = array_map('intval', explode(',', $agent));
        }

        // get agent feedback statistics
        $agentFeedbackRatingEnabled = Helper::getBusinessSettings('agent_feedback_rating') === 'yes';
        if (defined('FLUENTSUPPORTPRO_PLUGIN_VERSION') && $agentFeedbackRatingEnabled) {
            $agentConversations = Conversation::select([
                $this->db()->raw('person_id as agent_id'),
                $this->db()->raw('GROUP_CONCAT(id) as conversation_ids')
            ])
                ->whereIn('person_id', $agentIds)
                ->whereHas('person', function ($q) {
                    $q->where('person_type', '=', 'agent');
                })
                ->where('conversation_type', 'response')
                ->groupBy('agent_id')
                ->get();

            foreach ($agentConversations as $conversation) {
                $conversationIds = array_map('intval', explode(',', $conversation->conversation_ids));

                $feedbackMeta = Meta::whereIn('object_id', $conversationIds)
                    ->where('key', 'agent_feedback_ratings')
                    ->whereBetween('created_at', [$from, $to])
                    ->get();

                $likeCount = 0;
                $dislikeCount = 0;

                foreach ($feedbackMeta as $feedback) {
                    $feedbackStatus = $feedback->value;

                    if ($feedbackStatus === 'like') {
                        $likeCount++;
                    } elseif ($feedbackStatus === 'dislike') {
                        $dislikeCount++;
                    }
                }

                $agentId = $conversation->agent_id;
                $reports[$agentId]['likes'] = $likeCount;
                $reports[$agentId]['dislikes'] = $dislikeCount;
            }
        }

        $agents = Agent::select(['id', 'first_name', 'last_name'])
            ->whereIn('id', $agentIds)
            ->get();

        foreach ($agents as $agent) {
            $report = NULL;
            if(isset($reports[$agent->id])) {
                $reportFields = [
                    'interactions' => 0,
                    'responses' => 0,
                    'opens' => 0,
                    'closed' => 0,
                    'waiting_tickets' => 0,
                ];

                $additionalFields = defined('FLUENTSUPPORTPRO_PLUGIN_VERSION') && $agentFeedbackRatingEnabled ? ['likes' => 0, 'dislikes' => 0] : [];
                $reportFields = $reportFields + $additionalFields;

                $report = wp_parse_args($reports[$agent->id], $reportFields);
            }
            $agent->stats = $report;
            $agent->active_stat = $this->getActiveStatByAgent($agent->id);
        }
        return $agents;
    }

    public function getSummary($type, $from = null, $to = null)
    {
        global $wpdb;
        $tablePrefix = $wpdb->prefix;

        if (!$from) {
            $from = current_time('Y-m-d');
        }

        if (!$to) {
            $to = current_time('Y-m-d');
        }

        $from .= ' 00:00:00';
        $to .= ' 23:59:59';
        $reports = [];

        $groupByField = $type == 'product' ? 'product_id' : 'mailbox_id';

        $resolves = $this->db()->table('fs_tickets')
                         ->select([
                             $this->db()->raw('COUNT(id) AS count'),
                             $groupByField,
                         ])
                         ->groupBy($groupByField)
                         ->where('status', 'closed')
                         ->whereBetween('resolved_at', [$from, $to])
                         ->get();

        $reports = $this->pushReportData('closed', $resolves, $reports, $groupByField);

        $openTickets = $this->db()->table('fs_tickets')
                            ->select([
                                $this->db()->raw('COUNT(id) AS count'),
                                $groupByField
                            ])
                            ->groupBy($groupByField)
                            ->where('status', '!=', 'closed')
                            ->whereBetween('created_at', [$from, $to])
                            ->get();

        $reports = $this->pushReportData('opens', $openTickets, $reports, $groupByField);

        $responses = $this->db()->table('fs_conversations')
            ->join('fs_tickets', 'fs_tickets.id', '=', 'fs_conversations.ticket_id')
            ->select([
                $this->db()->raw('COUNT(' . $tablePrefix . 'fs_conversations.id) AS count'),
                'fs_tickets.' . $groupByField,
            ])
            ->groupBy('fs_tickets.' . $groupByField)
            ->whereBetween('fs_conversations.created_at', [$from, $to])
            ->get();

        $reports = $this->pushReportData('responses', $responses, $reports, $groupByField);

        $ticketIds = $this->db()->table('fs_tickets')
                          ->select([
                              $groupByField,
                              $this->db()->raw('GROUP_CONCAT(id) AS ticket_ids'),
                          ])
                          ->where($groupByField, '!=', 0)
                          ->groupBy($groupByField)
                          ->get();

        $result = [];
        foreach ($ticketIds as $item) {
            $result[$item->{$groupByField}] = explode(',', $item->ticket_ids);
        }

        foreach ($result as $id => $ticketIds) {
            $interactions = Conversation::whereIn('ticket_id', $ticketIds)
                ->where('conversation_type', 'response')
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('ticket_id')
                ->get()
                ->count();

            $reports[$id]['interactions'] = $interactions;
        }

        $ids = array_keys($reports);

        $types = [
            'product' => [
                'model' => Product::class,
                'fields' => ['id', 'title'],
            ],
            'mailbox' => [
                'model' => MailBox::class,
                'fields' => ['id', 'name'],
            ],
        ];

        $model = $types[$type]['model'];
        $fields = $types[$type]['fields'];

        $items = $model::select($fields)
                       ->whereIn('id', $ids)
                       ->get();

        foreach ($items as $item) {
            $report = isset($reports[$item->id]) ? $reports[$item->id] : [];

            $report = wp_parse_args($report, [
                'responses' => 0,
                'opens' => 0,
                'closed' => 0,
                'interactions' => 0
            ]);
            $item->stats = $report;
            $item->active_stat = '';
        }

        return $items;
    }

    /**
     * pushReportData method will format the ticket summary report
     * @param $type
     * @param $tickets
     * @param $reports
     * @param $groupByField
     * @return array
     */
    private function pushReportData($type, $tickets, $reports, $groupByField): array
    {
        foreach ($tickets as $ticket) {
            $groupKey = $ticket->{$groupByField};

            if (!$groupKey) {
                continue;
            }

            if (!isset($reports[$groupKey])) {
                $reports[$groupKey] = [];
            }

            $reports[$groupKey][$type] = $ticket->count;
        }

        return $reports;
    }

    /**
     * getActiveStats method will return the statistics for active tickets
     * This method will get the list of open tickets calculate the wait times and return results
     * @return array|false
     */
    public function getActiveStats()
    {
        // We will calculate the wait times for open waiting tickets
        $waitStat = Ticket::waitingOnly()
            ->where('status', '!=', 'closed')
            ->whereNotNull('waiting_since')
            ->select([
                $this->db()->raw('avg(UNIX_TIMESTAMP(waiting_since)) as avg_waiting'),
                $this->db()->raw('MIN(UNIX_TIMESTAMP(waiting_since)) as max_waiting'),
                $this->db()->raw('COUNT(*) as total_tickets')
            ])
            ->first();

        if(!$waitStat) {
            return false;
        }

        $waitStat->avg_waiting = intval($waitStat->avg_waiting);
        if($waitStat->avg_waiting > 0) {
            $waitSeconds = time() -  $waitStat->avg_waiting;
            if( $waitSeconds < 172800 && $waitSeconds > 7200) {
                $avgWait = ceil($waitSeconds / 3600) . ' hours';
            } else {
                $avgWait = human_time_diff($waitStat->avg_waiting, time());
            }
        } else {
            $avgWait = 0;
        }

        return [
            'average_waiting' => $avgWait,
            'max_waiting' => (intval($waitStat->max_waiting)) ? human_time_diff(intval($waitStat->max_waiting), time()) : 0,
            'waiting_tickets' => $waitStat->total_tickets
        ];
    }

    /**
     * getActiveStatByAgent method will return the statistics of active tickets for an agent
     * This method will get  agent id as parameter, fetch the list of open tickets by agent id, calculate the wait times and return results
     * @param $agentId
     * @return array|false
     */
    public function getActiveStatByAgent($agentId)
    {
        $waitStat = Ticket::waitingOnly()
            ->where('status', '!=', 'closed')
            ->whereNotNull('waiting_since')
            ->where('agent_id', $agentId)
            ->select([
                $this->db()->raw('avg(UNIX_TIMESTAMP(waiting_since)) as avg_waiting'),
                $this->db()->raw('MIN(UNIX_TIMESTAMP(waiting_since)) as max_waiting'),
                $this->db()->raw('COUNT(*) as total_tickets')
            ])
            ->first();

        if(!$waitStat) {
            return false;
        }

        $waitStat->avg_waiting = intval($waitStat->avg_waiting);
        if($waitStat->avg_waiting > 0) {
            $waitSeconds = time() -  $waitStat->avg_waiting;
            if( $waitSeconds < 172800 && $waitSeconds > 7200) {
                $avgWait = ceil($waitSeconds / 3600) . ' hours';
            } else {
                $avgWait = human_time_diff($waitStat->avg_waiting, time());
            }
        } else {
            $avgWait = 0;
        }

        return [
            'average_waiting' => $avgWait,
            'max_waiting' => (intval($waitStat->max_waiting)) ? human_time_diff(intval($waitStat->max_waiting), time()) : 0,
            'waiting_tickets' => $waitStat->total_tickets
        ];
    }

    public function getQueryResults($from, $to, $filter)
    {
        switch ($filter['report_type']) {
            case 'ticket':
                return $this->getTicketStats($from, $to);
            case 'agent_response':
                return $this->getResponseStats($from, $to, 'agent', $filter['agent_id'] ?? null);
            case 'customer_response':
                return $this->getResponseStats($from, $to, 'customer');
            default:
                return [];
        }
    }

    public function getTicketStats($from, $to)
    {
        $whereClause = '';
        if ($from && $to) {
            // Ensure the dates are in the correct format (Y-m-d H:i:s)
            $start_date = date('Y-m-d 00:00:00', strtotime($from));
            $end_date = date('Y-m-d 23:59:59', strtotime($to));
            $whereClause = "WHERE created_at BETWEEN '$start_date' AND '$end_date'";
        }

        global $wpdb;

        // SQL query to count tickets by day of week and hour within the specified date range
        $query = "SELECT DAYNAME(created_at) AS weekday, HOUR(created_at) AS hour, COUNT(*) AS count
        FROM {$wpdb->prefix}fs_tickets
        {$whereClause}
        GROUP BY DAYNAME(created_at), HOUR(created_at)
        ORDER BY FIELD(DAYNAME(created_at), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), HOUR(created_at)";

        // Execute the query
        $results = $wpdb->get_results($query);

        $fillData = array_fill(0, 24, 0);
        // add :00 on the suffix
        $fillData = array_combine(array_map(function ($hour) {
            return sprintf('%1d:00', $hour);
        }, array_keys($fillData)), $fillData);

        // Prepare the report
        // Prepare the report
        $report = [
            'Mon' => $fillData,
            'Tue' => $fillData,
            'Wed' => $fillData,
            'Thu' => $fillData,
            'Fri' => $fillData,
            'Sat' => $fillData,
            'Sun' => $fillData
        ];

        foreach ($results as $result) {
            $weekdayName = substr($result->weekday, 0, 3);
            if (!isset($report[$weekdayName])) {
                $report[$weekdayName] = $fillData;
            }
            $report[$weekdayName][sprintf('%1d:00', $result->hour)] = (int)$result->count;
        }

        return $report;
    }

    public function getResponseStats($from, $to, $reportType, $agentId = null)
    {

        global $wpdb;

        $whereClause = " AND p.person_type = '" . $reportType . "'";

        if ($from && $to) {
            // Ensure the dates are in the correct format (Y-m-d H:i:s)
            $start_date = date('Y-m-d 00:00:00', strtotime($from));
            $end_date = date('Y-m-d 23:59:59', strtotime($to));
            $whereClause .= " AND c.created_at BETWEEN '$start_date' AND '$end_date'";
        }

        if ($agentId) {
            $whereClause .= " AND c.person_id = $agentId";
        }

        // SQL query to count customer responses by day of week and hour within the specified date range
        $query = "SELECT DAYNAME(c.created_at) AS weekday, HOUR(c.created_at) AS hour, COUNT(*) AS count
        FROM {$wpdb->prefix}fs_conversations AS c
        JOIN {$wpdb->prefix}fs_persons AS p ON c.person_id = p.id
        WHERE c.conversation_type = 'response'
          {$whereClause}
        GROUP BY DAYNAME(c.created_at), HOUR(c.created_at)
        ORDER BY FIELD(DAYNAME(c.created_at), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), HOUR(c.created_at)";

        // Execute the query
        $results = $wpdb->get_results($query);

        $fillData = array_fill(0, 24, 0);
        // add :00 on the suffix
        $fillData = array_combine(array_map(function ($hour) {
            return sprintf('%1d:00', $hour);
        }, array_keys($fillData)), $fillData);

        // Prepare the report
        $report = [
            'Mon' => $fillData,
            'Tue' => $fillData,
            'Wed' => $fillData,
            'Thu' => $fillData,
            'Fri' => $fillData,
            'Sat' => $fillData,
            'Sun' => $fillData
        ];

        foreach ($results as $result) {
            $weekdayName = substr($result->weekday, 0, 3);
            if (!isset($report[$weekdayName])) {
                $report[$weekdayName] = $fillData;
            }
            $report[$weekdayName][sprintf('%01d:00', $result->hour)] = (int)$result->count;
        }

        return $report;
    }

    public function getTicketResponseStats($from, $to, $filter)
    {
        $query = Conversation::query()
            ->select('id', 'ticket_id', 'person_id', 'created_at', 'content')
            ->addSelect([
                'person_type' => Person::select('person_type')
                    ->whereColumn('id', 'fs_conversations.person_id'),
                'full_name' => Person::selectRaw("CONCAT(first_name, ' ', last_name)")
                    ->whereColumn('id', 'fs_conversations.person_id')
            ])
            ->where('conversation_type', 'response')
            ->when(Arr::get($filter, 'person_type'), function (Builder $q, $personType) {
                return $q->whereHas('person', function ($q) use ($personType) {
                    return $q->where('person_type', $personType);
                });
            })
            ->when(($from && $to), function ($q) use ($from, $to) {
                return $q->whereBetween('created_at', [
                    DateTime::parse($from)->startOfDay(),
                    DateTime::parse($to)->endOfDay()
                ]);
            })
            ->when(Arr::get($filter, 'person_id'), function ($q, $personId) {
                return $q->where('person_id', $personId);
            });

        return $query->get();
    }

    public function applyDateFilter($query, $from, $to)
    {
        if ($from && $to) {
            $from .= ' 00:00:00';
            $to .= ' 23:59:59';

            $query->whereBetween('created_at', [$from, $to]);
        }
    }

    public function finalizeQuery($query)
    {
        return $query->groupByRaw('DAYNAME(created_at), HOUR(created_at)')
            ->orderByRaw("FIELD(DAYNAME(created_at), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), HOUR(created_at)")
            ->get();
    }

    public function formatResults($results)
    {
        $dataItems = [
            'Mon' => [], 'Tue' => [], 'Wed' => [], 'Thu' => [], 'Fri' => [], 'Sat' => [], 'Sun' => []
        ];

        $hours = array_map(function ($hour) {
            return $hour . ":00";
        }, range(0, 23));

        foreach ($dataItems as $day => $data) {
            $dataItems[$day] = array_fill_keys($hours, 0);
        }

        foreach ($results as $row) {
            $day = substr($row['day_of_week'], 0, 3);
            $hour = $row['hour_of_day'] . ":00";
            $dataItems[$day][$hour] = (int) $row['count'];
        }

        return $dataItems;
    }
}
