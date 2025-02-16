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

            '#673AB7', // Dark deep purple
            '#7E57C2', // Dark purple
            '#8E24AA', // Dark magenta
            '#AB47BC', // Dark pink
            '#D81B60', // Dark crimson
            '#E91E63', // Dark pink-red
            '#EC407A', // Dark rose
            '#F06292', // Dark blush
            '#F48FB1', // Dark coral
            '#FF80AB', // Dark salmon
            '#FF4081', // Dark hot pink
            '#F50057', // Dark raspberry
            '#C2185B', // Dark maroon
            '#AD1457', // Dark burgundy
            '#880E4F', // Dark wine
            '#6A1B9A', // Dark plum
            '#7B1FA2', // Dark violet
            '#8E24AA', // Dark orchid
            '#9C27B0', // Dark lavender
            '#AB47BC', // Dark lilac
            '#BA68C8', // Dark mauve
            '#CE93D8', // Dark thistle
            '#E1BEE7', // Dark lavender blush
            '#F3E5F5', // Dark pale lavender
            '#EDE7F6', // Dark light lavender
            '#D1C4E9', // Dark periwinkle
            '#B39DDB', // Dark wisteria
            '#9575CD', // Dark amethyst
            '#7E57C2', // Dark heliotrope
            '#673AB7', // Dark royal purple
            '#5E35B1', // Dark indigo
            '#512DA8', // Dark navy
            '#4527A0', // Dark midnight blue
            '#311B92', // Dark sapphire
            '#1A237E', // Dark cobalt
            '#0D47A1', // Dark azure
            '#1565C0', // Dark cerulean
            '#1976D2', // Dark sky blue
            '#1E88E5', // Dark cornflower
            '#2196F3', // Dark dodger blue
            '#42A5F5', // Dark steel blue
            '#64B5F6', // Dark light blue
            '#90CAF9', // Dark powder blue
            '#BBDEFB', // Dark pale blue
            '#E3F2FD', // Dark light cyan
            '#B2EBF2', // Dark pale cyan
            '#80DEEA', // Dark aqua
            '#4DD0E1', // Dark turquoise
            '#26C6DA', // Dark cyan
            '#00BCD4', // Dark teal
            '#00ACC1', // Dark sea green
            '#0097A7', // Dark emerald
            '#00838F', // Dark forest green
            '#006064', // Dark pine green
        ];

        // Use a hash function to map user_id to a color
        $index = $userId % count($colors);
        return $colors[$index];
    }
}