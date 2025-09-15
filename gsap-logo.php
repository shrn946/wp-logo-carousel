<?php
/*
Plugin Name: GSAP Logo Carousel (Advanced)
Description: Advanced, responsive, highly-configurable logo carousel using GSAP. Shortcode: [glc_carousel].
Version: 1.5
Author: Hassan
Text Domain: glc
*/

if (!defined('ABSPATH')) exit;

class GLC_Plugin {
    const OPTION_KEY = 'glc_settings';

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_assets'));
        add_action('wp_ajax_glc_save_settings', array($this, 'ajax_save_settings'));
        add_shortcode('glc_carousel', array($this, 'shortcode'));
    }

    public function init() {
        $defaults = array(
            'logos' => array(),
            'speed' => 30,
            'direction' => 'left',
            'gap' => 40,
            'autoplay' => true,
            'pause_on_hover' => true,
            'loop' => true,
            'lazyload' => true,
            'logo_width' => 200,
            'logo_height' => 80,
            'hover_effect' => 'none',
        );

        if (get_option(self::OPTION_KEY) === false) {
            add_option(self::OPTION_KEY, $defaults);
        }
    }

    public function admin_menu() {
        add_menu_page(
            'GSAP Logo Carousel',
            'Logo Carousel',
            'manage_options',
            'glc-settings',
            array($this, 'settings_page'),
            'dashicons-images-alt2',
            65
        );
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'glc-settings') === false) return;
        wp_enqueue_media();
        wp_enqueue_style('glc-admin', plugin_dir_url(__FILE__) . 'assets/admin.css');
        wp_enqueue_script('glc-admin', plugin_dir_url(__FILE__) . 'assets/admin.js', array('jquery'), null, true);
        wp_localize_script('glc-admin', 'glcAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('glc_nonce'),
            'settings' => get_option(self::OPTION_KEY),
        ));
    }

    public function frontend_assets() {
        wp_enqueue_script('gsap', 'https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js', array(), null, true);
        wp_enqueue_style('glc-frontend', plugin_dir_url(__FILE__) . 'assets/frontend.css');
        wp_enqueue_script('glc-frontend', plugin_dir_url(__FILE__) . 'assets/frontend.js', array('jquery','gsap'), null, true);
        wp_localize_script('glc-frontend', 'glcFrontend', array(
            'settings' => get_option(self::OPTION_KEY),
            'ajax_url' => admin_url('admin-ajax.php'),
        ));
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) wp_die('Not allowed');
        $settings = get_option(self::OPTION_KEY);
        ?>
        <div class="wrap glc-wrap">
            <h1>GSAP Logo Carousel — Settings</h1>
            <form id="glc-form" method="post">
                <h2>Logos</h2>
                <p><button type="button" class="button" id="glc-add-logo">Add logo</button></p>
                <div id="glc-logos">
                    <?php if (!empty($settings['logos'])): foreach ($settings['logos'] as $index => $logo): ?>
                        <div class="glc-logo-item" data-index="<?php echo $index; ?>">
                            <input type="hidden" name="logos[<?php echo $index; ?>][id]" value="<?php echo esc_attr($logo['id']); ?>" />
                            <p><img src="<?php echo esc_attr($logo['url']); ?>" class="glc-thumb" style="max-width:120px;max-height:60px;" /></p>
                            <p>Link: <input type="url" name="logos[<?php echo $index; ?>][link]" value="<?php echo esc_attr($logo['link']); ?>" style="width:60%" /></p>
                            <p>
                                <button type="button" class="button glc-change-logo">Change</button>
                                <button type="button" class="button glc-remove-logo">Remove</button>
                            </p>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <h2>General Settings</h2>
                <table class="form-table">
                    <tr><th>Speed (px/sec)</th><td><input type="number" name="speed" value="<?php echo esc_attr($settings['speed']); ?>" /></td></tr>
                    <tr><th>Direction</th><td>
                        <select name="direction">
                            <option value="left" <?php selected($settings['direction'],'left'); ?>>Left</option>
                            <option value="right" <?php selected($settings['direction'],'right'); ?>>Right</option>
                        </select>
                    </td></tr>
                    <tr><th>Gap (px)</th><td><input type="number" name="gap" value="<?php echo esc_attr($settings['gap']); ?>" /></td></tr>
                    <tr><th>Logo Height (px)</th><td><input type="number" name="logo_height" value="<?php echo esc_attr($settings['logo_height']); ?>" /></td></tr>
                    <tr><th>Hover Effect</th><td>
                        <select name="hover_effect">
                            <option value="none" <?php selected($settings['hover_effect'],'none'); ?>>None</option>
                            <option value="grayscale" <?php selected($settings['hover_effect'],'grayscale'); ?>>Grayscale</option>
                            <option value="scale" <?php selected($settings['hover_effect'],'scale'); ?>>Zoom In</option>
                            <option value="opacity" <?php selected($settings['hover_effect'],'opacity'); ?>>Fade</option>
                        </select>
                    </td></tr>
                    <tr><th>Autoplay</th><td><input type="checkbox" name="autoplay" value="1" <?php checked($settings['autoplay'], true); ?> /></td></tr>
                    <tr><th>Pause on hover</th><td><input type="checkbox" name="pause_on_hover" value="1" <?php checked($settings['pause_on_hover'], true); ?> /></td></tr>
                    <tr><th>Lazyload images</th><td><input type="checkbox" name="lazyload" value="1" <?php checked($settings['lazyload'], true); ?> /></td></tr>
                </table>

                <p class="submit"><button id="glc-save" class="button button-primary">Save settings</button></p>
            </form>

            <h2>Shortcode</h2><p>Use <code>[glc_carousel]</code></p>
        </div>
        <?php
    }

    public function ajax_save_settings() {
        check_admin_referer('glc_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('No permission');

        $opts = get_option(self::OPTION_KEY, array());
        $body = $_POST;

        $opts['speed'] = (int)($body['speed'] ?? $opts['speed']);
        $opts['direction'] = in_array($body['direction'], ['left','right']) ? $body['direction'] : $opts['direction'];
        $opts['gap'] = (int)($body['gap'] ?? $opts['gap']);
        $opts['logo_width'] = (int)($body['logo_width'] ?? $opts['logo_width']);
        $opts['logo_height'] = (int)($body['logo_height'] ?? $opts['logo_height']);
        $opts['hover_effect'] = sanitize_text_field($body['hover_effect'] ?? $opts['hover_effect']);
        $opts['autoplay'] = !empty($body['autoplay']);
        $opts['pause_on_hover'] = !empty($body['pause_on_hover']);
        $opts['loop'] = !empty($body['loop']);
        $opts['lazyload'] = !empty($body['lazyload']);

        $logos = array();
        if (!empty($body['logos']) && is_array($body['logos'])) {
            foreach ($body['logos'] as $l) {
                $id = isset($l['id']) ? (int)$l['id'] : 0;
                $url = $id ? wp_get_attachment_url($id) : (!empty($l['url']) ? esc_url_raw($l['url']) : '');
                if (!$url) continue;
                $logos[] = array(
                    'id'   => $id,
                    'url'  => $url,
                    'link' => !empty($l['link']) ? esc_url_raw($l['link']) : '',
                );
            }
        }
        $opts['logos'] = $logos;

        update_option(self::OPTION_KEY, $opts);
        wp_send_json_success($opts);
    }

    public function shortcode($atts) {
        if (is_admin()) return '';

        $settings = get_option(self::OPTION_KEY);
        $atts = shortcode_atts(array(
            'speed' => $settings['speed'],
            'gap' => $settings['gap'],
            'direction' => $settings['direction'],
            'class' => '',
        ), $atts, 'glc_carousel');

        if (empty($settings['logos'])) return '<!-- no logos configured -->';

        ob_start(); ?>
        <div class="glc-carousel <?php echo esc_attr($atts['class']); ?> hover-<?php echo esc_attr($settings['hover_effect']); ?>"
             data-settings='<?php echo esc_attr(json_encode(array_merge($settings, $atts))); ?>'>
            <div class="glc-track">
                <?php foreach ($settings['logos'] as $logo): ?>
                    <div class="glc-item" style="margin-right:<?php echo (int)$settings['gap']; ?>px;">
                        <?php if (!empty($logo['link'])): ?><a href="<?php echo esc_url($logo['link']); ?>" target="_blank"><?php endif; ?>
                        <img src="<?php echo esc_attr($logo['url']); ?>"
                             alt=""
                             style="height:<?php echo (int)$settings['logo_height']; ?>px; width:auto; object-fit:contain;"
                             loading="<?php echo $settings['lazyload'] ? 'lazy' : 'eager'; ?>" />
                        <?php if (!empty($logo['link'])): ?></a><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new GLC_Plugin();
