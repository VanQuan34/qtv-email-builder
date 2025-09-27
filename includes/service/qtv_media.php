<?php
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . './qtv_response_helper.php';

class QTV_Media_Service {
    use QTV_Response_Helper;

    private $taxonomy = 'qtv_template_category';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Đăng ký endpoint cho taxonomy Category
     */
    public function register_routes() {
        register_rest_route('qtv-email/v1', '/media/upload', [
            [
                'methods'  => 'POST',
                'callback' =>  [$this, 'email_builder_upload_media'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('qtv-email/v1', '/media/file/actions/delete', [
            [
                'methods'  => 'POST',
                'callback' =>  [$this, 'email_builder_delete_media'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('qtv-email/v1', '/media/list', [
            [
                'methods'  => 'GET',
                'callback' =>  [$this, 'email_builder_list_media'],
                'permission_callback' => '__return_true',
                'args' => array(
                    'per_page' => array(
                        'type' => 'integer',
                        'default' => 10,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => function ($param) {
                            return $param > 0 && $param <= 100; // Giới hạn perpage từ 1 đến 100
                        },
                    ),
                    'page' => array(
                        'type' => 'integer',
                        'default' => 1,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => function ($param) {
                            return $param > 0; // Đảm bảo paged là số dương
                        },
                    ),
                ),
            ],
        ]);
    }

     // Hàm xử lý upload media
    function email_builder_upload_media(WP_REST_Request $request) {
        try {
            $base64_data = $request->get_param('base64_data');
            $file_name   = $request->get_param('file_name');
            $replace_url = $request->get_param('replace_url') ?: '';

            // Nếu có base64_data thì xử lý như cũ
            if (!empty($base64_data) && !empty($file_name)) {
                $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $base64_data);
                $binary = base64_decode($base64, true);

                if ($binary === false) {
                    return new WP_REST_Response([
                        'code' => 400,
                        'message' => 'Invalid base64 data',
                        'data' => null
                    ], 400);
                }

                $upload_dir = wp_upload_dir();
                $file_path  = $upload_dir['path'] . '/' . sanitize_file_name($file_name) . '.png';

                if (!file_put_contents($file_path, $binary)) {
                    return new WP_REST_Response([
                        'code' => 500,
                        'message' => 'Failed to save temporary file',
                        'data' => null
                    ], 500);
                }

                $file_type   = 'image/png';
                $attachment  = [
                    'post_mime_type' => $file_type,
                    'post_title'     => sanitize_file_name($file_name),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                ];

                $attachment_id = wp_insert_attachment($attachment, $file_path);
                if (is_wp_error($attachment_id)) {
                    return new WP_REST_Response([
                        'code' => 500,
                        'message' => $attachment_id->get_error_message(),
                        'data' => null
                    ], 500);
                }

                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
                wp_update_attachment_metadata($attachment_id, $attachment_data);

                $file_url = wp_get_attachment_url($attachment_id);

                return new WP_REST_Response([
                    'code' => 200,
                    'message' => 'Upload success (base64)',
                    'data' => [
                        'id'  => $attachment_id,
                        'url' => $file_url
                    ]
                ], 200);
            }

            // Nếu không có base64_data => xử lý file upload từ FormData
            if (!empty($_FILES['file'])) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');

                $uploaded = wp_handle_upload($_FILES['file'], ['test_form' => false]);
                if (isset($uploaded['error'])) {
                    return new WP_REST_Response([
                        'code' => 500,
                        'message' => $uploaded['error'],
                        'data' => null
                    ], 500);
                }

                $file_type  = $uploaded['type'];
                $file_path  = $uploaded['file'];
                $file_url   = $uploaded['url'];
                $file_title = sanitize_file_name($request->get_param('filename') ?: basename($file_path));

                $attachment = [
                    'post_mime_type' => $file_type,
                    'post_title'     => $file_title,
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                ];

                $attachment_id = wp_insert_attachment($attachment, $file_path);
                if (is_wp_error($attachment_id)) {
                    return new WP_REST_Response([
                        'code' => 500,
                        'message' => $attachment_id->get_error_message(),
                        'data' => null
                    ], 500);
                }

                $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
                wp_update_attachment_metadata($attachment_id, $attachment_data);

                return new WP_REST_Response([
                    'code' => 200,
                    'message' => 'Upload success (file)',
                    'data' => [
                        'id'  => $attachment_id,
                        'url' => $file_url
                    ]
                ], 200);
            }

            // Nếu không có cả base64_data lẫn file upload
            return new WP_REST_Response([
                'code' => 400,
                'message' => 'Missing base64_data or file',
                'data' => null
            ], 400);

        } catch (Exception $e) {
            return new WP_REST_Response([
                'code' => 500,
                'message' => $e->getMessage() ?: 'Upload failed',
                'data' => null
            ], 500);
        }
    }


    public function email_builder_list_media(WP_REST_Request $request){
        try {
            // Lấy tham số từ request
            $per_page = $request->get_param('per_page');
            $paged = $request->get_param('page');
            $search = $request->get_param('search');
    
            // Hàm phụ để lấy dữ liệu media với các trường yêu cầu
            $get_media_data = function ($attachment_id) {
                $attachment = get_post($attachment_id);
                if (!$attachment || $attachment->post_type !== 'attachment') {
                    return null;
                }
    
                $file_url = wp_get_attachment_url($attachment_id);
                $meta = wp_get_attachment_metadata($attachment_id);
                $file_path = get_attached_file($attachment_id);
                
                $file_size_mb = $file_path && file_exists($file_path) ? round(filesize($file_path) / 1024 / 1024, 1) : 0;
                $origin_capacity = $file_size_mb >= 1
                    ? round($file_size_mb, 1) . ' MB'
                    : round(filesize($file_path) / 1024 , 1) . ' KB';
    
                return [
                    'id' => (string)$attachment_id,
                    'created_time' => get_post_meta($attachment_id, '_created_time', true) ?: get_the_date('c', $attachment_id),
                    'filename' => basename($file_url),
                    'mimetype' => $attachment->post_mime_type,
                    'origin_capacity' => $origin_capacity,
                    'origin_height' => $meta['height'] ?? 0,
                    'origin_url' => $file_url ?: '',
                    'origin_width' => $meta['width'] ?? 0,
                    'display' => 'list',
                    'do_not_delete' => true,
                ];
            };
    
            // Lấy danh sách media với phân trang
            $args = array(
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => $per_page,
                'paged' => $paged,
            );

            if (!empty($search)) {
                $args['s'] = $search;
            }
    
            $query = new WP_Query($args);
            $media_items = array();
    
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $media_data = $get_media_data(get_the_ID());
                    if ($media_data) {
                        $media_items[] = $media_data;
                    }
                }
            }
    
            // Reset post data
            wp_reset_postdata();
    
            return $this->success($media_items);
    
        } catch (Exception $e) {
            return $this->error($e->getMessage() ?: 'Getting media list failed', 500);
        }
    }

    public function email_builder_delete_media(WP_REST_Request $request) {
        $urls = $request->get_param('urls');

        if (empty($urls) || !is_array($urls)) {
            return new WP_Error(
                'invalid_param',
                'The "urls" parameter must be an array and not empty.',
                ['status' => 400]
            );
        }

        $deleted = [];
        $errors  = [];

        foreach ($urls as $url) {
            $attachment_id = attachment_url_to_postid($url);

            if ($attachment_id) {
                $result = wp_delete_attachment($attachment_id, true);
                if ($result) {
                    $deleted[] = [
                        'url' => $url,
                        'id'  => $attachment_id,
                        'status' => 'deleted'
                    ];
                } else {
                    $errors[] = [
                        'url' => $url,
                        'error' => 'Cannot delete attachment'
                    ];
                }
            } else {
                $errors[] = [
                    'url' => $url,
                    'error' => 'Attachment ID not found'
                ];
            }
        }

        return [
            'deleted' => $deleted,
            'errors'  => $errors,
        ];
    }

}
