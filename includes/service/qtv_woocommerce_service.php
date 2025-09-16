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
        $type = $request->get_param('type') ?? 'latest';
        $per_page = $request->get_param('per_page') ?? 3;

        if (!in_array($type, ['latest', 'featured', 'on_sale', 'selling', 'rated'])) {
            return new WP_Error('invalid_type', 'Invalid product type', ['status' => 400]);
        }

        try {
            // Cài đặt args mặc định
            $args = [
                'status'  => 'publish',
                'limit'   => $per_page,
                'orderby' => 'date',
                'order'   => 'DESC',
            ];

            // Điều chỉnh args theo type
            switch ($type) {
                case 'featured':
                    $args['featured'] = true;
                    break;

                case 'on_sale':
                    $on_sale_ids = wc_get_product_ids_on_sale();
                    $args['include'] = $on_sale_ids;
                    break;

                case 'selling':
                    $args['orderby'] = 'total_sales';
                    $args['order']   = 'DESC';
                    break;

                case 'rated':
                    $args['orderby']  = 'meta_value_num';
                    $args['meta_key'] = '_wc_average_rating';
                    $args['order']    = 'DESC';
                    break;

                case 'latest':
                default:
                    $args['orderby'] = 'date';
                    $args['order']   = 'DESC';
                    break;
            }

            $products = wc_get_products($args);

            $result = [];
            foreach ($products as $product) {
                $price = wc_price($product->get_price());
                if($type === 'on_sale') {
                    $regular_price = wc_price($product->get_regular_price());
                    $sale_price    = wc_price($product->get_sale_price());
                    $price = '<del>' . $regular_price . '</del>   -   <ins>' . $sale_price . '</ins>';
                }
                $result[] = [
                    'id'             => $product->get_id(),
                    'title'          => $product->get_name(),
                    'price_raw'      => $product->get_price(),
                    'price'          => $price,
                    'link'           => get_permalink($product->get_id()),
                    'featured_image' => wp_get_attachment_image_url($product->get_image_id(), 'full')
                                        ?: wc_placeholder_img_src(),
                ];
            }

            return $this->success($result);
        } catch (Exception $e) {
            return new WP_Error('wc_error', $e->getMessage(), ['status' => 500]);
        }
    }
}
