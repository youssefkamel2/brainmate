<?php

namespace App\Traits;

trait MemberColorTrait
{
    /**
     * Get a fixed color for a user based on their ID.
     */
    public function getMemberColor($userId)
    {
        // Predefined list of colors, including WhatsApp-like colors
        $colors = [
            '#FF7F50', '#40E0D0', '#FFD700', '#E6E6FA', '#FA8072',
            '#87CEEB', '#FFE5B4', '#98FB98', '#FF6347', '#DA70D6',
            '#008080', '#DC143C', '#6A5ACD', '#A0522D', '#4B0082',
            '#808000', '#CCCCFF', '#800000', '#7FFFD4', '#F88379',
            '#4682B4', '#D2691E', '#DDA0DD', '#5F9EA0', '#008B8B',
            '#B22222', '#9370DB', '#E9967A', '#F08080', '#98FB98',
            '#BDB76B', '#BC8F8F', '#3CB371', '#9932CC', '#20B2AA',
            '#FF1493', '#6495ED', '#FF8C00', '#48D1CC', '#DB7093',
            '#483D8B', '#87CEFA', '#BA55D3', '#B8860B', '#FFB6C1',
            '#66CDAA', '#8B008B', '#B0C4DE', '#C71585', '#8FBC8F',
        ];

        // Use a hash function to map user_id to a color
        $index = $userId % count($colors);
        return $colors[$index];
    }
}