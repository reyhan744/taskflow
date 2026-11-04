<?php
/**
 * Admin menu page and asset loading for the Vue.js single-page app.
 *
 * The app is a no-build Vue.js 3 application: the official vue.global.prod.js
 * distribution is bundled with the plugin, so no Node.js/npm or build step is
 * required on the host.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Assets
 */
class TaskFlow_Assets {

        const MENU_SLUG = 'taskflow';

        /**
         * Register the admin menu entry.
         *
         * @return void
         */
        public static function register_menu() {
                add_menu_page(
                        __( 'TaskFlow', 'taskflow' ),
                        __( 'TaskFlow', 'taskflow' ),
                        TaskFlow_Capabilities::EDIT,
                        self::MENU_SLUG,
                        array( __CLASS__, 'render_page' ),
                        'dashicons-yes-alt',
                        26
                );

                // Keep the (hidden) admin page hook available for asset targeting.
                add_submenu_page(
                        self::MENU_SLUG,
                        __( 'TaskFlow', 'taskflow' ),
                        __( 'TaskFlow', 'taskflow' ),
                        TaskFlow_Capabilities::EDIT,
                        self::MENU_SLUG,
                        array( __CLASS__, 'render_page' )
                );
        }

        /**
         * Admin page container. The Vue app mounts on #taskflow-app.
         *
         * @return void
         */
        public static function render_page() {
                ?>
                <div class="wrap taskflow-wrap">
                        <div id="taskflow-app" class="taskflow-app" aria-label="<?php esc_attr_e( 'TaskFlow', 'taskflow' ); ?>">
                                <div class="taskflow-boot">
                                        <span class="taskflow-boot__spinner"></span>
                                        <?php esc_html_e( 'Loading…', 'taskflow' ); ?>
                                </div>
                        </div>
                </div>
                <?php

                include TASKFLOW_PLUGIN_DIR . 'templates/admin-app.php';
                include TASKFLOW_PLUGIN_DIR . 'templates/admin-app-2.php';
        }

        /**
         * Enqueue styles and scripts only on the TaskFlow screen.
         *
         * @param string $hook Current admin page hook.
         * @return void
         */
        public static function enqueue_assets( $hook ) {
                if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
                        return;
                }

                $ver = TASKFLOW_VERSION;

                wp_enqueue_style(
                        'taskflow-admin',
                        TASKFLOW_PLUGIN_URL . 'assets/css/taskflow.css',
                        array(),
                        $ver
                );

                wp_enqueue_script(
                        'taskflow-vue',
                        TASKFLOW_PLUGIN_URL . 'assets/vendor/vue.global.prod.js',
                        array(),
                        '3.5.13',
                        true
                );

                $modules = array(
                        'taskflow-i18n',
                        'taskflow-api',
                        'taskflow-store',
                        'taskflow-ui',
                        'taskflow-views-tasks',
                        'taskflow-views-admin',
                        'taskflow-app',
                );

                $deps = array( 'taskflow-vue' );

                foreach ( $modules as $module ) {
                        wp_enqueue_script(
                                $module,
                                TASKFLOW_PLUGIN_URL . 'assets/js/' . $module . '.js',
                                $deps,
                                $ver,
                                true
                        );
                        $deps[] = $module;
                }

                // Inject the bootstrap data before the first module loads, so every
                // module (including the i18n dictionary) can read window.TaskFlowBoot.
                wp_add_inline_script(
                        'taskflow-i18n',
                        'window.TaskFlowBoot = ' . wp_json_encode( self::boot_data() ) . ';',
                        'before'
                );
        }

        /**
         * Data injected into the SPA bootstrap.
         *
         * @return array<string,mixed>
         */
        private static function boot_data() {
                $settings = TaskFlow_Settings::all();

                // Resolve text direction against the USER locale (admin language),
                // not only the site locale. A prefix check keeps this deterministic
                // even when the runtime locale switcher is unavailable.
                $user_l10n = get_user_locale();

                $is_rtl = is_rtl()
                        || (bool) preg_match( '/^(fa|ar|he|ur|ckb|ps|sd|ug|dv|ku|ary|apc|prd)/i', $user_l10n );

                return array(
                        'restUrl'   => esc_url_raw( rest_url( TASKFLOW_REST_NAMESPACE ) ),
                        'nonce'     => wp_create_nonce( 'wp_rest' ),
                        'version'   => TASKFLOW_VERSION,
                        'user'      => array(
                                'id'     => get_current_user_id(),
                                'name'   => wp_get_current_user()->display_name,
                                'avatar' => get_avatar_url( get_current_user_id(), array( 'size' => 48 ) ),
                        ),
                        'caps'      => array(
                                'manage'      => TaskFlow_Capabilities::can_manage(),
                                'edit_others' => TaskFlow_Capabilities::can_edit_others(),
                                'edit'        => TaskFlow_Capabilities::can_edit(),
                        ),
                        'settings'  => array(
                                'currency'       => $settings['currency'],
                                'items_per_page' => $settings['items_per_page'],
                        ),
                        'locale'    => $user_l10n,
                        'isRtl'     => $is_rtl,
                        'siteUrl'   => home_url( '/' ),
                        'strings'   => TaskFlow_I18n::strings(),
                );
        }
}
