<?php

namespace FluentSupport\App\Http\Controllers;


use FluentSupport\Framework\Request\Request;
use FluentSupport\App\Http\Controllers\Controller;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Integrations\FluentBot\FluentBotService;

class FluentBotController extends Controller
{
    public function getPresetPrompts(Request $request)
    {
        $type = $request->getSafe('type', 'sanitize_text_field');

        try {
            return (new FluentBotService())->getPresetPrompts($type);
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function generateResponse(Request $request)
    {
        $ticketId = $request->getSafe('id', 'intval');
        $productId = $request->getSafe('product_id', 'intval');
        $prompt = $request->getSafe('content', 'sanitize_text_field');
        $conversationId = $request->getSafe('conversation_id', 'sanitize_text_field', '');
        $selectedText = $request->getSafe('selectedText', 'sanitize_text_field', '');
        $type = $request->getSafe('type', 'sanitize_text_field', 'response');

        try {
            $customAI = new FluentBotService();

            if ($type === 'modifyResponse') {
                $result = $customAI->modifyResponse($prompt, $selectedText, $ticketId);
            } else {
                $ticket = Ticket::with('responses')->findOrFail($ticketId);
                $result = $customAI->generateResponse($prompt, $ticket, $productId, $conversationId ?: null);
            }

            return $result;
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function generateStreamResponse(Request $request)
    {
        $ticketId = $request->getSafe('id', 'intval');
        $productId = $request->getSafe('product_id', 'intval');
        $prompt = $request->getSafe('content', 'sanitize_text_field');
        $conversationId = $request->getSafe('conversation_id', 'sanitize_text_field', '');
        $selectedText = $request->getSafe('selectedText', 'sanitize_text_field', '');
        $type = $request->getSafe('type', 'sanitize_text_field', 'response');

        try {
            $customAI = new FluentBotService();

            if ($type === 'modifyResponse') {
                $result = $customAI->modifyResponse($prompt, $selectedText, $ticketId);
                return $result;
            } else {
                $ticket = Ticket::with('responses')->findOrFail($ticketId);

                // Disable all output buffering
                while (ob_get_level()) {
                    ob_end_clean();
                }

                // Set headers for Server-Sent Events
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no'); // Disable nginx buffering
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Headers: Cache-Control');

                // Disable WordPress output buffering
                remove_action('shutdown', 'wp_ob_end_flush_all', 1);

                // Send initial connection event
                echo "event: connected\n";
                echo "data: Connection established\n\n";
                flush();

                // Start streaming response
                $customAI->generateStreamResponse($prompt, $ticket, $productId, $conversationId ?: null);

                // Send end event
                echo "event: end\n";
                echo "data: Stream completed\n\n";
                flush();

                exit;
            }
        } catch (\Exception $e) {
            // Send error as SSE event
            echo "event: error\n";
            echo "data: " . json_encode(['message' => $e->getMessage()]) . "\n\n";
            flush();
            exit;
        }
    }

    public function getTicketSummary(Request $request)
    {
        $ticketId = $request->getSafe('id', 'intval');
        $ticket = Ticket::with('responses')->findOrFail($ticketId);

        try {
            return (new FluentBotService())->getTicketSummary($ticket);
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function getTicketTone(Request $request)
    {
        $ticketId = $request->getSafe('id', 'intval');
        $ticket = Ticket::with('responses')->findOrFail($ticketId);

        try {
            return (new FluentBotService())->getTicketTone($ticket);
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ]);
        }
    }

}
