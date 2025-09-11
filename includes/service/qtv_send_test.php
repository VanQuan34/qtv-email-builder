<?php
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . './qtv_response_helper.php';

class QTV_SendTest_Service {
    use QTV_Response_Helper;


    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        // Thay đổi tên người gửi
        add_filter('wp_mail_from_name', function() {
            return 'QTV Email Builder';
        }, 9999);
    }

    /**
     * Đăng ký endpoint cho taxonomy Category
     */
    public function register_routes() {
        register_rest_route('qtv-email/v1', 'actions/send-test-email', [
            [
                'methods'  => 'POST',
                'callback' => [$this, 'send_test_email'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }
    /**
     * Gửi email test
     */

    public function send_test_email($request) {
        $params = $request->get_json_params();
        $to = isset($params['send_to']) ? $params['send_to'] : '';
        $subject = isset($params['title']) ? sanitize_text_field($params['title']) : '';
        $message = isset($params['body']) ? $params['body'] : '';
        
        $headers = isset($params['headers']) && is_array($params['headers']) ? $params['headers'] : ['Content-Type: text/html; charset=UTF-8'];

        if (empty($to) || empty($subject) || empty($message)) {
            return new WP_Error('missing_params', 'Missing email, subject, or message', array('status' => 400));
        }
        
        if (is_string($to) && !empty($to)) {
                $to = [$to];
        } elseif (is_array($to) && !empty($to)) {
                $to = $to;
        } else {
            return new WP_REST_Response(
                array(
                    'code' => 400,
                    'message' => 'Missing or invalid email recipient',
                    'data' => null
                ),
                400
            );
        }

        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            return array('code' => 200, 'success' => true, 'message' => 'Email sent!');
        } else {
            return new WP_Error('send_fail', 'Unable to send email', array('status' => 500));
        }
    }
}
