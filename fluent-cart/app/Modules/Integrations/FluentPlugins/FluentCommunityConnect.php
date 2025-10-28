<?php

namespace FluentCart\App\Modules\Integrations\FluentPlugins;

use FluentCart\App\Modules\Integrations\BaseIntegrationManager;
use FluentCart\App\Services\AuthService;
use FluentCart\App\Vite;
use FluentCart\Framework\Support\Arr;
use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Modules\Course\Model\Course;
use FluentCommunity\Modules\Course\Services\CourseHelper;

class FluentCommunityConnect extends BaseIntegrationManager
{

    protected $runOnBackgroundForProduct = false;

    public $category = 'lms';

    public function __construct()
    {
        parent::__construct('WebmakerrCommunity', 'fluent_community', 12);

        $this->description = __('Create a fast and responsive community and LMS without slowing down your server – no bloat, just performance. Sale your course or memberships with WebmakerrPro + WebmakerrCommunity Integration.', 'fluent-cart');
        $this->logo = Vite::getAssetUrl('images/integrations/fluent-community.svg');
        $this->disableGlobalSettings = true;
        $this->installable = 'fluent-community/fluent-community.php';
    }

    public function isConfigured()
    {
        return defined('FLUENT_COMMUNITY_PLUGIN_VERSION');
    }

    public function getApiSettings()
    {
        return [
            'status'  => defined('FLUENT_COMMUNITY_PLUGIN_VERSION'),
            'api_key' => ''
        ];
    }

    public function getIntegrationDefaults($settings)
    {
        return [
            'enabled'                => 'yes',
            'name'                   => '',
            'space_ids'              => [],
            'remove_space_ids'       => [],
            'course_ids'             => [],
            'remove_course_ids'      => [],
            'event_trigger'          => [],
            'tag_ids_selection_type' => 'simple',
            'mark_as_verified'       => 'no',
            'watch_on_access_revoke' => 'yes'
        ];
    }

    public function getSettingsFields($settings, $args = [])
    {
        $spaces = Space::orderBy('title', 'ASC')->select(['id', 'title', 'parent_id'])
            ->with(['group'])
            ->get();
        $formattedSpaces = [];
        foreach ($spaces as $space) {
            $title = $space->title;

            if ($space->group) {
                $title .= ' (' . $space->group->title . ')';
            }

            $formattedSpaces[(string)$space->id] = $title;
        }

        $formattedCourses = [];

        $isCourseEnabled = Helper::isFeatureEnabled('course_module');

        if ($isCourseEnabled) {
            $courses = Course::orderBy('title', 'ASC')->select(['id', 'title'])->get();

            $formattedCourses = [];
            foreach ($courses as $course) {
                $formattedCourses[(string)$course->id] = $course->title;
            }
        }

        $fields = [
            'name'                   => [
                'key'         => 'name',
                'label'       => __('Feed Title', 'fluent-cart'),
                'required'    => true,
                'placeholder' => __('Name', 'fluent-cart'),
                'component'   => 'text',
                'inline_tip'  => __('Name of this feed, it will be used to identify this feed in the list of feeds', 'fluent-cart')
            ],
            'space_ids'              => [
                'key'         => 'space_ids',
                'label'       => __('Add to Spaces', 'fluent-cart'),
                'placeholder' => __('Select WebmakerrCommunity Spaces', 'fluent-cart'),
                'inline_tip'  => __('Select the WebmakerrCommunity Spaces you would like to add.', 'fluent-cart'),
                'component'   => 'select',
                'is_multiple' => true,
                'required'    => false,
                'options'     => $formattedSpaces
            ],
            'course_ids'             => [
                'key'          => 'course_ids',
                'require_list' => false,
                'label'        => __('Add to Courses', 'fluent-cart'),
                'placeholder'  => __('Select Courses', 'fluent-cart'),
                'component'    => 'select',
                'is_multiple'  => true,
                'options'      => $formattedCourses,
                'inline_tip'   => __('Select the courses you would like to enroll the customer to', 'fluent-cart')
            ],
            'remove_space_ids'       => [
                'key'         => 'remove_space_ids',
                'label'       => __('Remove From Spaces', 'fluent-cart'),
                'placeholder' => __('Select Spaces', 'fluent-cart'),
                'inline_tip'  => __('Select the Spaces you would like to remove from your spaces.', 'fluent-cart'),
                'component'   => 'select',
                'is_multiple' => true,
                'required'    => false,
                'options'     => $formattedSpaces
            ],
            'remove_course_ids'      => [
                'key'          => 'remove_course_ids',
                'require_list' => false,
                'label'        => __('Remove From Courses', 'fluent-cart'),
                'placeholder'  => __('Select Courses', 'fluent-cart'),
                'component'    => 'select',
                'is_multiple'  => true,
                'options'      => $formattedCourses,
                'inline_tip'   => __('Select the courses you would like to remove from the customer', 'fluent-cart')
            ],
            'mark_as_verified'       => [
                'key'            => 'mark_as_verified',
                'component'      => 'yes-no-checkbox',
                'checkbox_label' => __('Mark the community profile as verified', 'fluent-cart'),
                'inline_tip'     => __('If you enable this, the user will be marked as verified in WebmakerrCommunity', 'fluent-cart')
            ],
            'watch_on_access_revoke' => [
                'key'            => 'watch_on_access_revoke',
                'component'      => 'yes-no-checkbox',
                'checkbox_label' => __('Remove from selected Courses/Spaces on Refund or Subscription Access Expiration ', 'fluent-cart'),
                'inline_tip'     => __('If you enable this, on refund or subscription validity expiration, the selected spaces and courses will be removed from the customer.', 'fluent-cart')
            ]
        ];

        if (!$isCourseEnabled) {
            unset($fields['course_ids']);
            unset($fields['remove_course_ids']);
        }

        $fields = array_values($fields);

        $fields[] = $this->actionFields();

        return [
            'fields'              => $fields,
            'button_require_list' => false,
            'integration_title'   => __('WebmakerrCommunity', 'fluent-cart')
        ];
    }

