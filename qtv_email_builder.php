<?php
/**
 * Plugin Name: QTV Email Builder
 * Description: Plugin quản lý Email Template (CPT + REST API CRUD)
 * Version: 1.0.0
 * Author: QuanTV
 * Text Domain: qtv-email-builder
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', function() {
    load_plugin_textdomain(
        'qtv-email-builder',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});

// Autoload (nếu dùng composer thì tốt, còn không thì require thủ công)
require_once plugin_dir_path(__FILE__) . 'includes/qtv_email_template.php';
require_once plugin_dir_path(__FILE__) . 'includes/service/qtv_service.php';

final class QTV_Email_Builder {

    public function __construct() {
        // Khởi tạo CPT
        new QTV_Email_Template();

        // Khởi tạo service API
        new QTV_Service_Email();
    }
}

// Bootstrap plugin
new QTV_Email_Builder();