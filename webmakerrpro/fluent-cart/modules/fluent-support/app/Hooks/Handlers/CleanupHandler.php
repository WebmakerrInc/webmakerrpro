<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Models\Activity;
use FluentSupport\App\Models\AIActivityLogs;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\App\Services\Integrations\Maintenance;

class CleanupHandler
{
    public function initHourlyTasks()
    {
        $this->cleanLiveActivities();
        $this->maybeDeleteOldTempFiles();
    }

    public function initDailyTasks()
    {
        $this->cleanActivityLogs();

        $this->cleanAIActivityLogs();
    }

    protected function cleanLiveActivities()
    {
        // Delete All Live Activity older than 24 hours
        $oldDateTime = gmdate('Y-m-d H:i:s', current_time('timestamp') - 86400);

        Meta::where('key', '_live_activity')
            ->where('object_type', 'ticket_meta')
            ->where('updated_at', '<', $oldDateTime)
            ->delete();
    }

    protected function cleanActivityLogs()
    {
        $settings = Helper::getOption('_activity_settings', []);

        if (!$settings && empty($settings['delete_days'])) {
            $settings['delete_days'] = 14;
        }

        $oldDateTime = gmdate('Y-m-d H:i:s', current_time('timestamp') - ($settings['delete_days'] * 86400));

        Activity::where('created_at', '<', $oldDateTime)->delete();
    }

    protected function cleanAIActivityLogs()
    {
        if ((!defined('FLUENT_SUPPORT_PRO_DIR_FILE') || !Helper::openAIIntegrationStatus())
            && !Helper::fluentBotIntegrationStatus()) {
            return;
        }

        $settings = Helper::getOption('_ai_activity_settings', []);

        $defaultDays = 14;

        if (!empty($settings['delete_days'])) {
            $defaultDays = (int)$settings['delete_days'];
        }

        $oldDateTime = gmdate('Y-m-d H:i:s', current_time('timestamp') - ($defaultDays * 86400));

        AIActivityLogs::where('created_at', '<', $oldDateTime)->delete();
    }

    public function maybeDeleteAttachmentsOnClose($ticket)
    {
        $settings = (new Settings())->globalBusinessSettings();
        if ($settings['del_files_on_close'] == 'yes') {
            $this->deleteTicketAttachments($ticket);
        }
    }

    public function deleteTicketAttachments($ticket)
    {
        $uploadDir = wp_upload_dir();
        $dir = $uploadDir['basedir'] . FLUENT_SUPPORT_UPLOAD_DIR;

        $attachments = Attachment::where('ticket_id', $ticket->id)->get();

        if (!$attachments->isEmpty()) {
            $ticketDir = $dir . '/ticket_' . $ticket->id;
            if (is_dir($ticketDir)) {
                $this->deleteDir($ticketDir);
            }

            foreach ($attachments as $attachment) {
                if ($attachment->driver != 'local') {
                    do_action('fluent_support/delete_remote_attachment_' . $attachment->driver, $attachment, $ticket->id);
                } else if (file_exists($attachment->file_path)) {
                    wp_delete_file($attachment->file_path);
                }
            }

            Attachment::where('ticket_id', $ticket->id)->delete();
        }

    }

    private function deleteDir($dir)
    {
        if (!class_exists('\WP_Filesystem_Direct')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
            require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');
        }

        $fileSystemDirect = new \WP_Filesystem_Direct(false);
        $fileSystemDirect->rmdir($dir, true);
    }

    public function maybeMaintanceTask()
    {
        (new Maintenance())->maybeProcessData();

        $this->maybeRemoveOldScheuledActionLogs();
    }

    private function maybeDeleteOldTempFiles()
    {
        $dir = FileSystem::getDir();

        // loop through files in directory
        foreach (glob($dir . '/temp_files/*') as $filename) {
            // check if file was created before last 2 hours
            if (time() - filectime($filename) >= 7200) { // 2 hours
                wp_delete_file($filename); // delete file
            }
        }
    }


    function maybeRemoveOldScheuledActionLogs($group_slug = 'fluent-support', $days_old = 7)
    {
        global $wpdb;

        // Get the timestamp for 7 days ago
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$days_old} days"));

        // Get the group ID
        $group_id = $wpdb->get_var($wpdb->prepare(
            "SELECT group_id FROM {$wpdb->prefix}actionscheduler_groups WHERE slug = %s",
            $group_slug
        ));

        if (!$group_id) {
            return false; // Group not found
        }

        // Delete old actions and their associated logs
        $deleted = $wpdb->query($wpdb->prepare("
        DELETE a, l
        FROM {$wpdb->prefix}actionscheduler_actions a
        LEFT JOIN {$wpdb->prefix}actionscheduler_logs l ON a.action_id = l.action_id
        WHERE a.group_id = %d
        AND a.status IN ('complete', 'failed')
        AND a.scheduled_date_gmt < %s", $group_id, $cutoff_date));

        // Clean up orphaned claims
        $wpdb->query("
        DELETE c
        FROM {$wpdb->prefix}actionscheduler_claims c
        LEFT JOIN {$wpdb->prefix}actionscheduler_actions a ON c.claim_id = a.claim_id
        WHERE a.action_id IS NULL");

        return $deleted;
    }
}
