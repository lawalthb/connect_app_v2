<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Faker\Factory as Faker;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create();
        $now = Carbon::now();

        // Create an admin user
        $users = [
            [
                'name' => 'Administrator',
                'email' => 'admin@example.com',
                'password' => Hash::make('Password123'),
                'username' => 'admin',
                'bio' => 'System administrator account',
                'country_id' => 1,
                'device_token' => 'admin-device-token-' . $faker->uuid,
                'email_verified_at' => $now,
                'is_verified' => true,
                'verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now
            ]
        ];

        // Generate 9 additional random users
        for ($i = 0; $i < 9; $i++) {
            $firstName = $faker->firstName;
            $lastName = $faker->lastName;
            $name = $firstName . ' ' . $lastName;
            $username = strtolower(str_replace(' ', '', $firstName . $lastName . $faker->numberBetween(1, 999)));

            $users[] = [
                'name' => $name,
                'email' => $faker->unique()->safeEmail,
                'password' => Hash::make('Password123'),
                'username' => $username,
                'bio' => $faker->paragraph(1),
                'country_id' => $faker->numberBetween(1, 20),
                'device_token' => 'device-token-' . $faker->uuid,
                'email_verified_at' => $now,
                'is_verified' => true,
                'verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now
            ];
        }

        // Insert users
        DB::table('users')->insert($users);

        // Assign roles if the model_has_roles table exists
        if (DB::getSchemaBuilder()->hasTable('model_has_roles')) {
            foreach ($users as $user) {
                $userId = DB::table('users')->where('email', $user['email'])->first()->id;

                try {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $user['username'] === 'admin' ? 1 : 2, // 1 = Admin, 2 = User
                        'model_type' => 'App\\Models\\User',
                        'model_id' => $userId
                    ]);
                } catch (\Exception $e) {
                    // Role assignment failed - continue without role assignment
                }
            }
        }

        // Assign default social circles to users
        $this->assignSocialCircles($users);
    }

    /**
     * Assign social circles to users
     *
     * @param array $users
     * @return void
     */
    private function assignSocialCircles($users)
    {
        $now = Carbon::now();

        // Check if social circles table exists
        if (!DB::getSchemaBuilder()->hasTable('social_circles')) {
            return;
        }

        // Get the actual table name for user social circles
        $userSocialCirclesTable = 'user_social_circles';

        // Check if the user_social_circles table exists
        if (!DB::getSchemaBuilder()->hasTable($userSocialCirclesTable)) {
            // Try alternative table names
            $alternativeTableNames = ['user_social_circle', 'users_social_circles', 'user_socials'];
            foreach ($alternativeTableNames as $tableName) {
                if (DB::getSchemaBuilder()->hasTable($tableName)) {
                    $userSocialCirclesTable = $tableName;
                    break;
                }
            }

            // If still no table found, return
            if (!DB::getSchemaBuilder()->hasTable($userSocialCirclesTable)) {
                return;
            }
        }

        // Get column names from the user social circles table
        $columns = DB::connection()->getSchemaBuilder()->getColumnListing($userSocialCirclesTable);

        // Determine the correct column names
        $userIdColumn = 'user_id';
        $socialIdColumn = 'social_id';

        // Check if the columns exist, otherwise try to find alternatives
        if (!in_array($userIdColumn, $columns)) {
            foreach ($columns as $column) {
                if (strpos($column, 'user') !== false) {
                    $userIdColumn = $column;
                    break;
                }
            }
        }

        if (!in_array($socialIdColumn, $columns)) {
            foreach ($columns as $column) {
                if (strpos($column, 'social') !== false && $column != $userIdColumn) {
                    $socialIdColumn = $column;
                    break;
                }
            }
        }

        // Get available social circle IDs
        $socialCircleIds = DB::table('social_circles')->pluck('id')->toArray();

        if (empty($socialCircleIds)) {
            return;
        }

        // Process each user
        foreach ($users as $user) {
            $userId = DB::table('users')->where('email', $user['email'])->first()->id;

            // Assign each social circle to the user
            foreach ($socialCircleIds as $socialCircleId) {
                try {
                    $data = [
                        $userIdColumn => $userId,
                        $socialIdColumn => $socialCircleId,
                    ];

                    // Add timestamps if they exist in the table
                    if (in_array('created_at', $columns)) {
                        $data['created_at'] = $now;
                    }

                    if (in_array('updated_at', $columns)) {
                        $data['updated_at'] = $now;
                    }

                    DB::table($userSocialCirclesTable)->insert($data);
                } catch (\Exception $e) {
                    // Log the error for debugging
                    \Log::error("Failed to assign social circle {$socialCircleId} to user {$userId}: " . $e->getMessage());
                    continue;
                }
            }
        }
    }
}
