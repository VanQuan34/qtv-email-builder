<?php
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . './qtv_response_helper.php';

class QTV_Woocommerce_Service {
    use QTV_Response_Helper;


    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Đăng ký endpoint cho taxonomy Category
     */
    public function register_routes() {
        register_rest_route('qtv-email/v1', '/woocommerce/products', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'qtv_get_latest_products'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    function qtv_get_latest_products(WP_REST_Request $request) {
        $type = $request->get_param('type') ?? 'latest'; // latest, featured, on_sale
         if (!in_array($type, ['latest', 'featured', 'on_sale', 'selling'])) {
            return new WP_Error('invalid_type', 'Invalid product type', ['status' => 400]);
        }
        try {
            $args = [
                'status' => 'publish',
                'limit'  => 3,
                'orderby'=> 'date',
                'order'  => 'DESC',
            ];

            $products = wc_get_products($args);

            $result = [];
            foreach ($products as $product) {
                $result[] = [
                    'id'    => $product->get_id(),
                    'title'  => $product->get_name(),
                    'price' => wc_price($product->get_price()),
                    'link'  => get_permalink($product->get_id()),
                    'featured_image' => wp_get_attachment_image_url($product->get_image_id(), 'full') 
                                ?: wc_placeholder_img_src(), // fallback placeholder
                ];
            }

            return $this->success($result);
        } catch (Exception $e) {
            return new WP_Error('wc_error', $e->getMessage(), ['status' => 500]);
        }
    }
}
