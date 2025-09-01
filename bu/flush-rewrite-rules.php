<?php
/**
 * Temporary script to flush rewrite rules
 * Delete this file after running once
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../wp-load.php');
}

// Flush rewrite rules
flush_rewrite_rules(true);

echo "Rewrite rules flushed successfully!\n";
echo "You can now delete this file.\n";
echo "Try accessing: https://v118.local/signal/\n";
?>
