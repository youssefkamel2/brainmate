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
            '#FF6633', '#FFB399', '#FF33FF', '#FFFF99', '#00B3E6', 
            '#E6B333', '#3366E6', '#999966', '#99FF99', '#B34D4D',
            '#FF6666', '#66FF66', '#6666FF', '#FF66FF', '#66FFFF',
            '#FFCC66', '#CCFF66', '#66CCFF', '#CC66FF', '#FF6666',
            '#FFCCCC', '#CCFFCC', '#CCCCFF', '#FFCCFF', '#CCFFFF',
            '#FF9966', '#99FF66', '#6699FF', '#9966FF', '#FF6699',
            '#FFCC99', '#99FFCC', '#99CCFF', '#CC99FF', '#FF99CC',
            '#FF6666', '#66FF66', '#6666FF', '#FF66FF', '#66FFFF',
            '#FFCC66', '#CCFF66', '#66CCFF', '#CC66FF', '#FF6666',
            '#FFCCCC', '#CCFFCC', '#CCCCFF', '#FFCCFF', '#CCFFFF',
            '#FF9966', '#99FF66', '#6699FF', '#9966FF', '#FF6699',
            '#FFCC99', '#99FFCC', '#99CCFF', '#CC99FF', '#FF99CC',
            '#DCF8C6', '#ECE5DD', '#D4E7F7', '#F5F5F5', '#F8F8F8',
            '#E5F6FB', '#F0F8FF', '#F0FFF0', '#FFF0F5', '#F5F5DC',
            '#F0E68C', '#E6E6FA', '#FFFACD', '#ADD8E6', '#F08080',
            '#E0FFFF', '#90EE90', '#D3D3D3', '#FFB6C1', '#FFA07A',
            '#20B2AA', '#87CEFA', '#778899', '#B0C4DE', '#FFFFE0',
            '#00FF00', '#32CD32', '#FAFAD2', '#FF00FF', '#800080',
            '#FF0000', '#FF4500', '#DA70D6', '#EEE8AA', '#98FB98',
            '#AFEEEE', '#DB7093', '#FFEFD5', '#FFDAB9', '#CD853F',
            '#FFC0CB', '#DDA0DD', '#B0E0E6', '#FF6347', '#FFA500',
            '#FFD700', '#6A5ACD', '#7B68EE', '#00FA9A', '#48D1CC',
            '#C71585', '#191970', '#F5FFFA', '#FFE4E1', '#FFE4B5',
            '#FFDEAD', '#000080', '#FDF5E6', '#808000', '#6B8E23',
            '#FFA500', '#FF4500', '#DA70D6', '#EEE8AA', '#98FB98',
            '#AFEEEE', '#DB7093', '#FFEFD5', '#FFDAB9', '#CD853F',
            '#FFC0CB', '#DDA0DD', '#B0E0E6', '#FF6347', '#FFA500',
            '#FFD700', '#6A5ACD', '#7B68EE', '#00FA9A', '#48D1CC',
            '#C71585', '#191970', '#F5FFFA', '#FFE4E1', '#FFE4B5',
            '#FFDEAD', '#000080', '#FDF5E6', '#808000', '#6B8E23',
        ];

        // Use a hash function to map user_id to a color
        $index = $userId % count($colors);
        return $colors[$index];
    }
}