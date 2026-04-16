<?php
declare(strict_types=1);

// Loyalty tiers thresholds (total_earned in points)
define('LOYALTY_TIERS', [
    'diamond'  => 100000,
    'platinum' => 50000,
    'gold'     => 20000,
    'silver'   => 5000,
    'bronze'   => 0,
]);

// Loyalty weekly bonus points per tier
define('LOYALTY_WEEKLY_BONUS', [
    'diamond'  => 500,
    'platinum' => 250,
    'gold'     => 150,
    'silver'   => 100,
    'bronze'   => 50,
]);

// Daily check-in points per streak day (day 1-7, then repeats)
define('CHECKIN_STREAK_POINTS', [1=>10, 2=>15, 3=>20, 4=>25, 5=>30, 6=>40, 7=>50]);

// Streak milestone bonuses
define('STREAK_MILESTONES', [7=>100, 30=>500, 100=>2000]);

// Rate limiting
define('RATE_LIMIT_TASK_PER_HOUR', 5);

// Login lockout policy
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 30);

// Task anti-cheat: minimum % of declared duration that must pass
define('TASK_MIN_DURATION_RATIO', 0.85);

// Points to USD conversion: X points = $1
define('DEFAULT_POINTS_PER_DOLLAR', 10000);

// Referral commission rates by level
define('REFERRAL_COMMISSION', [1 => 0.10, 2 => 0.05, 3 => 0.02]);

// Default minimum withdrawal in USD
define('DEFAULT_MIN_WITHDRAWAL', 1.00);

// Task types
define('TASK_TYPE_CAPTCHA',       'captcha');
define('TASK_TYPE_WATCH_ADS',     'watch_ads');
define('TASK_TYPE_WEBSITE_VISIT', 'website_visit');
define('TASK_TYPE_VIDEO_WATCH',   'video_watch');
define('TASK_TYPE_DAILY_CHECKIN', 'daily_checkin');
define('TASK_TYPE_LOYALTY_BONUS', 'loyalty_bonus');
define('TASK_TYPE_REFERRAL',      'referral');

// Ad positions
define('AD_PRE_TASK',      'pre_task');
define('AD_POST_TASK',     'post_task');
define('AD_DASHBOARD',     'dashboard');
define('AD_INTERSTITIAL',  'interstitial');
define('AD_DISPLAY_SECONDS', 5);
