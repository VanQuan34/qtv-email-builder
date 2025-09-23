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
                'permission_callback' => [$this, 'qtv_permission_check'],
            ],
        ]);

        register_rest_route('qtv-email/v1', '/woocommerce/product/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_single_product'],
                'permission_callback' => [$this, 'qtv_permission_check'],
            ],
        ]);

        register_rest_route('qtv-email/v1', '/woocommerce/product/filter', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'filter_products_by_name'],
                'permission_callback' => [$this, 'qtv_permission_check'],
            ],
        ]);

        register_rest_route('qtv-email/v1', '/woocommerce/product/category', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_product_categories'],
                'permission_callback' => [$this, 'qtv_permission_check'],
            ],
        ]);

        register_rest_route('qtv-email/v1', '/woocommerce/product/tag', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_product_tags'],
                'permission_callback' => [$this, 'qtv_permission_check'],
            ],
        ]);
    }

    private function is_woocommerce_active() {
        return class_exists('WooCommerce') || (function_exists('is_plugin_active') && is_plugin_active('woocommerce/woocommerce.php'));
    }

    function qtv_get_latest_products(WP_REST_Request $request) {
        if(!$this->is_woocommerce_active()){
            return $this->success([]);
        }
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

    function get_single_product($request) {
        $id = (int) $request['id'];

        if(!$this->is_woocommerce_active()){
            return $this->success(null);
        }

        if ($id === 0) {
            $args = array(
                'post_type'      => 'product',
                'posts_per_page' => 1,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'post_status'    => 'publish',
            );
            $latest = get_posts($args);

            if (empty($latest)) {
                return $this->success(null);
            }

            $id = $latest[0]->ID;
        }

        if (!$id || get_post_type($id) !== 'product') {
            return $this->success(null);
        }

        $product = wc_get_product($id);
        if (!$product) {
            return $this->success(null);
        }

        try {
            $data = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'slug' => $product->get_slug(),
                'price' => wc_price($product->get_price()),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'stock_status' => $product->get_stock_status(),
                'description' => $product->get_description(),
                'short_description' => $product->get_short_description(),
                'permalink' => $product->get_permalink(),
                'image' => wp_get_attachment_url($product->get_image_id()),
                'gallery' => array_map(function ($img_id) {
                    return wp_get_attachment_url($img_id);
                }, $product->get_gallery_image_ids()),
            );
            return $this->success($data);
        } catch (Exception $e) {
            // Trả lỗi nếu có exception
            return new WP_Error('product_error', $e->getMessage(), array('status' => 500));
        }
    }

    function filter_products_by_name($request) {
        global $wpdb;

        $name = sanitize_text_field($request->get_param('name'));
        if (empty($name)) {
            return $this->success([]);
        }

        if(!$this->is_woocommerce_active()){
            return $this->success([]);
        }

        // Query trực tiếp theo post_title gần đúng
        $like = '%' . $wpdb->esc_like($name) . '%';
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'product' 
                AND post_status = 'publish' 
                AND post_title LIKE %s 
                ORDER BY post_date DESC 
                LIMIT 100", // giới hạn 20 sản phẩm
                $like
            )
        );

        if (empty($ids)) {
            return $this->success([]);
        }

        $products = [];
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if ($product) {
                $products[] = $this->build_product_response($product);
            }
        }

        return $this->success($products);
    }

    public function get_product_categories($request){
        if(!$this->is_woocommerce_active()){
            return $this->success([]);
        }

        $per_page = $request->get_param('per_page') ?: 5;
        $args = array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'number' => $per_page,
            'orderby' => 'id',
            'order' => 'DESC',
        );

        $categories = get_terms($args);

        if (is_wp_error($categories) || empty($categories)) {
            return $this->success([]);
        }

        $category_data = array();
        foreach ($categories as $category) {
            $category_data[] = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'url' => get_term_link($category),
                'image' => wp_get_attachment_url(get_term_meta($category->term_id, 'thumbnail_id', true)),
                'product_count' => $category->count,
            );
        }

        return $this->success($category_data);
    }

    public function get_product_tags($request){
        if(!$this->is_woocommerce_active()){
            return $this->success([]);
        }

        $per_page = $request->get_param('per_page') ?: 5;
        $args = array(
            'taxonomy' => 'product_tag',
            'hide_empty' => false,
            'number' => $per_page,
            'orderby' => 'id',
            'order' => 'DESC',
        );

        $tags = get_terms($args);

        if (is_wp_error($tags ) || empty($tags)) {
            return $this->success([]);
        }

        $tag_data = array();
        foreach ($tags as $tag) {
            $tag_data[] = array(
                'id' => $tag->term_id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'description' => $tag->description,
                'url' => get_term_link($tag),
                'product_count' => $tag->count,
            );
        }

        return $this->success($tag_data);
    }


    function build_product_response($product) {
        return array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'slug' => $product->get_slug(),
            'price' => wc_price($product->get_price()),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'stock_status' => $product->get_stock_status(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'permalink' => $product->get_permalink(),
            'image' => wp_get_attachment_url($product->get_image_id()),
            'gallery' => array_map(function ($img_id) {
                return wp_get_attachment_url($img_id);
            }, $product->get_gallery_image_ids()),
        );
    }

}
