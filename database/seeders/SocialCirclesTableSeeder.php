<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SocialCirclesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now();

        $socialCircles = [
            [
                'id' => 1,
                'name' => 'Music',
                'logo' => 'MusicIcon.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'Connect with music lovers, share your favorite tracks, and discuss all things music',
                'order_by' => 8,
                'color' => '#E91E63',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 2,
                'name' => 'Sport',
                'logo' => 'SportIcon.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'For sports enthusiasts to discuss games, athletes, events, and fitness',
                'order_by' => 2,
                'color' => '#2196F3',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 3,
                'name' => 'Professionals',
                'logo' => 'ProfessionalsIcon.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'Network with professionals from various industries and share career insights',
                'order_by' => 9,
                'color' => '#607D8B',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 4,
                'name' => 'Gaming',
                'logo' => 'GamingIcon.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'Connect with fellow gamers, discuss the latest games, and organize gaming sessions',
                'order_by' => 6,
                'color' => '#673AB7',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 5,
                'name' => 'Fashion',
                'logo' => 'ImgFashin.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'For fashion enthusiasts to share styles, trends, and fashion advice',
                'order_by' => 5,
                'color' => '#FF9800',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 6,
                'name' => 'Health & Fitness',
                'logo' => 'HealthFitnessIcon.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'Share fitness tips, health advice, and wellness practices',
                'order_by' => 3,
                'color' => '#4CAF50',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 7,
                'name' => 'Foodies',
                'logo' => 'FoodiesIcon.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'For food lovers to share recipes, restaurant recommendations, and cooking tips',
                'order_by' => 13,
                'color' => '#FF5722',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 8,
                'name' => 'Animal lovers',
                'logo' => 'Animallovericon.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'Connect with fellow animal enthusiasts, share pet photos, and discuss animal welfare',
                'order_by' => 14,
                'color' => '#795548',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 9,
                'name' => 'Party',
                'logo' => 'partyIcon.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'Find the best events, parties, and nightlife activities in your area',
                'order_by' => 11,
                'color' => '#9C27B0',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 10,
                'name' => 'TV/Movies',
                'logo' => 'MoviesIcon.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'Discuss your favorite TV shows, movies, actors, and upcoming releases',
                'order_by' => 7,
                'color' => '#F44336',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 11,
                'name' => 'Connect Travel',
                'logo' => 'ConnectTravelIcon.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'Share travel experiences, plan trips, and find travel buddies',
                'order_by' => 15,
                'color' => '#03A9F4',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 23,
                'name' => 'Connect Shop',
                'logo' => 'ConnectShopIcon.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'Buy, sell, and trade items with other members of the community',
                'order_by' => 16,
                'color' => '#8BC34A',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 26,
                'name' => 'Just Connect!',
                'logo' => 'JustConnect!.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'General social circle for making new connections and friends',
                'order_by' => 1,
                'color' => '#00BCD4',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 27,
                'name' => 'Business',
                'logo' => 'Business.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'Network with entrepreneurs, discuss business strategies, and find collaborators',
                'order_by' => 4,
                'color' => '#3F51B5',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 28,
                'name' => 'Education',
                'logo' => 'Education.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'Discuss education topics, share learning resources, and connect with students and educators',
                'order_by' => 10,
                'color' => '#009688',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 29,
                'name' => 'Politics',
                'logo' => 'Politics.png',
                'logo_url' => 'uploads/logo/',
                'description' => 'Discuss current political events, policies, and engage in civil political dialogue',
                'order_by' => 12,
                'color' => '#CDDC39',
                'is_default' => true,
                'is_active' => true,
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now
            ],
        ];

        DB::table('social_circles')->insert($socialCircles);
    }
}
