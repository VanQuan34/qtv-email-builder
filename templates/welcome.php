<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

?>
<?php
// templates/welcome-page.php
if (! defined('ABSPATH')) exit;

$request = new WP_REST_Request('GET', '/qtv-email/v1/top-templates');
$response = QTV_WordPress_Service::get_top_templates($request);

$posts = $response->get_data();
$template_views = [];

if (is_array($posts)) {
    foreach ($posts as $item) {
        $template_views[$item['title']] = (int) $item['count'];
    }
}

$template_views_json = json_encode($template_views);

?>
<div class="wrap" style="font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width:1200px; margin:40px auto; padding:0 20px; background:#f9fafb; border-radius:8px;">
    <header style="text-align:center; padding-bottom:40px;">
        <h1 style="font-size:2.8rem; margin:0; color:#2a2a72;"><?php echo __('Welcome to QTV Email Builder', 'qtv-email-builder'); ?></h1>
        <p style="font-size:1.2rem; color:#6b7280; margin-top:10px;"><?php echo __('Create beautiful, professional emails easily right inside your WordPress', 'qtv-email-builder'); ?></p>

        <!-- Progress bar / onboarding steps -->
        <div style="margin-top:30px; max-width:600px; margin-left:auto; margin-right:auto;">
            <div style="background:#ddd; border-radius:12px; overflow:hidden; height:14px;">
                <div style="width:33%; background:#4f46e5; height:100%; transition: width 0.25s ease-in-out;"></div>
            </div>
            <ul style="list-style:none; display:flex; justify-content:space-between; padding:0; margin:10px 0 0 0; font-size:0.9rem; color:#4b5563;">
                <li><?php echo __('1. Create Email', 'qtv-email-builder'); ?></li>
                <li><?php echo __('2. Customize', 'qtv-email-builder'); ?></li>
                <li><?php echo __('3. Send & Track', 'qtv-email-builder'); ?></li>
            </ul>
        </div>

        <!-- Tips / info box -->
        <div style="background:#eef2ff; border:1px solid #c3dafe; border-radius:8px; padding:15px; max-width:600px; margin:20px auto 0; color:#3730a3; font-weight:600;">
            <?php echo __('Tip: Use the drag & drop editor to quickly build your email layouts. Check out our tutorials for best practices!', 'qtv-email-builder'); ?>
        </div>

        <div style="margin-top: 25px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=qtv-email-manager#/')); ?>" class="button button-primary" style="padding:14px 28px; font-weight:600; font-size:1rem; margin-right:10px;"><?php echo __('Create New Email', 'qtv-email-builder'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=qtv-email-manager#/')); ?>" class="button" style="padding:14px 28px; font-weight:600; font-size:1rem;"><?php echo __('Available Templates', 'qtv-email-builder'); ?></a>
        </div>
    </header>

    <section style="display:flex; flex-wrap:wrap; justify-content:space-around; gap:25px; margin-top:50px;">
        <?php
        $features = [
            ['icon' => 'dashicons-editor-bold', 'title' => __('Drag & Drop Builder', 'qtv-email-builder'), 'desc' => __('Design emails visually with a drag-and-drop interface, no coding skills needed.', 'qtv-email-builder')],
            ['icon' => 'dashicons-smartphone', 'title' => __('Mobile Friendly', 'qtv-email-builder'), 'desc' => __('Emails automatically look great on all devices, from desktop to mobile phones.', 'qtv-email-builder')],
            ['icon' => 'dashicons-admin-generic', 'title' => __('Flexible Customization', 'qtv-email-builder'), 'desc' => __('Easily customize styles, images, and content as you wish.', 'qtv-email-builder')],
            ['icon' => 'dashicons-email-alt', 'title' => __('Fast Email Sending', 'qtv-email-builder'), 'desc' => __('Integrated email sending within WordPress, no extra tools needed.', 'qtv-email-builder')],
        ];
        foreach ($features as $feature): ?>
            <div style="flex:1 1 280px; background:#fff; border-radius:12px; padding:30px 25px; box-shadow:0 4px 20px rgba(0,0,0,0.1); text-align:center; transition:box-shadow 0.3s ease;">
                <span class="dashicons <?php echo esc_attr($feature['icon']); ?>" style="font-size:40px; color:#4f46e5; margin-bottom:20px; display:inline-block;"></span>
                <h2 style="font-size:1.3rem; font-weight:700; margin-bottom:12px; color:#1f2937;"><?php echo esc_html($feature['title']); ?></h2>
                <p style="font-size:1rem; line-height:1.5; color:#4b5563;"><?php echo esc_html($feature['desc']); ?></p>
            </div>
        <?php endforeach; ?>
    </section>

    <section style="margin-top:60px;">
        <h2 style="color:#2a2a72; font-weight:700;"><?php echo __('Template Usage Frequency', 'qtv-email-builder'); ?></h2>
        <canvas id="templateUsageChart" style="width: 100%; max-width: 900px; height: 400px;"></canvas>
    </section>

    <footer style="text-align:center; margin-top:60px; padding:20px 0; border-top:1px solid #ddd; color:#555; font-size:0.9rem;">
        &copy; <?php echo date('Y'); ?> <?php echo __('QTV Email Builder. Need help?', 'qtv-email-builder'); ?> <a href="https://support.quantv.store" target="_blank" rel="noopener noreferrer"><?php echo __('Contact Support', 'qtv-email-builder'); ?></a>
    </footer>
    <
    <script>
        window.QTV_TEMPLATE_VIEWS = <?php echo $template_views_json; ?>;
        window.QTV_VIEWS_LABEL = '<?php echo esc_js(__('Visits', 'qtv-email-builder')); ?>';
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?php echo plugins_url('../assets/script.js', __FILE__); ?>"></script>
</div>