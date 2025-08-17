<?php
/**
 * Plugin Name: Signal Archive Manager
 * Description: PSR-4 based signal archive management system
 * Version: 1.0.0
 * Author: Vider Development Team
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Autoload classes
require_once __DIR__ . '/vendor/autoload.php';

use Vider\SignalArchive\SignalArchiveManager;

// Initialize the plugin immediately
SignalArchiveManager::getInstance();
