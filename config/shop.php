<?php

return [
    // Out-of-stock visibility: 'show' keeps products visible (greyed out), 'hide' removes them.
    'out_of_stock_visibility' => env('SHOP_OUT_OF_STOCK_VISIBILITY', 'show'),

    // Backup settings
    'backup_rclone_remote' => env('BACKUP_RCLONE_REMOTE', 'GD_FeeStore'),
    // Days to keep backup bundles on server disk (Drive keeps everything).
    'backup_keep_days' => env('BACKUP_KEEP_DAYS', 14),
];
