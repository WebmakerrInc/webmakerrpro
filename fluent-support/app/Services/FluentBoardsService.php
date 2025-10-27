<?php

namespace FluentSupport\App\Services;

use Exception;
use FluentSupport\App\Models\Conversation;

class FluentBoardsService
{

    public function addInternalNote($task)
    {
        $urlBase = admin_url('admin.php?page=fluent-boards#/');
        $boardsProfileUrl = $urlBase . 'boards/' . $task->board_id . '/tasks/' . $task->id;

        $internalNote = 'A new task has been created for this ticket in Fluent Boards. You can view and manage it at the following link:<br><a href="' . $boardsProfileUrl . '">here</a>';

        $agent = Helper::getAgentByUserId(get_current_user_id());

        Conversation::create([
            'ticket_id'         => $task->source_id,
            'person_id'         => $agent->id,
            'conversation_type' => 'internal_info',
            'content'           => $internalNote
        ]);
    }

    public function addComment($task)
    {
        $baseUrl = admin_url('admin.php?page=fluent-support#/tickets/');
        $ticketUrl = $baseUrl . $task->source_id . '/view';

        $description = 'This task was created from Fluent Support. Here you can find this ticket: <a href="' . $ticketUrl . '" target="_blank">View Ticket</a>';

        $commentData = [
            'description' => $description,
            'created_by' => $task->created_by,
            'task_id' => $task->id,
            'board_id' => $task->board_id,
            'type' => 'comment'
        ];

        \FluentBoards\App\Models\Comment::create( $commentData);
    }
}
