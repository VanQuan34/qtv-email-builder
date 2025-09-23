<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once plugin_dir_path(__FILE__) . './qtv_response_helper.php';
require_once plugin_dir_path(__FILE__) . './qtv_category.php';
require_once plugin_dir_path(__FILE__) . './qtv_media.php';
require_once plugin_dir_path(__FILE__) . './qtv_send_test.php';
require_once plugin_dir_path(__FILE__) . './qtv_wordpress.php';
require_once plugin_dir_path(__FILE__) . './qtv_woocommerce.php';


class QTV_Service_Email {
    use QTV_Response_Helper;

    private $post_type = 'email_template';
    private $taxonomy = 'template_category';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        new QTV_Category_Service();
        new QTV_Media_Service();
        new QTV_SendTest_Service();
        new QTV_Woocommerce_Service();
        new QTV_WordPress_Service();
    }

    public function qtv_permission_check(){
        if ( ! is_user_logged_in() ) {
        return new WP_Error(
            '401',
            __( 'Not Logged in', 'qtv_email_builder' ),
            [ 'status' => 401 ]
        );
        }
        return true;
    }

    public function register_routes() {
        register_rest_route('qtv-email/v1', '/templates', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'list_templates'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'  => 'POST',
                'callback' => [$this, 'create_template'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods'  => 'DELETE',
                'callback' => [$this, 'delete_template'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route('qtv-email/v1', '/templates/favorite', [
            [
                'methods'  => 'PUT',
                'callback' => [$this, 'set_template_favorite'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('qtv-email/v1', '/templates/sample', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'list_templates_sample'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('qtv-email/v1', '/templates/(?P<id>\d+)', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'get_template'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'  => 'PUT',
                'callback' => [$this, 'update_template'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route('qtv-email/v1', '/templates/actions/count', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'count_templates'],
                'permission_callback' => '__return_true',
            ],
        ]);

         register_rest_route('qtv-email/v1', '/templates/sample/actions/count', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'count_templates_sample'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('qtv-email/v1', '/templates/actions/count-all', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'count_templates'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('qtv-email/v1', '/templates/actions/count/categories', [
            [
                'methods'  => 'POST',
                'callback' => [$this, 'count_templates_by_categories'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('qtv-email/v1', '/posts', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'get_summary_posts'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function check_permission() {
        return true; //current_user_can('edit_posts');
    }

     /**
     * Giúp decode content nếu nó là JSON string
     */
    private function maybe_decode_json($content) {
        $decoded = json_decode($content, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $content;
    }

    /**
     * Generate UUID v4
     */
    private function generate_uuid() {
        return wp_generate_uuid4();
    }

    public function list_templates($request) {
        $per_page   = (int) $request->get_param('per_page') ?: 24;
        $page       = (int) $request->get_param('page') ?: 1;
        $search     = sanitize_text_field( $request->get_param('search') ?: '' );
        $categories = $request->get_param('categories');
        $isFavorite = filter_var( $request->get_param('is_favorite') ?? false, FILTER_VALIDATE_BOOLEAN );

        // convert categories string "3,4" -> [3,4]
        $categories = !empty($categories) 
        ? array_map('intval', explode(',', $categories)) 
        : [];

        try {
            $args = [
                'post_type'      => $this->post_type,
                'post_status'    => 'publish',
                'posts_per_page' => $per_page,
                'paged'          => $page,
                'meta_query'     => [
                    'relation' => 'AND',
                    [
                        'relation' => 'OR',
                        [
                            'key'     => 'sample',
                            'value'   => '0',
                            'compare' => '='
                        ],
                        [
                            'key'     => 'sample',
                            'value'   => '',
                            'compare' => '='
                        ],
                        [
                            'key'     => 'sample',
                            'compare' => 'NOT EXISTS'
                        ],
                    ],
                ]
            ];

            if ($isFavorite) {
                $args['meta_query'][] = [
                    'key'     => 'is_favorite',
                    'value'   => '1',
                    'compare' => '='
                ];

                $args['meta_query'][] = [
                    'key'     => 'sample',
                    'value'   => '1',
                    'compare' => '!='
                ];
            }

            if (!empty($search)) {
                $args['s'] = $search;
            }

            if (!empty($categories)) {
                $args['tax_query'] = [
                    [
                        'taxonomy' => 'template_category',
                        'field'    => 'term_id',
                        'terms'    =>  $categories,
                        'operator' => 'IN',
                    ]
                ];
            }
            
            $posts = get_posts($args);
            $data = [];
        
            foreach ($posts as $post) {
                $meta = $this->get_meta_fields($post->ID);
        
                // Nếu content là JSON string thì decode (tuỳ bạn có dùng JSON không)
                if (!empty($meta['content'])) {
                    $decoded = json_decode($meta['content'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $meta['content'] = $decoded;
                    }
                }
        
                // base fields
                $item = [
                    'id'      => $post->ID,
                    'title'   => $post->post_title,
                    // 'content' => $post->post_content,
                ];
        
                // merge meta fields cùng cấp
                $item = array_merge($item, $meta);
        
                $data[] = $item;
            }
        
            // return rest_ensure_response($data);
            return $this->success($data, "Request thành công");
        } catch(error){
            return $this->error("Có lỗi phía server", 500, null);
        }
    }

    public function list_templates_sample($request){
        $per_page = (int) ($request['per_page'] ?? 24);
        $page     = (int) ($request['page'] ?? 1);
        $search   = sanitize_text_field($request['search'] ?? '');
        $isFavorite = filter_var($request['is_favorite'] ?? false, FILTER_VALIDATE_BOOLEAN); 

        try {
            $meta_query = [
                'key'     => 'sample',
                'value'   => '1',
                'compare' => '='
            ];

            if ($isFavorite) {
                $meta_query[] = [
                    'key'     => 'is_favorite',
                    'value'   => '1',
                    'compare' => '='
                ];
            }

            if (count($meta_query) > 1) {
                $meta_query['relation'] = 'AND';
            }
            $args = [
                'post_type'      => $this->post_type,
                'post_status'    => 'publish',
                'posts_per_page' => $per_page,
                'paged'          => $page,
                'meta_query'     => [
                    'relation' => 'AND',
                    [
                        'key'     => 'sample',
                        'value'   => '1',
                        'compare' => '='
                    ],
                ]
            ];

            if ($isFavorite) {
                $args['meta_query'][] = [
                    'key'     => 'is_favorite',
                    'value'   => '1',
                    'compare' => '='
                ];
            }

            if (!empty($search)) {
                $args['s'] = $search;
            }
            
            $posts = get_posts($args);
            $data = [];
        
            foreach ($posts as $post) {
                $meta = $this->get_meta_fields($post->ID);
        
                // Nếu content là JSON string thì decode (tuỳ bạn có dùng JSON không)
                if (!empty($meta['content'])) {
                    $decoded = json_decode($meta['content'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $meta['content'] = $decoded;
                    }
                }
        
                // base fields
                $item = [
                    'id'      => $post->ID,
                    'title'   => $post->post_title,
                    // 'content' => $post->post_content,
                ];
        
                // merge meta fields cùng cấp
                $item = array_merge($item, $meta);
        
                $data[] = $item;
            }
        
            // return rest_ensure_response($data);
            return $this->success($data, "Request success");
        } catch(error){
            return $this->error("Error server", 500, null);
        }
    }

    public function count_templates($request){
        $search   = sanitize_text_field($request['search'] ?? '');
        $category = sanitize_text_field($request['category'] ?? '');
        $isFavorite = filter_var($request['is_favorite'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $args = [
            'post_type'      => $this->post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1, // lấy hết để đếm
            'fields'         => 'ids', // chỉ lấy ID để tối ưu
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [
                        'key'     => 'sample',
                        'value'   => '0',
                        'compare' => '='
                    ],
                    [
                        'key'     => 'sample',
                        'value'   => '',
                        'compare' => '='
                    ],
                    [
                        'key'     => 'sample',
                        'compare' => 'NOT EXISTS'
                    ],
                ],
            ]
        ];

        if ($isFavorite) {
            $args['meta_query'][] = [
                'key'     => 'is_favorite',
                'value'   => '1',
                'compare' => '='
            ];
        }

        if (!empty($search)) {
            $args['s'] = $search;
        }

        if (!empty($category)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'template_category',
                    'field'    => 'slug',
                    'terms'    => $category,
                ]
            ];
        }

        $query = new WP_Query($args);

        $count = (int) $query->found_posts;

        return $this->success([
            'count' => $count,
            'type'  => 'public',
            'total_sample_email_template' => $count
        ]);
    }

    public function count_templates_sample($request){
        $search   = sanitize_text_field($request['search'] ?? '');
        $isFavorite = filter_var($request['is_favorite'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $args = [
            'post_type'      => $this->post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1, // lấy hết để đếm
            'fields'         => 'ids', // chỉ lấy ID để tối ưu
            'meta_query'     => [
                [
                    'key'     => 'sample',
                    'value'   => '1',   // hoặc giá trị bạn muốn lọc
                    'compare' => '='
                ]
            ]
        ];

        if ($isFavorite) {
            $args['meta_query'][] = [
                'key'     => 'is_favorite',
                'value'   => '1',
                'compare' => '='
            ];
        }

        if (!empty($search)) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);

        $count = (int) $query->found_posts;

        return $this->success([
            'count' => $count,
            'type'  => 'public',
            'total_sample_email_template' => $count
        ]);
    }

    public function count_templates_by_categories(WP_REST_Request $request) {
        $body = $request->get_json_params();

        $category_ids = !empty($body['category_ids']) ? array_map('intval', (array) $body['category_ids']) : [];
        $search       = sanitize_text_field($request->get_param('search'));
        $is_favorite = $request->get_param('is_favorite');
        $is_favorite = ($is_favorite === 'true'); // convert sang bool

        if (empty($category_ids)) {
            return $this->error("Missing category_ids", 400);
        }

        $results = [];

        foreach ($category_ids as $cat_id) {
            $args = [
                'post_type'      => $this->post_type,
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'posts_per_page' => -1,
                'tax_query'      => [
                    [
                        'taxonomy' => 'template_category',
                        'field'    => 'term_id',
                        'terms'    => $cat_id,
                    ]
                ],
                'meta_query'     => [
                        'relation' => 'AND',
                        [
                            'relation' => 'OR',
                            [
                                'key'     => 'sample',
                                'value'   => '0',
                                'compare' => '='
                            ],
                            [
                                'key'     => 'sample',
                                'value'   => '',
                                'compare' => '='
                            ],
                            [
                                'key'     => 'sample',
                                'compare' => 'NOT EXISTS'
                            ],
                        ],
                    ]

            ];
            

            if (!empty($search)) {
                $args['s'] = $search;
            }

            if ($is_favorite) {
                $args['meta_query'][] = [
                    'key'     => 'is_favorite',
                    'value'   => '1',
                    'compare' => '='
                ];
            }

            $query = new WP_Query($args);
            $results[] = [
                'category_id'    => $cat_id,
                'type'  => 'public',
                'count' => (int) $query->found_posts
            ];
        }

        return $this->success($results);
    }

    

    public function get_template($request) {

        $post_id = (int)$request['id'];
        $post    = get_post($post_id);

        if (!$post || $post->post_type !== $this->post_type) {
            return $this->error("Template not exist.", 404);
        }

        // Lấy meta fields
        $meta = $this->get_meta_fields($post_id);
        // Lấy categories từ taxonomy template_category
        $post_categories = wp_get_post_terms($post_id, $this->taxonomy, ['fields' => 'ids']);
        if (is_wp_error($post_categories)) {
            $post_categories = []; // Xử lý lỗi, trả về mảng rỗng
        }

        $meta['categories'] = $post_categories;
        
        // if($meta && $meta['sample'] == '1'){
        //     return $this->error("Mẫu không tồn tại", 401);
        // }
        
        // Gom chung data giống list_templates
        $data = array_merge(
            [
                'id'      => $post->ID,
                'title'   => $post->post_title,
                'content' => $this->maybe_decode_json($post->post_content),
                'body'    => [
                    'body'       => $meta['email_content'] ?? '',
                    'style'      => $meta['style'] ?? '',
                    'data'       => isset($meta['email_data']) ? json_decode($meta['email_data'], true) : [],
                    'breakPoints'=> [],
                    'fontItems'  => [
                        [
                            'fontType' => [400, 700],
                            'html'     => "<span style=\"font-family: 'Georgia, Times, 'Times New Roman', serif\">Georgia</span>",
                            'id'       => 'Georgia',
                            'name'     => 'Georgia',
                            'style'    => '',
                            'value'    => "Georgia, Times, 'Times New Roman', serif",
                        ],
                    ],
                ],
            ],
            $meta
        );


        return $this->success($data);
    }

    public function create_template($request) {
        $params = $request->get_json_params();
        $params['template_id'] = $this->generate_uuid();
        $params['session'] = $this->generate_uuid();
        $params['sample'] = '0';

        $html = $params['body']['body'];
        $style = $params['body']['style'];
        $data = $params['body']['data'];

        $array = [
            "body" => $html,
            "style" => $style,
            "data" => $data
        ];

        $params['email_content'] = $html;
        $params['style'] = wp_slash($style);
        $params['email_data'] = wp_slash(json_encode($data, JSON_UNESCAPED_UNICODE));

        $post_id = wp_insert_post([
            'post_type'    => $this->post_type,
            'post_title'   => sanitize_text_field($params['title'] ?? $params['name'] ?? 'Không có tiêu đề'),
            'post_content' => json_encode($array, JSON_UNESCAPED_UNICODE),
            'post_status'  => 'publish'
        ]);

        if (is_wp_error($post_id)) {
            // return new WP_Error('create_failed', 'Không tạo được template', ['status' => 500]);
            return $this->error("Có vấn đề khi tạo", 500);
        }

        if (!empty($params['categories']) && is_array($params['categories'])) {
            $categories = array_map('intval', $params['categories']); // Sanitize: đảm bảo ID là số nguyên
            $result = wp_set_post_terms($post_id, $categories, $this->taxonomy, false);
            if (is_wp_error($result)) {
                return $this->error("Không thể gán categories cho template", 500);
            }
        }

        $this->save_meta_fields($post_id, $params);

        return $this->get_template(['id' => $post_id]);
    }

    public function update_template($request) {
        $post_id = (int)$request['id'];
        $params = $request->get_json_params();

        if (!get_post($post_id)) {
            return new WP_Error('not_found', 'Template không tồn tại', ['status' => 404]);
        }

        $html = $params['body']['body'];
        $style = $params['body']['style'];
        $data = $params['body']['data'];

        $params['email_content'] = wp_slash($html);
        $params['style'] = wp_slash($style);
        $params['email_data'] = wp_slash(json_encode($data, JSON_UNESCAPED_UNICODE));

        $array = [
            "body" => $html,
            "style" => $style,
            "data" => $data
        ];

        $updated_id = wp_update_post([
            'ID'           => $post_id,
            'post_title'   => sanitize_text_field($params['title'] ?? $params['name'] ?? ''),
            'post_content' => json_encode($array, JSON_UNESCAPED_UNICODE),
        ], true);

        if (is_wp_error($updated_id)) {
            return new WP_Error('update_failed', 'Không update được template', ['status' => 500]);
        }

        $this->save_meta_fields($post_id, $params);

        return $this->get_template(['id' => $post_id]);
    }

    public function delete_template($request) {
        $ids_param = sanitize_text_field($request['ids'] ?? '');

        if (!get_post($ids_param)) {
            return $this->error("Template không tồn tại", 400);
        }

        $ids = array_filter(array_map('intval', explode(',', $ids_param)));

        if (empty($ids)) {
            return $this->error("Template không tồn tại", 400);
        }

        $deleted = [];
        $errors  = [];

         foreach ($ids as $post_id) {
            $post = get_post($post_id);

            if (!$post || $post->post_type !== $this->post_type) {
                $errors[] = [
                    'id'    => $post_id,
                    'error' => 'Template không tồn tại hoặc không đúng post_type'
                ];
                continue;
            }

            $result = wp_delete_post($post_id, true);

            if ($result) {
                $deleted[] = $post_id;
            } else {
                $errors[] = [
                    'id'    => $post_id,
                    'error' => 'Không thể xóa template'
                ];
            }
        }

        return $this->success(true);
    }

    public function set_template_favorite($request){
        $params = $request->get_json_params();
        $temp_id = (int) $params['email_id'];
        $is_favorite = $params['is_favorite'] ?: false;

        if (is_wp_error($temp_id)) {
            // return new WP_Error('create_failed', 'Không tạo được template', ['status' => 500]);
            return $this->error("Có vấn đề khi tạo", 500);
        }

        $this->save_meta_fields($temp_id, $params);
        return $this->success(true);
    }

    public function get_summary_posts($request) {
        try{
        // 📌 3 bài viết mới nhất
            $posts = get_posts([
                'post_type'      => 'post',
                'posts_per_page' => 3,
                'post_status'    => 'publish'
            ]);
            $latest_posts = [];
            foreach ($posts as $post) {
                $latest_posts[] = [
                    'id'    => $post->ID,
                    'title' => get_the_title($post->ID),
                    'link'  => get_permalink($post->ID),
                    'excerpt' => wp_trim_words(get_the_excerpt($post->ID), 15, '...'),
                    'featured_image' => get_the_post_thumbnail_url($post->ID, 'full') ?: ''
                ];
            }

            // 📌 5 tags
            $tags = get_terms([
                'taxonomy'   => 'post_tag',
                'number'     => 5,
                'hide_empty' => false
            ]);
            $tags_data = [];
            foreach ($tags as $tag) {
                $tags_data[] = [
                    'id'   => $tag->term_id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'url'  => get_term_link($tag->term_id)
                ];
            }

            // 📌 5 categories
            $cats = get_terms([
                'taxonomy'   => 'category',
                'number'     => 5,
                'hide_empty' => true
            ]);
            $cats_data = [];
            foreach ($cats as $cat) {
                $cats_data[] = [
                    'id'   => $cat->term_id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'url'  => get_term_link($cat->term_id)
                ];
            }

            // 📌 Trả về JSON gộp
            $data = [
                'latest_posts' => $latest_posts,
                'tags'         => $tags_data,
                'categories'   => $cats_data
            ];
            return $this->success($data);
        } catch(error){
            return $this->error("Có lỗi phía server", 500, null);
        }
    }

    private function save_meta_fields($post_id, $params) {
        $fields = [
            "categories",
            "created_by",
            "created_time",
            "description",
            "template_id",
            "is_favorite",
            "merchant_id",
            "name",
            "session",
            "small_thumbnail",
            "status_code",
            "thumbnail",
            'sample',
            'email_content',
            'style',
            'email_data',
            "updated_time"
        ];

        foreach ($fields as $field) {
            if (isset($params[$field])) {
                if ($field === 'email_content' || $field === 'style' || $field === "email_data") {
                    update_post_meta($post_id, $field, $params[$field]);
                } else {
                    update_post_meta($post_id, $field, sanitize_text_field($params[$field]));
                }
            }
        }

        // lưu nguyên JSON nếu cần
        if (!empty($params)) {
            // update_post_meta($post_id, '_raw_json', wp_slash(json_encode($params)));
        }
    }

    private function get_meta_fields($post_id) {
        $fields = [
            "categories","created_by","created_time","description","template_id",
            "is_favorite","merchant_id","name","session","small_thumbnail",
            "status_code","thumbnail","updated_time", "sample", "email_content", 'style', "email_data", "_raw_json"
        ];
        $meta = [];
        foreach ($fields as $f) {
            $meta[$f] = get_post_meta($post_id, $f, true);
        }
        return $meta;
    }
}
