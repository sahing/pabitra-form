<?php
/*
Plugin Name: Course Download Popup
Description: Collects user info via popup form and redirects to download after saving data.
Version: 1.1
Author: Sahin Ahmed
*/

if (!defined('ABSPATH')) exit;

// Create DB Table
function course_popup_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'course_leads';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id INT NOT NULL AUTO_INCREMENT,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'course_popup_create_table');

// Admin Settings Menu
function course_popup_admin_menu() {
    add_menu_page('Course Settings', 'Course Settings', 'manage_options', 'course-popup-settings', 'course_popup_settings_page');
}
add_action('admin_menu', 'course_popup_admin_menu');

function course_popup_settings_page() {
    if (isset($_POST['save_course_popup_settings'])) {
        update_option('course_download_link', esc_url_raw($_POST['download_link']));
        update_option('course_button_text', sanitize_text_field($_POST['button_text']));
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $link = get_option('course_download_link', '');
    $button_text = get_option('course_button_text', 'Download Course');

    ?>
    <div class="wrap">
        <h2>Course Popup Settings</h2>
        <form method="post">
            <label>Download Link:</label><br>
            <input type="url" name="download_link" value="<?php echo esc_url($link); ?>" size="50" required><br><br>

            <label>Button Text:</label><br>
            <input type="text" name="button_text" value="<?php echo esc_attr($button_text); ?>" size="30" required><br><br>

            <input type="submit" name="save_course_popup_settings" class="button button-primary" value="Save Settings">
        </form>
    </div>
    <?php
}

// Handle Form Submission
function handle_course_form_submission() {
    if (!isset($_POST['course_nonce_field']) || !wp_verify_nonce($_POST['course_nonce_field'], 'course_nonce_action')) {
        wp_die('Security check failed!');
    }

    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);

    if (!empty($email) && !empty($phone)) {
        global $wpdb;
        $table = $wpdb->prefix . 'course_leads';

        $wpdb->insert($table, [
            'email' => $email,
            'phone' => $phone,
        ]);

        $download_link = get_option('course_download_link');
        if (!empty($download_link)) {
            wp_redirect(esc_url_raw($download_link));
            exit;
        } else {
            wp_die('Download link not configured.');
        }
    } else {
        wp_die('Please fill in all fields.');
    }
}
add_action('admin_post_course_form_submit', 'handle_course_form_submission');
add_action('admin_post_nopriv_course_form_submit', 'handle_course_form_submission');

// Shortcode
function course_popup_button_shortcode() {
    ob_start();
    $button_text = get_option('course_button_text', 'Download Course');

    ?>
    <button id="open-course-popup" class="button button-primary"><?php echo esc_html($button_text); ?></button>

    <div id="course-popup-form" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;">
        <div style="background:white;padding:20px;max-width:400px;margin:100px auto;position:relative;">
            <button onclick="document.getElementById('course-popup-form').style.display='none'" style="position:absolute;top:10px;right:10px;">X</button>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="course_form_submit">
                <?php wp_nonce_field('course_nonce_action', 'course_nonce_field'); ?>
                <input type="email" name="email" placeholder="Your Email" required><br><br>
                <input type="tel" name="phone" placeholder="Your Phone Number" required><br><br>
                <input type="submit" value="Submit" class="button button-primary">
            </form>
        </div>
    </div>

    <script>
        document.getElementById('open-course-popup').addEventListener('click', function() {
            document.getElementById('course-popup-form').style.display = 'block';
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('course_download_button', 'course_popup_button_shortcode');

// Admin table view
function course_popup_add_submenu() {
    add_submenu_page('course-popup-settings', 'Leads', 'View Leads', 'manage_options', 'course-popup-leads', 'course_popup_view_leads');
}
add_action('admin_menu', 'course_popup_add_submenu');

function course_popup_view_leads() {
    global $wpdb;
    $table = $wpdb->prefix . 'course_leads';

    $per_page = 10;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $per_page;

    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $leads = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY submitted_at DESC LIMIT %d OFFSET %d", $per_page, $offset));

    echo '<div class="wrap"><h2>Course Leads</h2>';
    echo '<table class="widefat"><thead><tr><th>Email</th><th>Phone</th><th>Submitted At</th></tr></thead><tbody>';
    foreach ($leads as $lead) {
        echo '<tr>';
        echo '<td>' . esc_html($lead->email) . '</td>';
        echo '<td>' . esc_html($lead->phone) . '</td>';
        echo '<td>' . esc_html($lead->submitted_at) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    $page_links_total = ceil($total / $per_page);
    if ($page_links_total > 1) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        for ($i = 1; $i <= $page_links_total; $i++) {
            $class = ($i === $paged) ? ' class="current"' : '';
            echo '<a' . $class . ' href="?page=course-popup-leads&paged=' . $i . '">' . $i . '</a> ';
        }
        echo '</div></div>';
    }

    echo '</div>';
}
?>
