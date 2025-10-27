<?php

namespace FluentSupport\App\Hooks\Handlers;


use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;

class ExternalPages
{
    public function route()
    {
        $route = sanitize_text_field($_REQUEST['fs_view']);

        $methodMaps = [
            'ticket' => 'handleTicketView'
        ];

        if (isset($methodMaps[$route])) {
            $this->{$methodMaps[$route]}();
        }

    }

    public function handleTicketView()
    {
        if (!Helper::isPublicSignedTicketEnabled()) {
            $this->handleInvalidTicket();
        } else {
            $this->handleValidTicket();
        }
    }

    /**
     * Display the attachment.
     *
     * Uses the new rewrite endpoint to get an attachment ID
     * and display the attachment if the currently logged in user
     * has the authorization to.
     *
     * @return void
     * @since 3.2.0
     */
    public function view_attachment()
    {
        $attachmentHash = sanitize_text_field($_REQUEST['fst_file']);

        if (empty($attachmentHash)) {
            die('Invalid Attachment Hash');
        }

        $attachment = $this->getAttachmentByHash($attachmentHash);

        if (!$attachment) {
            die('Invalid Attachment Hash');
        }

        // check signature hash
        if (!$this->validateAttachmentSignature($attachment)) {
            $dieMessage = esc_html__('Sorry, Your secure sign is invalid, Please reload the previous page and get new signed url', 'fluent-support');
            die(esc_html($dieMessage));  // Escaping the die message again for safety
        }

        //If external file
        if ('local' !== $attachment->driver) {
            if(!empty($attachment->full_url)){
                $this->redirectToExternalAttachment($attachment->full_url);
            }else{
                die('File could not be found');
            }
        }

        //Handle Local file
        if (!file_exists($attachment->file_path)) {
            die('File could not be found');
        }
        $this->serveLocalAttachment($attachment);
    }

    private function getAttachmentByHash($attachmentHash)
    {
        return Attachment::where('file_hash', $attachmentHash)->first();
    }

    private function validateAttachmentSignature($attachment)
    {
        $sign = md5($attachment->id . date('YmdH'));
        return $sign === $_REQUEST['secure_sign'];
    }

    private function handleInvalidTicket()
    {
        $ticketId = absint(Arr::get($_REQUEST, 'ticket_id'));
        $ticket = Ticket::where('id', $ticketId)->first();

        if (!$ticket) {
            $this->showInvalidPortalMessage();
        } else {
            $this->redirectToTicketView($ticket);
        }
    }

    private function handleValidTicket()
    {
        $ticketHash = sanitize_text_field(Arr::get($_REQUEST, 'support_hash'));
        $ticketId = absint(Arr::get($_REQUEST, 'ticket_id'));
        $ticket = Ticket::where('hash', $ticketHash)->where('id', $ticketId)->first();

        if (!$ticket) {
            $this->showInvalidPortalMessage();
        } elseif (get_current_user_id()) {
            $this->redirectToTicketView($ticket);
        }
    }

    private function showInvalidPortalMessage()
    {
        echo '<h3 style="text-align: center; margin: 50px 0;">' . esc_html__('Invalid Support Portal URL', 'fluent-support') . '</h3>';
        die();
    }

    private function redirectToTicketView($ticket)
    {
        $redirectUrl = Helper::getTicketViewUrl($ticket);
        $this->redirectToExternalAttachment($redirectUrl);
    }

    private function redirectToExternalAttachment($redirectUrl)
    {
        wp_redirect($redirectUrl, 307);
        exit();
    }

    // Helper method to serve an attachment
    private function serveLocalAttachment($attachment)
    {
        $file_path = realpath($attachment->file_path);
        $uploads     = wp_upload_dir();
        $uploads_dir = realpath($uploads['basedir']); // Ensures both paths are absolute

        if (!$file_path || !$uploads_dir || strpos($file_path, $uploads_dir) !== 0 || !file_exists($file_path)) {
            wp_die(esc_html__('File not found or access denied', 'fluent-support'), 403);
            return;
        }
        
        ob_get_clean();
        ini_set('user_agent', 'Fluent Support/' . FLUENT_SUPPORT_VERSION . '; ' . esc_url(get_bloginfo('url')));
        
        header("Content-Type: " . esc_attr($attachment->file_type));
        header("Content-Disposition: inline; filename=\"" . esc_attr($attachment->title) . "\"");
        readfile($file_path);
        die();
    }

}
