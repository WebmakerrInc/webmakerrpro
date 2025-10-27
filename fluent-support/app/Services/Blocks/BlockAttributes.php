<?php

namespace FluentSupport\App\Services\Blocks;

class BlockAttributes
{
    /**
     * @param array An array of attributes used for styling.
     */
    public static function CustomerPortalAttributes()
    {
        return [
            'containerBorderRadius' => [
                'type' => 'number',
                'default' => 16,
            ],
            'primaryButtonBgColor' => [
                'type' => 'string',
                'default' => 'rgba(14, 18, 27, 1)',
            ],
            'primaryButtonTextColor' => [
                'type' => 'string',
                'default' => 'rgba(255, 255, 255, 1)',
            ],
            'secondaryButtonBgColor' => [
                'type' => 'string',
                'default' => 'rgba(255, 255, 255, 1)',
            ],
            'secondaryButtonTextColor' => [
                'type' => 'string',
                'default' => '#18181B',
            ],
            'ticketInputBorderRadius' => [
                'type' => 'number',
                'default' => 10,
            ],
            'avatarBorderRadius' => [
                'type' => 'string',
                'default' => '50%',
            ],
            'showLogoutButton' => [
                'type' => 'boolean',
                'default' => false,
            ],
            'selectedMailbox' => [
                'type' => 'number',
                'default' => 0,
            ]
        ];
    }
}
