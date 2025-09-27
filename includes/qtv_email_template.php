<?php
if (!defined('ABSPATH')) {
    exit;
}

class QTV_Email_Template {

    private $post_type = 'qtv_email_template';
    private $taxonomy = 'qtv_template_category';

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_action('init', [$this, 'register_meta_fields']);
        add_action('init', [$this, 'register_taxonomy']);
        add_action('init', [$this, 'register_shortcode']);
    }

    /**
     * Đăng ký Custom Post Type
     */
    public function register_cpt() {
        $labels = array(
            'name'               => __('Email Templates', 'qtv-email-builder'),
            'singular_name'      => __('Email Template', 'qtv-email-builder'),
            'menu_name'          => __('Email Templates', 'qtv-email-builder'),
            'name_admin_bar'     => __('Email Template', 'qtv-email-builder'),
            'add_new'            => __('Add New', 'qtv-email-builder'),
            'add_new_item'       => __('Add New Email Template', 'qtv-email-builder'),
            'new_item'           => __('New Email Template', 'qtv-email-builder'),
            'edit_item'          => __('Edit Email Template', 'qtv-email-builder'),
            'view_item'          => __('View Email Template', 'qtv-email-builder'),
            'all_items'          => __('All Email Templates', 'qtv-email-builder'),
            'search_items'       => __('Search Email Templates', 'qtv-email-builder'),
            'not_found'          => __('Not found', 'qtv-email-builder'),
            'not_found_in_trash' => __('Not found in Trash', 'qtv-email-builder'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'show_in_menu'       => false,
            'supports'           => array('title', 'editor'),
            'menu_icon'          => 'dashicons-email',
            'has_archive'        => true,
            'rewrite'            => array('slug' => $this->post_type),
            'show_in_rest'       => true,
        );

        register_post_type($this->post_type, $args);
    }

    /**
     * Đăng ký các post meta cho CPT
     */
    public function register_meta_fields() {
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
            "sample",
            "email_content",
            "style",
            "email_data",
            "updated_time"
        ];

        foreach ($fields as $field) {
            register_post_meta($this->post_type, $field, [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => null,
            ]);
        }

        register_post_meta($this->post_type, '_raw_json', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
        ]);
    }

    /**
     * Đăng ký taxonomy "Category" cho CPT email_template
     */
    public function register_taxonomy() {
        $labels = [
            'name'              => _x('Categories', 'taxonomy general name', 'qtv-email-builder'),
            'singular_name'     => _x('Category', 'taxonomy singular name', 'qtv-email-builder'),
            'search_items'      => __('Search Categories', 'qtv-email-builder'),
            'all_items'         => __('All Categories', 'qtv-email-builder'),
            'parent_item'       => __('Parent Category', 'qtv-email-builder'),
            'parent_item_colon' => __('Parent Category:', 'qtv-email-builder'),
            'edit_item'         => __('Edit Category', 'qtv-email-builder'),
            'update_item'       => __('Update Category', 'qtv-email-builder'),
            'add_new_item'      => __('Add New Category', 'qtv-email-builder'),
            'new_item_name'     => __('New Category Name', 'qtv-email-builder'),
            'menu_name'         => __('Categories', 'qtv-email-builder'),
        ];

        $args = [
            'hierarchical'      => true, // giống category, false thì giống tag
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'template-category'],
            'show_in_rest'      => true, // quan trọng để hiển thị trong Gutenberg + API
        ];

        register_taxonomy($this->taxonomy, [$this->post_type], $args);
    }

    function register_shortcode(){
        add_shortcode('qtv_email_builder', [$this, 'qtv_add_shortcode_email']);
    }

    function qtv_add_shortcode_email($atts){
        $atts = shortcode_atts([
            'template_id' => '',
            'use_iframe'  => '1'
        ], $atts);

        $template_id = $atts['template_id'];
        $use_iframe  = $atts['use_iframe'];
        if (empty($template_id)) {
            return '<p style="color:red;">Missing template_id in shortcode.</p>';
        }

        $args = [
            'post_type'      => $this->post_type,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'   => 'template_id',
                    'value' => $template_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $query->the_post();
            
            // Lấy meta field email_content
            $email_content = get_post_meta(get_the_ID(), 'email_content', true);

            wp_reset_postdata();

            if (!empty($email_content)) {

                if ($use_iframe === '0') {
                    return $email_content;
                }

                $iframe_id = 'qtv_iframe_' . uniqid();
                $iframe = '<iframe id="' . $iframe_id . '" 
                        style="width:100%;min-height:500px;border:0;"
                        srcdoc="' . esc_attr($email_content) . '">
                    </iframe>
                    
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            var iframe = document.getElementById("' . $iframe_id . '");
                            if (iframe) {
                                iframe.onload = function() {
                                    try {
                                        var doc = iframe.contentWindow.document;
                                        var height = doc.body.scrollHeight || doc.documentElement.scrollHeight;
                                        iframe.style.height = height + 30 + "px";
                                    } catch (e) {
                                        console.error("Không thể truy cập nội dung iframe:", e);
                                    }
                                };
                            }
                        });
                    </script>';

                return $iframe;
            } else {
                return '<p style="color:red;">Not found meta "email_content".</p>';
            }
        } else {
            return '<p style="color:red;">Template with template_id not found: ' . esc_html($template_id) . '</p>';
        }
    }
}

