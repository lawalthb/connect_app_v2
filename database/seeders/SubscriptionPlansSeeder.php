<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subscribe;

class SubscriptionPlansSeeder extends Seeder
{
    public function run()
    {
        $plans = [
            [
                'id' => 1,
                'name' => 'Connect Travel',
                'slug' => 'connect-travel',
                'description' => 'Connect specifically to people in a particular country. Plan for travel by meeting people beforehand or meet people in a country at a safe distance.',
                'price' => 7.99,
                'currency' => 'USD',
                'duration_days' => 30,
                'features' => [
                    'travel_connections',
                    'country_specific_search',
                    'travel_planning_tools',
                    'safe_distance_connections'
                ],
                'is_active' => true,
                'sort_order' => 1,
                'badge_color' => '#4F46E5',
                'icon' => 'travel'
            ],
            [
                'id' => 2,
                'name' => 'Connect Unlimited',
                'slug' => 'connect-unlimited',
                'description' => 'Connect with as many users as you like on a daily basis. No daily limits on connections.',
                'price' => 5.99,
                'currency' => 'USD',
                'duration_days' => 30,
                'features' => [
                    'unlimited_daily_connections',
                    'unlimited_swipes',
                    'unlimited_likes',
                    'priority_support'
                ],
                'is_active' => true,
                'sort_order' => 2,
                'badge_color' => '#059669',
                'icon' => 'unlimited'
            ],
            [
                'id' => 3,
                'name' => 'Connect Premium',
                'slug' => 'connect-premium',
                'description' => 'All features from Connect Unlimited, Connect Travel and 2x Connect Boost. The ultimate connection experience.',
                'price' => 9.99,
                'currency' => 'USD',
                'duration_days' => 30,
                'features' => [
                    'unlimited_daily_connections',
                    'unlimited_swipes',
                    'unlimited_likes',
                    'travel_connections',
                    'country_specific_search',
                    'travel_planning_tools',
                    'safe_distance_connections',
                    'premium_boost',
                    'priority_support',
                    'advanced_filters',
                    'read_receipts',
                    'profile_boost_2x'
                ],
                'is_active' => true,
                'sort_order' => 3,
                'badge_color' => '#DC2626',
                'icon' => 'premium'
            ],
            [
                'id' => 4,
                'name' => 'Connect Boost',
                'slug' => 'connect-boost',
                'description' => 'Put yourself in the front of the line and enable more users to view your profile. Increase your visibility.',
                'price' => 4.99,
                'currency' => 'USD',
                'duration_days' => 1, // Boost is for 24 hours
                'features' => [
                    'profile_boost',
                    'increased_visibility',
                    'front_of_line',
                    'more_profile_views'
                ],
                'is_active' => true,
                'sort_order' => 4,
                'badge_color' => '#F59E0B',
                'icon' => 'boost'
            ]
        ];

        foreach ($plans as $plan) {
            Subscribe::updateOrCreate(
                ['id' => $plan['id']],
                $plan
            );
        }
    }
}
