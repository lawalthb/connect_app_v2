

/**
     * Get list of blocked user IDs for a user
     */
    public static function blockUserList($userId)
    {
        return BlockUser::where('user_id', $userId)
            ->where('deleted_flag', 'N')
            ->pluck('block_user_id')
            ->toArray();
    }
