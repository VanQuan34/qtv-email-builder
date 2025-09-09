<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once plugin_dir_path(__FILE__) . './qtv_response_helper.php';

class QTV_Service_Email {
    use QTV_Response_Helper;

    private $post_type = 'email_template';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
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

        register_rest_route('qtv-email/v1', '/upload-media', [
            [
                'methods'  => 'POST',
                'callback' =>  [$this, 'email_builder_upload_media'],
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
            return $this->success($data, "Request thành công");
        } catch(error){
            return $this->error("Có lỗi phía server", 500, null);
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
        $params       = $request->get_json_params();
        $category_ids = !empty($params['category_ids']) ? array_map('intval', (array) $params['category_ids']) : [];
        $search       = sanitize_text_field($params['search'] ?? '');

        if (empty($category_ids)) {
            return $this->error("Thiếu category_ids", 400);
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
            return $this->error("Mẫu không tồn tại", 404);
        }

        // Lấy meta fields
        $meta = $this->get_meta_fields($post_id);
        
        // $data = array_merge(
        //     [
        //         'id'      => $post->ID,
        //         'title'   => $post->post_title,
        //         "body" => [
        //             "body" => "\n    <body class=\"body\" style=\"margin: 0; padding: 0; -webkit-text-size-adjust: none; text-size-adjust: none;\">\n      <table id=\"comp-f062cc6221a\" mo-type=\"page\" class=\"nl-container\" width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" \n        style=\"mso-table-lspace:0pt;mso-table-rspace:0pt;background-color:#E6E7EA;background-size:contain;background-repeat:repeat;background-position:right center;\">\n        <tbody>\n          <tr>\n            <td class=\"mo-eb-page-content\" id=\"cont-062cc6221a4\">\n\n              <!--HEADER -->\n               \n        <table id=\"comp-959847b69bb\" mo-type=\"header\" \n          class=\"row\" \n          align=\"center\" width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" \n          role=\"presentation\" style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-size: auto;\">\n          <tbody>\n            <tr>\n              <td>\n                <!-- Row content -->\n                <table class=\"row-content\" align=\"center\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" \n                  style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 650px; margin: 0 auto; background-color: #ffffff;\" width=\"650\">\n                  <tbody>\n                    <tr>\n                      <td class=\"column mo-eb-content mo-eb-content-body\" id=\"comp-59847b69bbd\" width=\"100%\" \n                        style=\"mso-table-lspace:0pt;mso-table-rspace:0pt;font-weight:400;text-align:left;vertical-align:top;border-top:0px;border-right:0px;border-bottom:0px;border-left:0px;\n                          padding-bottom:24px;padding-left:24px;padding-right:24px;padding-top:24px;\">\n                        <!-- Image block -->\n                        <table id=\"comp-9847b69bbdb\" mo-type=\"image\" class=\"image_block\" width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt;\">\n                          <tr>  \n                            <td class=\"pad\" style=\"\">\n                              <div class=\"alignment\" align=\"center\" \n                                style=\"line-height:102px; height: 102px; width:100%; \n                                        border: solid 1px #E6E7EA; background-color: #F8F9FA;\n                                        display: flex; align-items: center; justify-content: center;\">\n                                <div class=\"image-content\" style=\"width: 64px;\">\n                                  <img src=\"https://ck.mobio.io//images/email-builder/image-outline.svg\" \n                                    style=\"display: block; height: auto; border: 0; width: 100%;\" height=\"auto\" />\n                                </div>\n                              </div>\n                            </td>\n                          </tr>\n                        </table>\n\n                      </td>\n                    </tr>\n                  </tbody>\n                </table>\n\n              </td>\n            </tr>\n          </tbody>\n        </table>\n      \n\n              <!-- CONTENT -->\n              \n        <table id=\"comp-847b69bbdbb\" mo-type=\"content\" \n          class=\"row\" \n          align=\"center\" width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" \n          role=\"presentation\" style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-size: auto;\">\n          <tbody>\n            <tr>\n              <td>\n                <!-- Row content -->\n                <table class=\"row-content\" align=\"center\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" \n                  style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 650px; margin: 0 auto; background-color: #ffffff;\" width=\"650\">\n                  <tbody>\n                    <tr>\n                      <td class=\"column mo-eb-content mo-eb-content-body\" id=\"comp-47b69bbdbbd\" width=\"100%\" \n                        style=\"mso-table-lspace:0pt;mso-table-rspace:0pt;font-weight:400;text-align:center;vertical-align:center;\n                          border-top:0px;border-right:0px;border-bottom:0px;border-left:0px; height: 400px; line-height: 400px;\n                          padding-left: 0px; padding-right: 0px; padding-top: 0px; padding-bottom: 0px;\">\n                          <div class=\"content-empty\" style=\"color: #9CA2AD; font-family: 'Open Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif \">Thực hiện kéo thả để tạo mẫu email</div>\n                      </td>\n                    </tr>\n                  </tbody>\n                </table>\n\n              </td>\n            </tr>\n          </tbody>\n        </table>\n      \n\n              <!-- FOOTER -->\n               \n        <table id=\"comp-7b69bbdbbd6\" mo-type=\"footer\" \n          class=\"row\" \n          align=\"center\" width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" \n          role=\"presentation\" style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-size: auto;\">\n          <tbody>\n            <tr>\n              <td>\n                <!-- Row content -->\n                <table class=\"row-content\" align=\"center\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" \n                  style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 650px; margin: 0 auto; background-color:#F8F9FA;\" width=\"650\">\n                  <tbody>\n                    <tr>\n                      <td class=\"column mo-eb-content mo-eb-content-body\" id=\"comp-f25f062cc62\" width=\"100%\" \n                        style=\"mso-table-lspace:0pt;mso-table-rspace:0pt;font-weight:400;text-align:left;vertical-align:top;\n                          border-top:0px;border-right:0px;border-bottom:0px;border-left:0px; padding-top: 24px; padding-bottom: 24px;\">\n\n                          <!-- Text block -->\n                          <table id=\"comp-d6a4f25f062\" mo-type=\"text\" text-type=\"p\" width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" \n          \n          \n          \n          \n          style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt;\">\n          <tbody>\n            <tr>\n              <td class=\"pad\" style=\"padding-left: 12px; padding-right: 12px; padding-top: 12px; padding-bottom: 12px;\">\n\n                <div class=\"mo-text\" style=\"width:100%; font-family: 'Open Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; font-weight: 400; letter-spacing: normal;\n                  mso-line-height-alt: 18px; line-height: 150%; text-align: center; color: #071631;\">\n                  <p style=\"margin: 0px;\">Bấm vào đây để sử dụng nội dung</p>\n                </div>\n\n              </td>\n            </tr>\n          <tbody>\n        </table>\n\n                          <!-- Social block -->\n                           \n        <table mo-type=\"social\" id=\"comp-9bbdbbd6a4f\" class=\"social_block\" width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt;\">\n          <tbody>\n            <tr>\n              <td class=\"pad\" style=\"padding-top: 12px; padding-bottom: 12px; padding-left: 0px; padding-right: 0px;\">\n                <div class=\"alignment\" align=\"center\">\n                  <table class=\"social-table\" width=\"\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt; display: inline-block;\">\n                    <tbody>\n                      <tr>\n                        <td>\n                          <table class=\"table-icon\" width=\"\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt; display: inline-block;\">\n                            <tbody>\n                              <tr>\n                                <td class=\"pad-icon\" style=\"padding-left: 6px;padding-right: 6px;\">\n                                  <a id='comp-bbdbbd6a4f2' class=\"mo-social-item\" social-type=\"facebook\" href=\"https://www.facebook.com/\" target=\"_blank\">\n                                    <img src=\"https://ck.mobio.io//images/email-builder/social/facebook/color-solid.png\" width=\"32\" height=\"auto\" style=\"display: block; height: auto; border: 0;\">\n                                  </a>\n                                </td>\n                              </tr>\n                            </tbody>\n                          </table>\n\n                          <table class=\"table-icon\" width=\"\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt; display: inline-block;\">\n                            <tbody>\n                              <tr>\n                                <td class=\"pad-icon\" style=\"padding-left: 6px;padding-right: 6px;\">\n                                  <a id='comp-bdbbd6a4f25' class=\"mo-social-item\" social-type=\"instagram\" href=\"https://www.instagram.com/\" target=\"_blank\">\n                                    <img src=\"https://ck.mobio.io//images/email-builder/social/instagram/color-solid.png\" width=\"32\" height=\"auto\" style=\"display: block; height: auto; border: 0;\">\n                                  </a>\n                                </td>\n                              </tr>\n                            </tbody>\n                          </table>\n\n                          <table class=\"table-icon\" width=\"\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt; display: inline-block;\">\n                            <tbody>\n                              <tr>\n                                <td class=\"pad-icon\" style=\"padding-left: 6px;padding-right: 6px;\">\n                                  <a id='comp-dbbd6a4f25f' class=\"mo-social-item\" social-type=\"web\" href=\"https://mobio.io\" target=\"_blank\">\n                                    <img src=\"https://ck.mobio.io//images/email-builder/social/web/color-solid.png\" width=\"32\" height=\"auto\" style=\"display: block; height: auto; border: 0;\">\n                                  </a>\n                                </td>\n                              </tr>\n                            </tbody>\n                          </table>\n\n                          <table class=\"table-icon\" width=\"\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt; display: inline-block;\">\n                            <tbody>\n                              <tr>\n                                <td class=\"pad-icon\" style=\"padding-left: 6px;padding-right: 6px;\">\n                                  <a id='comp-bbd6a4f25f0' class=\"mo-social-item\" social-type=\"phone\"  href=\"0975568330\" target=\"_blank\">\n                                    <img src=\"https://ck.mobio.io//images/email-builder/social/phone/color-solid.png\" width=\"32\" height=\"auto\"  style=\"display: block; height: auto; border: 0;\">\n                                  </a>\n                                </td>\n                              </tr>\n                            </tbody>\n                          </table>\n\n                          <table class=\"table-icon\" width=\"\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt; display: inline-block;\">\n                            <tbody>\n                              <tr>\n                                <td class=\"pad-icon\" style=\"padding-left: 6px;padding-right: 6px;\">\n                                  <a id='comp-bd6a4f25f06' class=\"mo-social-item\" social-type=\"mail\" href=\"support@mobio.io\" target=\"_blank\">\n                                    <img src=\"https://ck.mobio.io//images/email-builder/social/mail/color-solid.png\" width=\"32\" height=\"auto\" style=\"display: block; height: auto; border: 0;\">\n                                  </a>\n                                </td>\n                              </tr>\n                            </tbody>\n                          </table>\n                        </td>\n                      </tr>\n                    </tbody>\n                  </table>\n                </div>\n              </td>\n            </tr>\n          </tbody>\n        </table>\n      \n\n                          <!-- Divider top -->\n                           \n        <table id=comp-4f25f062cc6 class=\"divider_block\" mo-type=\"divider\" width=\"100%\" border=\"0\" cellpadding=\"10\" cellspacing=\"0\" role=\"presentation\" \n          style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt;\">\n          <tbody>\n            <tr>\n              <td class=\"pad\" style=\"padding-top: 12px; padding-right: 24px; padding-bottom: 12px; padding-left:24px;\">\n                <div class=\"alignment\" align=\"center\">\n                  <table class=\"divider-content\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" width=\"100%\" style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt;\">\n                    <tbody>\n                      <tr>\n                        <td class=\"divider_inner\" style=\"font-size: 1px; line-height: 1px; border-top: 1px solid #6A7383;\">\n                          <span style=\"word-break: break-word;\">&hairsp;</span>\n                        </td>\n                      </tr>\n                    </tbody>\n                  </table>\n                </div>\n              </td>\n            </tr>\n          </tbody>\n        </table>\n      \n\n                          <!-- Text block -->\n                          <table id=\"comp-6a4f25f062c\" mo-type=\"text\" text-type=\"p\" width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" \n          \n          \n          \n          \n          style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt;\">\n          <tbody>\n            <tr>\n              <td class=\"pad\" style=\"padding-left: 12px; padding-right: 12px; padding-top: 12px; padding-bottom: 12px;\">\n\n                <div class=\"mo-text\" style=\"width:100%; font-family: 'Open Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; font-weight: 400; letter-spacing: normal;\n                  mso-line-height-alt: 18px; line-height: 150%; text-align: center; color: #6a7383;\">\n                  <p style=\"margin: 0px;\">Copyright &copy; *|CURRENT-YEAR|* *|COMPANY|*, All rights reserved.</p>\n                </div>\n\n              </td>\n            </tr>\n          <tbody>\n        </table>\n                        \n                          <!-- Text block -->\n                          <table id=\"comp-a4f25f062cc\" mo-type=\"text\" text-type=\"p\" width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" \n          \n          \n          \n          \n          style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt;\">\n          <tbody>\n            <tr>\n              <td class=\"pad\" style=\"padding-left: 12px; padding-right: 12px; padding-top: 12px; padding-bottom: 12px;\">\n\n                <div class=\"mo-text\" style=\"width:100%; font-family: 'Open Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; font-weight: 400; letter-spacing: normal;\n                  mso-line-height-alt: 18px; line-height: 150%; text-align: center; color: #6a7383;\">\n                  <p style=\"margin: 0px;\">Để ngừng nhận email thông báo của chúng tôi, vui lòng <a href=\"#\" obj-id=\"link_b69bbdbbd6a\" style=\"color:#226ff5; text-decoration: underline;\">Bấm vào đây</a></p>\n                </div>\n\n              </td>\n            </tr>\n          <tbody>\n        </table>\n\n                      </td>\n                    </tr>\n                  </tbody>\n                </table>\n\n              </td>\n            </tr>\n          </tbody>\n        </table>\n      \n\n              <!-- LOGO -->\n              \n        <table id=\"comp-25f062cc622\" mo-type=\"logo\" class=\"row\" draggable=\"false\"\n          align=\"center\" width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" not-action=\"true\"\n          role=\"presentation\" style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-size: auto;\">\n          <tbody>\n            <tr>\n              <td>\n                <!-- Row content -->\n                <table class=\"row-content\" align=\"center\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" \n                  style=\"mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 650px; margin: 0 auto; width=\"650\">\n                  <tbody>\n                    <tr>\n                      <td align=\"center\" class=\"column\" id=\"comp-5f062cc6221\" width=\"100%\" \n                        style=\"mso-table-lspace:0pt;mso-table-rspace:0pt;text-align:center;vertical-align:center;\n                          border-top:0px;border-right:0px;border-bottom:0px;border-left:0px;\n                          padding-left: 24px; padding-right: 24px; padding-top: 36px; padding-bottom: 36px;\">\n                          <div class=\"alignment\" align=\"center\" style=\"width:100%;\">\n                            <div style=\"width: 108px;\">\n                              <img src=\"https://ck.mobio.io//images/email-builder/logo-mobio-d.png\" style=\"display: block; height: auto; border: 0; width: 108px;\" height=\"auto\" />\n                            </div>\n                          </div>\n                      </td>\n                    </tr>\n                  </tbody>\n                </table>\n\n              </td>\n            </tr>\n          </tbody>\n        </table>\n      \n            </td>\n          </tr>\n        </tbody>\n      </table>\t\n    </body>\n  ",
        //             "data" => [],
        //             "style" => "\n      * {\n        box-sizing: border-box;\n      }\n\n      body {\n        margin: 0;\n        padding: 0;\n      }\n      \n      #MessageViewBody a {\n        color: inherit;\n        text-decoration: none;\n      }\n\n      p {\n        line-height: inherit;\n        margin: 0px;\n      }\n\n      h1,h2,h3 {\n        margin: 0px;\n      }\n\n      .desktop_hide,\n      .desktop_hide table {\n        mso-hide: all;\n        display: none;\n        max-height: 0px;\n        overflow: hidden;\n      }\n\n      .image_block img+div {\n        display: none;\n      }\n\n      sup,\n      sub {\n        font-size: 75%;\n        line-height: 0;\n      }\n\n      .desktop-only {\n        opacity: 1;\n      }\n\n      .mobile-only {\n        opacity: 0.3\n      }\n\n      @media (max-width:680px) {\n        .stack .column {\n          width: 100% !important;\n          display: block;\n        }\n          \n        .row-content {\n          width: 100% !important;\n        }\n\n        .desktop-only {\n          opacity: 0.3;\n        }\n\n        .mobile-only {\n          opacity: 1;\n          display: table !important;\n          line-height: auto !important;\n          max-height: auto !important;\n        }\n\n        .mobile-hide {\n          display: none;\n        }\n\n        .full-width {\n          max-width: 100% !important;\n        }\n\n        h1 {\n          font-size: 36px !important;\n          text-align: center !important;\n        }\n        h2 { \n          font-size: 30px !important;\n          text-align: center !important;\n        }\n        h3 {\n          font-size: 22px !important;\n          text-align: center !important;\n        }\n      }\n  "
        //         ]
        //     ],
        //     $meta
        // );

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

     // Hàm xử lý upload media
    function email_builder_upload_media(WP_REST_Request $request) {
        try {
            // Lấy dữ liệu từ request
            $base64_data = $request->get_param('base64_data');
            $file_name = $request->get_param('file_name');
            $replace_url = $request->get_param('replace_url') ?: '';

            // Kiểm tra dữ liệu đầu vào
            if (empty($base64_data) || empty($file_name)) {
                return new WP_REST_Response(
                    array(
                        'code' => 400,
                        'message' => 'Missing base64_data or file_name',
                        'data' => null
                    ),
                    400
                );
            }

            // Tách phần base64 nếu là data URL
            $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $base64_data);
            $binary = base64_decode($base64, true);

            if ($binary === false) {
                return new WP_REST_Response(
                    array(
                        'code' => 400,
                        'message' => 'Invalid base64 data',
                        'data' => null
                    ),
                    400
                );
            }

            // Tạo file tạm thời
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['path'] . '/' . sanitize_file_name($file_name) . '.png';

            // Lưu binary data vào file tạm
            if (!file_put_contents($file_path, $binary)) {
                return new WP_REST_Response(
                    array(
                        'code' => 500,
                        'message' => 'Failed to save temporary file',
                        'data' => null
                    ),
                    500
                );
            }

            // Chuẩn bị dữ liệu để thêm vào Media Library
            $file_type = 'image/png';
            $attachment = array(
                'post_mime_type' => $file_type,
                'post_title' => sanitize_file_name($file_name),
                'post_content' => '',
                'post_status' => 'inherit'
            );

            // Thêm file vào Media Library
            $attachment_id = wp_insert_attachment($attachment, $file_path);

            if (is_wp_error($attachment_id)) {
                return new WP_REST_Response(
                    array(
                        'code' => 500,
                        'message' => $attachment_id->get_error_message(),
                        'data' => null
                    ),
                    500
                );
            }

            // Tạo metadata cho attachment
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attachment_data);

            // Lấy URL của file đã upload
            $file_url = wp_get_attachment_url($attachment_id);

            // Xử lý replace_url nếu có
            if (!empty($replace_url)) {
                // Bạn có thể thêm logic để xử lý replace_url, ví dụ: xóa file cũ nếu cần
                // Ví dụ: tìm attachment cũ bằng URL và xóa
                // Đây chỉ là placeholder, bạn cần triển khai logic cụ thể
            }

            return new WP_REST_Response(
                array(
                    'code' => 200,
                    'message' => 'Upload success',
                    'data' => array(
                        'id' => $attachment_id,
                        'url' => $file_url
                    )
                ),
                200
            );

        } catch (Exception $e) {
            return new WP_REST_Response(
                array(
                    'code' => 500,
                    'message' => $e->getMessage() ?: 'Upload failed',
                    'data' => null
                ),
                500
            );
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