function qtv_email_builder_admin_menu() {
    // Menu chính
    add_menu_page(
        'Email Builder',           // Page title
        'Email Builder',               // Menu title
        'manage_options',              // Capability
        'qtv-email-manager',           // Slug
        'qtv_email_manager_dashboard', // Callback hiển thị
        'dashicons-email',        // Icon
        10                             // Vị trí menu
    );
}
add_action('admin_menu', 'qtv_email_builder_admin_menu', 9999);

function qtv_email_manager_dashboard() {
    echo '<div class="wrap">';
    echo '<div id="qtv-angular-app"><app-root></app-root></div>';
}

add_action('admin_enqueue_scripts', 'angular_dashboard_embed_scripts');

function angular_dashboard_embed_scripts($hook) {
    if ($hook !== 'toplevel_page_qtv-email-manager') {
        return;
    }
    
    $plugin_url = plugin_dir_url(__FILE__);
    $angular_base = $plugin_url . 'angular/';
    
    wp_enqueue_style('angular-styles', $angular_base . 'styles.css', array(), null);
    add_action('admin_footer', function() use ($angular_base) {
        echo '
        <script type="module" src="' . esc_url($angular_base . 'runtime.js') . '"></script>
        <script type="module" src="' . esc_url($angular_base . 'polyfills.js') . '"></script>
        <script defer src="' . esc_url($angular_base . 'scripts.js') . '"></script>
        <script type="module" src="' . esc_url($angular_base . 'main.js') . '"></script>
        ';
    });

    wp_enqueue_script('jquery-min-js', $angular_base . 'assets/js/jquery.min.js', [], null, true);
    wp_enqueue_script('keymaster-min-js', $angular_base . 'assets/js/keymaster.min.js', ['jquery-min-js'], null, true);
    wp_enqueue_script('integrate-micro-sites-js', $angular_base . 'assets/js/integrate-micro-sites.min.js', [], null, true);
    wp_enqueue_script('mo-wb-core', $angular_base . 'assets/js/mo.wb.min.js', [], null, true);
    wp_enqueue_script('mo-wb-core-ace', $angular_base . 'assets/ace/ace.js', [], null, true);

     // Gửi nonce xuống Angular
    wp_localize_script('mo-wb-core-ace', 'qtvApi', [
        'root'  => esc_url_raw(rest_url('qtv-email/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);

    
    add_action('admin_footer', function() {
        echo '<script src="https://accounts.google.com/gsi/client" async defer></script>';
    });

    echo '
    <script>
        window.qtvEmailHost = "' . esc_js(get_site_url()) . '";
        window.qtvEmailPluginUrl = "' . esc_js($plugin_url) . '";
        window.qtvWoocommereceActive = ' . (is_plugin_active('woocommerce/woocommerce.php') ? 'true' : 'false') . ';
        window.qtvLanguage = "' . esc_js(get_bloginfo('language')) . '";
    </script>
    <style>
      :root {
        --pri: #226FF5;
        --pri-d: #1B59C4;
        --pri-l1: #4E8CF7;
        --pri-l2: #E9F1FE;
        --pri-l3: #F4F8FF;
        --pri-l4: #91B7FA;
        --pri-l5: #BDD4FC;
        --pri-l6: #D3E2FD;
      }
        
      :root {
        --ac-1: #7239EA;
        --ac-1-d: #5B2EBB;
        --ac-1-l1: #8E61EE;
        --ac-1-l2: #F1EBFD;
        --ac-1-l3: #F8F5FE;

        --ac-2: #04C8C8;
        --ac-2-d: #03A0A0;
        --ac-2-l1: #36D3D3;
        --ac-2-l2: #E6FAFA;
        --ac-2-l3: #CDF4F4;

        --ac-3: #F5226F;
        --ac-3-d: #C41B59;
        --ac-3-l1: #F74E8C;
        --ac-3-l2: #FEE9F1;
        --ac-3-l3: #FDD3E2;
        
        --r: #EE2D41;
        --r-d: #BE2434;
        --r-l1: #F15767;
        --r-l2: #FDEAEC;
        --r-l3: #FEF5F6;

        --y: #FFA621;
        --y-d: #CC851A;
        --y-l1: #FFB84D;
        --y-l2: #FFF6E9;
        --y-l3: #FFFBF4;
        
        --g: #00BC62;
        --g-d: #00A757;
        --g-l1: #33DA8A;
        --g-l2: #E6FAF0;
        --g-l3: #F2FCF7;

        --ad-1: #663259;
        --ad-1-d: #522847;
        --ad-1-l1: #855B7A;
        --ad-1-l2: #F0EBEE;
        --ad-1-l3: #e0d6de;

        --ad-2: #F05800;
        --ad-2-d: #C04600;
        --ad-2-l1: #F37933;
        --ad-2-l2: #FDEAEC;
        --ad-2-l3: #FCDECC;

        --menu-bg: #030B18;
        --main-txt: #071631;
        --btn-txt: #6A7383;
        --cap-txt: #9CA2AD;
        --disabled: #CDD0D6;
        --border: #E6E7EA;
        --bg: #F8F9FA;
        --w: #ffffff;
        --trans: transparent;
      }
    </style>';
}
