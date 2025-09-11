<?php
/**
 * Plugin Name: QTV Email Builder
 * Description: Plugin quản lý Email Template (CPT + REST API CRUD)
 * Version: 1.0.0
 * Author: QuanTV
 */

if (!defined('ABSPATH')) {
    exit;
}

// Autoload (nếu dùng composer thì tốt, còn không thì require thủ công)
require_once plugin_dir_path(__FILE__) . 'includes/qtv_email_template.php';
require_once plugin_dir_path(__FILE__) . 'includes/service/qtv_service_email.php';
require_once plugin_dir_path(__FILE__) . 'includes/service/qtv_category_service.php';
require_once plugin_dir_path(__FILE__) . 'includes/service/qtv_media_service.php';
require_once plugin_dir_path(__FILE__) . 'includes/service/qtv_send_test.php';

final class QTV_Email_Builder {

    public function __construct() {
        // Khởi tạo CPT
        new QTV_Email_Template();

        // Khởi tạo service API
        new QTV_Service_Email();
        new QTV_Category_Service();
        new QTV_Media_Service();
        new QTV_SendTest_Service();
    }
}

// Bootstrap plugin
new QTV_Email_Builder();