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
                'permission_callback' => [$this, 'qtv_permission_check'],
            ],
        ]);

        register_rest_route('qtv-email/v1', '/posts', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'get_summary_posts'],
                'permission_callback' => [$this, 'qtv_permission_check'],
            ],
        ]);
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
            return $this->error("Error server", 500, null);
        }
    }

}
