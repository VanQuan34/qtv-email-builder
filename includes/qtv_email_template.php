<?php
if (!defined('ABSPATH')) {
    exit;
}

class QTV_Email_Template {

    private $post_type = 'email_template';
    private $taxonomy = 'template_category';

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_action('init', [$this, 'register_meta_fields']);
        add_action('init', [$this, 'register_taxonomy']);
    }

    /**
     * Đăng ký Custom Post Type
     */
    public function register_cpt() {
        $labels = array(
            'name'               => 'Email Templates',
            'singular_name'      => 'Email Template',
            'menu_name'          => 'Email Templates',
            'name_admin_bar'     => 'Email Template',
            'add_new'            => 'Thêm mới',
            'add_new_item'       => 'Thêm Email Template',
            'new_item'           => 'Email Template mới',
            'edit_item'          => 'Sửa Email Template',
            'view_item'          => 'Xem Email Template',
            'all_items'          => 'Tất cả Email Templates',
            'search_items'       => 'Tìm Email Template',
            'not_found'          => 'Không tìm thấy',
            'not_found_in_trash' => 'Không có trong thùng rác'
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'show_in_menu'       => true,
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
            "template_id",   // tránh trùng "id" với post ID
            "is_favorite",
            "merchant_id",
            "name",
            "session",
            "small_thumbnail",
            "status_code",   // tránh trùng "status" của WP
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

        // Nếu bạn muốn lưu toàn bộ JSON gốc
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
            'name'              => _x('Categories', 'taxonomy general name', 'qtv'),
            'singular_name'     => _x('Category', 'taxonomy singular name', 'qtv'),
            'search_items'      => __('Search Categories', 'qtv'),
            'all_items'         => __('All Categories', 'qtv'),
            'parent_item'       => __('Parent Category', 'qtv'),
            'parent_item_colon' => __('Parent Category:', 'qtv'),
            'edit_item'         => __('Edit Category', 'qtv'),
            'update_item'       => __('Update Category', 'qtv'),
            'add_new_item'      => __('Add New Category', 'qtv'),
            'new_item_name'     => __('New Category Name', 'qtv'),
            'menu_name'         => __('Categories', 'qtv'),
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
        if (!term_exists('Uncategorized', $this->taxonomy)) {
        wp_insert_term('Uncategorized', $this->taxonomy, [
            'slug' => 'uncategorized',
            'description' => 'Danh mục mặc định cho template'
        ]);
    }
    }
}


// Tạo menu admin riêng
function qtv_email_builder_admin_menu() {
    // Menu chính
    add_menu_page(
        'QTV Email Manager 2222',           // Page title
        'Email Manager',               // Menu title
        'manage_options',              // Capability
        'qtv-email-manager',           // Slug
        'qtv_email_manager_dashboard', // Callback hiển thị
        'dashicons-email-alt2',        // Icon
        10                             // Vị trí menu
    );
}
add_action('admin_menu', 'qtv_email_builder_admin_menu', 9999);

// Trang Dashboard
function qtv_email_manager_dashboard() {
    echo '<div class="wrap">';
    echo '<div id="qtv-angular-app"><app-root></app-root></div>';// Angular sẽ mount vào đây
    echo '</div>';
}

add_action('admin_enqueue_scripts', 'angular_dashboard_embed_scripts');

function angular_dashboard_embed_scripts($hook) {
    if ($hook !== 'toplevel_page_qtv-email-manager') {
        return;
    }
    
    $plugin_url = plugin_dir_url(__FILE__);
    $angular_base = $plugin_url . 'angular/';

    // Load CSS chính (tên file CSS trong html là styles.32315f7a272f9431.css)
    wp_enqueue_style('angular-styles', $angular_base . 'styles.css', array(), null);

    // Load các script Angular. Lưu ý một số script có type="module" hoặc defer, WordPress wp_enqueue_script không hỗ trợ tự động kiểu này,
    // nên ta sẽ thêm thủ công qua action admin_footer nếu cần.

    // Cách 1: Load script bình thường (không đúng type module)
    // wp_enqueue_script('angular-runtime', $angular_base . 'runtime.6b24981b3a9500e0.js', array(), null, true);
    // wp_enqueue_script('angular-polyfills', $angular_base . 'polyfills.5b55ffc9d42aa9d8.js', array(), null, true);
    // wp_enqueue_script('angular-scripts', $angular_base . 'scripts.d1fe2b413193d73a.js', array(), null, true);
    // wp_enqueue_script('angular-vendor', $angular_base . 'vendor.ba1475e8a122a7ee.js', array(), null, true);
    // wp_enqueue_script('angular-main', $angular_base . 'main.2816dd690577b5a9.js', array(), null, true);

    // Cách 2: Do các file này cần type="module" hoặc defer nên trực tiếp in thẻ script thủ công ở footer
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


// Hook vào khi tạo admin menu
add_action('add_meta_boxes', 'mobio_add_template_meta_box');

function mobio_add_template_meta_box() {
    add_meta_box(
        'mobio_template_meta',              // ID của meta box
        'Template Meta Fields',             // Tiêu đề hiển thị
        'mobio_render_template_meta_box',   // Callback render nội dung
        'email_template',                   // Post type
        'normal',                           // Vị trí (normal, side, advanced)
        'high'                              // Độ ưu tiên
    );
}

// Hàm render nội dung trong meta box
function mobio_render_template_meta_box($post) {
    // Lấy meta đã lưu
    $meta_fields = get_post_meta($post->ID);

    echo '<table class="form-table">';
    foreach ($meta_fields as $key => $values) {
        if (strpos($key, '_') === 0) continue; // bỏ qua key system (_edit_lock,...)
        echo '<tr>';
        echo '<th style="width:150px; text-align:left;">' . esc_html($key) . '</th>';
        echo '<td>' . esc_html(is_array($values) ? implode(', ', $values) : $values) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}
