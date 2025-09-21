<?php
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . './qtv_response_helper.php';

class QTV_Category_Service {
    use QTV_Response_Helper;

    private $taxonomy = 'template_category';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Đăng ký endpoint cho taxonomy Category
     */
    public function register_routes() {
        register_rest_route('qtv-email/v1', '/categories', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'list_categories'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'  => 'POST',
                'callback' => [$this, 'create_category'],
                'permission_callback' => '__return_true',
            ]
        ]);

        register_rest_route('qtv-email/v1', '/categories/(?P<id>\d+)', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'get_category'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'  => 'PUT',
                'callback' => [$this, 'update_category'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'  => 'DELETE',
                'callback' => [$this, 'delete_category'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    /**
     * Lấy danh sách category
     */
    public function list_categories($request) {
        $terms = get_terms([
            'taxonomy'   => $this->taxonomy,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            return $this->serverError("Không lấy được danh sách category");
        }

        $data = [];
        foreach ($terms as $term) {
            $data[] = [
                'id'          => $term->term_id,
                'name'        => $term->name,
                'slug'        => $term->slug,
                'description' => $term->description,
                'count'       => $term->count,
            ];
        }

        return $this->success($data, "Lấy danh sách category thành công");
    }

    /**
     * Tạo category mới
     */
    public function create_category($request) {
        $params = $request->get_json_params();

        $name = sanitize_text_field($params['name'] ?? '');
        if (empty($name)) {
            return $this->error("Tên category không được để trống", 400);
        }

        $result = wp_insert_term($name, $this->taxonomy, [
            'slug'        => sanitize_title($params['slug'] ?? $name),
            'description' => sanitize_textarea_field($params['description'] ?? ''),
        ]);

        if (is_wp_error($result)) {
            return $this->serverError("Không tạo được category", $result->get_error_messages());
        }

        return $this->get_category(['id' => $result['term_id']]);
    }

    /**
     * Lấy chi tiết category
     */
    public function get_category($request) {
        $term_id = (int)$request['id'];
        $term = get_term($term_id, $this->taxonomy);

        if (!$term || is_wp_error($term)) {
            return $this->error("Category không tồn tại", 404);
        }

        $data = [
            'id'          => $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description,
            'count'       => $term->count,
        ];

        return $this->success($data, "Lấy chi tiết category thành công");
    }

    /**
     * Cập nhật category
     */
    public function update_category($request) {
        $term_id = (int)$request['id'];
        $params = $request->get_json_params();

        $result = wp_update_term($term_id, $this->taxonomy, [
            'name'        => sanitize_text_field($params['name'] ?? ''),
            'slug'        => sanitize_title($params['slug'] ?? ''),
            'description' => sanitize_textarea_field($params['description'] ?? ''),
        ]);

        if (is_wp_error($result)) {
            return $this->serverError("Không cập nhật được category", $result->get_error_messages());
        }

        return $this->get_category(['id' => $term_id]);
    }

    /**
     * Xoá category
     */
    public function delete_category($request) {
        $term_id = (int)$request['id'];
        $result = wp_delete_term($term_id, $this->taxonomy);

        if (is_wp_error($result)) {
            return $this->serverError("Không xoá được category", $result->get_error_messages());
        }

        return $this->success([], "Xoá category thành công");
    }
}
