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
            '#1A1A1A', '#2E2E2E', '#3D3D3D', '#4A4A4A', '#5C5C5C',
            '#6E6E6E', '#808080', '#929292', '#A4A4A4', '#B6B6B6',
            '#1F1F1F', '#2F2F2F', '#3F3F3F', '#4F4F4F', '#5F5F5F',
            '#6F6F6F', '#7F7F7F', '#8F8F8F', '#9F9F9F', '#AFAFAF',
            '#121212', '#242424', '#363636', '#484848', '#5A5A5A',
            '#6C6C6C', '#7E7E7E', '#909090', '#A2A2A2', '#B4B4B4',
            '#0D0D0D', '#1D1D1D', '#2D2D2D', '#3D3D3D', '#4D4D4D',
            '#5D5D5D', '#6D6D6D', '#7D7D7D', '#8D8D8D', '#9D9D9D',
            '#101010', '#202020', '#303030', '#404040', '#505050',
            '#606060', '#707070', '#808080', '#909090', '#A0A0A0',
            '#0A0A0A', '#1A1A1A', '#2A2A2A', '#3A3A3A', '#4A4A4A',
            '#5A5A5A', '#6A6A6A', '#7A7A7A', '#8A8A8A', '#9A9A9A',
            '#141414', '#242424', '#343434', '#444444', '#545454',
            '#646464', '#747474', '#848484', '#949494', '#A4A4A4',
            '#0E0E0E', '#1E1E1E', '#2E2E2E', '#3E3E3E', '#4E4E4E',
            '#5E5E5E', '#6E6E6E', '#7E7E7E', '#8E8E8E', '#9E9E9E',
            '#161616', '#262626', '#363636', '#464646', '#565656',
            '#666666', '#767676', '#868686', '#969696', '#A6A6A6',
            '#0C0C0C', '#1C1C1C', '#2C2C2C', '#3C3C3C', '#4C4C4C',
            '#5C5C5C', '#6C6C6C', '#7C7C7C', '#8C8C8C', '#9C9C9C',
        ];

        // Use a hash function to map user_id to a color
        $index = $userId % count($colors);
        return $colors[$index];
    }
}