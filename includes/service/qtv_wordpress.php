<?php
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . './qtv_response_helper.php';

class QTV_WordPress_Service {
    use QTV_Response_Helper;


    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Đăng ký endpoint cho taxonomy Category
     */
    public function register_routes() {
        register_rest_route('qtv-email/v1', '/wordpress/post', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'qtv_get_latest_products'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

}
