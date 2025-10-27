<?php

namespace FluentSupport\App\Services\Integrations\FluentBot;

use FluentSupport\App\Services\Integrations\FluentBot\FluentBotHelper;

class FluentBotService
{
    public function getPresetPrompts($type): array
    {
        return (new FluentBotHelper())->getPresetPrompts($type);
    }

    public function modifyResponse(string $prompt, $selectedText, $ticketId)
    {
        return (new FluentBotHelper())->modifyResponse($prompt, $selectedText, $ticketId);
    }

    public function generateResponse(string $responseContent, $ticket, $productId, $conversationId = null)
    {
        return (new FluentBotHelper())->generateResponse($responseContent, $ticket, $productId, $conversationId);
    }

    public function generateStreamResponse(string $responseContent, $ticket, $productId, $conversationId = null)
    {
        return (new FluentBotHelper())->generateStreamResponse($responseContent, $ticket, $productId, $conversationId);
    }

    public function getTicketSummary($ticket)
    {
        return (new FluentBotHelper())->generateTicketSummary($ticket);
    }

    public function getTicketTone($ticket)
    {
        return (new FluentBotHelper())->generateTicketTone($ticket);
    }

}