    /*
     * For Handling Notifications broadcast
     */
    public function processAction($order, $eventData)
    {
        $feedConfig = Arr::get($eventData, 'feed', []);
        $isRevokedHook = Arr::get($eventData, 'is_revoke_hook') === 'yes';
        $customer = $order->customer;

        // check exits
        $courseIds = array_filter((array)Arr::get($feedConfig, 'course_ids', []), 'intval');
        $spaceIds = array_filter((array)Arr::get($feedConfig, 'space_ids', []), 'intval');
        $removeCourseIds = array_filter((array)Arr::get($feedConfig, 'remove_course_ids', []), 'intval');
        $removeSpaceIds = array_filter((array)Arr::get($feedConfig, 'remove_space_ids', []), 'intval');
        $markAsVerified = Arr::get($feedConfig, 'mark_as_verified', '') == 'yes';

        $userId = $customer->getWpUserId(true);

        if ($isRevokedHook) {
            if (!$userId) {
                return;
            }

            $xProfile = XProfile::where('user_id', $userId)->first();
            if (!$xProfile) {
                return; // no xprofile found
            }

            // we will remove the spaces and courses from the user
            if ($spaceIds) {
                foreach ($spaceIds as $spaceId) {
                    Helper::removeFromSpace($spaceId, $userId, 'by_admin');
                }
            }
            if ($courseIds) {
                CourseHelper::leaveCourses($courseIds, $userId, 'by_admin');
            }
            return;
        }

        if (!$userId) {
            $userId = AuthService::createUserFromCustomer($customer);
            if (is_wp_error($userId)) {
                $order->addLog(
                    __('User creation failed from WebmakerrCommunity Integration', 'fluent-cart'),
                    $userId->get_error_message(),
                    'error',
                    'WebmakerrCommunity Integration'
                );
                return;
            }
        }

        if (!$userId) {
            return false;
        }

        $communityUser = User::find($userId);

        if (!$communityUser) {
            return;
        }

        $xprofile = $communityUser->syncXProfile(true);

        if (!$xprofile) {
            return;
        }

        if ($markAsVerified) {
            $xprofile->is_verified = 1;
            $xprofile->save();
        }

        // we will remove the spaces and courses from the user
        if ($removeSpaceIds) {
            foreach ($removeSpaceIds as $spaceId) {
                Helper::removeFromSpace($spaceId, $userId, 'by_admin');
            }
        }

        if ($removeCourseIds) {
            CourseHelper::leaveCourses($removeCourseIds, $userId, 'by_admin');
        }

        if ($spaceIds) {
            foreach ($spaceIds as $spaceId) {
                Helper::addToSpace($spaceId, $userId, 'member', 'by_admin');
            }
        }

        if ($courseIds) {
            CourseHelper::enrollCourses($courseIds, $userId, 'by_admin');
        }

        $order->addLog(
            __('WebmakerrCommunity Integration Success', 'fluent-cart'),
            sprintf(__('User has been added to spaces: %s and courses: %s', 'fluent-cart'), implode(', ', $spaceIds), implode(', ', $courseIds)),
            'info',
            'WebmakerrCommunity Integration'
        );
    }

}
