<?php
/**
 * PHPUnit bootstrap for the TaskFlow test suite.
 *
 * The suite runs the real plugin repositories/services against a SQLite
 * database through a faithful wpdb shim, and stubs the small WordPress API
 * surface the plugin uses (options, users/capabilities, i18n helpers).
 * No WordPress installation or MySQL server is required to run the tests.
 *
 * Run:  php tools/phpunit.phar -c phpunit.xml.dist   (see docs/TESTING.md)
 *
 * @package TaskFlow
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'TASKFLOW_TESTING', true );

// WP fetch-mode constants (defined in wp-includes/class-wp-wpdb.php in core).
if ( ! defined( 'ARRAY_A' ) ) {
        define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'ARRAY_N' ) ) {
        define( 'ARRAY_N', 'ARRAY_N' );
}

if ( ! defined( 'OBJECT' ) ) {
        define( 'OBJECT', 'OBJECT' );
}

// ---------------------------------------------------------------------------
// Options store.
// ---------------------------------------------------------------------------

$GLOBALS['taskflow_options'] = array();

function get_option( $name, $default = false ) {
        return array_key_exists( $name, $GLOBALS['taskflow_options'] ) ? $GLOBALS['taskflow_options'][ $name ] : $default;
}

function update_option( $name, $value, $autoload = null ) {
        $GLOBALS['taskflow_options'][ $name ] = $value;

        return true;
}

function add_option( $name, $value = '', $deprecated = '', $autoload = null ) {
        if ( array_key_exists( $name, $GLOBALS['taskflow_options'] ) ) {
                return false;
        }

        $GLOBALS['taskflow_options'][ $name ] = $value;

        return true;
}

function delete_option( $name ) {
        unset( $GLOBALS['taskflow_options'][ $name ] );

        return true;
}

// ---------------------------------------------------------------------------
// Users, roles and capabilities.
// ---------------------------------------------------------------------------

$GLOBALS['taskflow_users']    = array();
$GLOBALS['taskflow_roles']    = array();
$GLOBALS['taskflow_role_caps'] = array();
$GLOBALS['taskflow_current_user'] = 0;

function taskflow_test_reset_users() {
        $GLOBALS['taskflow_users'] = array(
                1 => (object) array(
                        'ID'           => 1,
                        'user_login'   => 'admin',
                        'user_email'   => 'admin@example.com',
                        'display_name' => 'Admin User',
                        'roles'        => array( 'administrator' ),
                        'caps'         => array( 'taskflow_manage', 'taskflow_edit_others_tasks', 'taskflow_edit_tasks', 'manage_options', 'read' ),
                ),
                2 => (object) array(
                        'ID'           => 2,
                        'user_login'   => 'editor',
                        'user_email'   => 'editor@example.com',
                        'display_name' => 'Edna Editor',
                        'roles'        => array( 'editor' ),
                        'caps'         => array( 'taskflow_edit_others_tasks', 'taskflow_edit_tasks', 'read' ),
                ),
                3 => (object) array(
                        'ID'           => 3,
                        'user_login'   => 'author',
                        'user_email'   => 'author@example.com',
                        'display_name' => 'Andy Author',
                        'roles'        => array( 'author' ),
                        'caps'         => array( 'taskflow_edit_tasks', 'read' ),
                ),
                4 => (object) array(
                        'ID'           => 4,
                        'user_login'   => 'subscriber',
                        'user_email'   => 'subscriber@example.com',
                        'display_name' => 'Sam Subscriber',
                        'roles'        => array( 'subscriber' ),
                        'caps'         => array( 'read' ),
                ),
        );

        foreach ( $GLOBALS['taskflow_users'] as $user ) {
                $user->allcaps = array_fill_keys( $user->caps, true );
        }

        $GLOBALS['taskflow_role_caps'] = array(
                'administrator' => array(),
                'editor'        => array(),
                'author'        => array(),
                'contributor'   => array(),
                'subscriber'    => array(),
        );

        $GLOBALS['taskflow_current_user'] = 1;
}

function wp_set_current_user( $user_id ) {
        $GLOBALS['taskflow_current_user'] = (int) $user_id;

        return get_userdata( $user_id );
}

function get_current_user_id() {
        return $GLOBALS['taskflow_current_user'];
}

function get_userdata( $user_id ) {
        return isset( $GLOBALS['taskflow_users'][ (int) $user_id ] ) ? $GLOBALS['taskflow_users'][ (int) $user_id ] : false;
}

function get_user_by( $field, $value ) {
        foreach ( $GLOBALS['taskflow_users'] as $user ) {
                if ( 'login' === $field && $user->user_login === $value ) {
                        return $user;
                }

                if ( 'email' === $field && $user->user_email === $value ) {
                        return $user;
                }

                if ( 'id' === $field && (int) $user->ID === (int) $value ) {
                        return $user;
                }
        }

        return false;
}

function user_can( $user, $cap ) {
        return $user && ! empty( $user->allcaps[ $cap ] );
}

function current_user_can( $cap ) {
        return user_can( get_userdata( get_current_user_id() ), $cap );
}

/**
 * Minimal WP_Roles replacement.
 */
class TaskFlow_Test_Roles {

        public function get_names() {
                return array(
                        'administrator' => 'Administrator',
                        'editor'        => 'Editor',
                        'author'        => 'Author',
                        'contributor'   => 'Contributor',
                        'subscriber'    => 'Subscriber',
                );
        }
}

function wp_roles() {
        return new TaskFlow_Test_Roles();
}

/**
 * Minimal WP_Role replacement.
 */
class TaskFlow_Test_Role {

        public $name;
        public $capabilities = array();

        public function __construct( $name ) {
                $this->name         = $name;
                $this->capabilities = isset( $GLOBALS['taskflow_role_caps'][ $name ] ) ? $GLOBALS['taskflow_role_caps'][ $name ] : array();
        }

        public function add_cap( $cap, $grant = true ) {
                if ( $grant ) {
                        $this->capabilities[ $cap ] = true;
                } else {
                        unset( $this->capabilities[ $cap ] );
                }

                $GLOBALS['taskflow_role_caps'][ $this->name ] = $this->capabilities;

                // Keep fake users in sync so capability checks reflect the change.
                foreach ( $GLOBALS['taskflow_users'] as $user ) {
                        if ( in_array( $this->name, (array) $user->roles, true ) ) {
                                if ( $grant ) {
                                        $user->allcaps[ $cap ] = true;
                                } else {
                                        unset( $user->allcaps[ $cap ] );
                                }
                        }
                }
        }

        public function remove_cap( $cap ) {
                $this->add_cap( $cap, false );
        }
}

function get_role( $name ) {
        return isset( $GLOBALS['taskflow_role_caps'][ $name ] ) ? new TaskFlow_Test_Role( $name ) : null;
}

function get_users( $args = array() ) {
        $role_in = isset( $args['role__in'] ) ? (array) $args['role__in'] : array();
        $out     = array();

        foreach ( $GLOBALS['taskflow_users'] as $user ) {
                if ( $role_in && ! array_intersect( $role_in, (array) $user->roles ) ) {
                        continue;
                }

                $out[] = $user;
        }

        return $out;
}

// ---------------------------------------------------------------------------
// Misc helpers used by the plugin.
// ---------------------------------------------------------------------------

function add_action( $hook, $callback = null, $priority = 10, $args = 1 ) {
        return true;
}

function add_filter( $hook, $callback = null, $priority = 10, $args = 1 ) {
        return true;
}

function apply_filters( $hook, $value ) {
        return $value;
}

function do_action( $hook, ...$args ) {
        return null;
}

function register_activation_hook( $file, $callback ) {
        return true;
}

function register_deactivation_hook( $file, $callback ) {
        return true;
}

function plugin_dir_path( $file ) {
        return rtrim( dirname( $file ), '/' ) . '/';
}

function plugin_dir_url( $file ) {
        return 'http://example.com/wp-content/plugins/taskflow/';
}

function plugin_basename( $file ) {
        return 'taskflow/taskflow.php';
}

function load_plugin_textdomain( $domain, $deprecated = false, $path = false ) {
        return true;
}

function __( $text, $domain = 'default' ) {
        return $text;
}

function esc_html__( $text, $domain = 'default' ) {
        return $text;
}

function esc_attr__( $text, $domain = 'default' ) {
        return $text;
}

function esc_html( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_attr( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_url_raw( $url ) {
        return (string) $url;
}

function esc_url( $url ) {
        return (string) $url;
}

function sanitize_text_field( $text ) {
        return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) );
}

function sanitize_textarea_field( $text ) {
        return trim( strip_tags( (string) $text ) );
}

function sanitize_key( $key ) {
        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function sanitize_email( $email ) {
        $email = trim( strip_tags( (string) $email ) );

        return is_email( $email ) ? $email : '';
}

function is_email( $email ) {
        return (bool) filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
}

function sanitize_title( $title ) {
        $title = strtolower( strip_tags( (string) $title ) );
        $title = preg_replace( '/[^a-z0-9\x80-\xff]+/', '-', $title );
        $title = trim( (string) $title, '-' );

        return $title;
}

function sanitize_hex_color( $color ) {
        if ( '' === $color ) {
                return '';
        }

        return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color ) ? $color : '';
}

function absint( $value ) {
        return abs( (int) $value );
}

function wp_parse_args( $args, $defaults = array() ) {
        return array_merge( (array) $defaults, (array) $args );
}

function wp_json_encode( $data, $flags = 0 ) {
        return json_encode( $data, $flags );
}

function rest_sanitize_boolean( $value ) {
        if ( is_bool( $value ) ) {
                return $value;
        }

        if ( is_string( $value ) ) {
                return 'true' === $value || '1' === $value || 'yes' === $value;
        }

        return (bool) $value;
}

function wp_generate_password( $length = 12, $special = true, $extra = true ) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $out   = '';

        for ( $i = 0; $i < $length; $i++ ) {
                $out .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
        }

        return $out;
}

function wp_timezone_string() {
        return 'UTC';
}

function get_locale() {
        return 'en_US';
}

function get_user_locale( $user_id = 0 ) {
        return 'en_US';
}

function is_rtl() {
        return false;
}

function get_bloginfo( $key = '' ) {
        return '6.8';
}

function wp_get_theme() {
        return new class() {
                public function get( $key ) {
                        return 'TestTheme';
                }
        };
}

function get_avatar_url( $user_id, $args = array() ) {
        return 'http://example.com/avatars/' . (int) $user_id . '.png';
}

function current_time( $type ) {
        return 'mysql' === $type ? date( 'Y-m-d H:i:s' ) : time();
}

function wp_mail( $to, $subject, $body ) {
        return true;
}

function wp_strip_all_tags( $text ) {
        return trim( strip_tags( (string) $text ) );
}

function wp_kses_post( $text ) {
        return (string) $text;
}

function translate_user_role( $label ) {
        return $label;
}

function wp_check_filetype( $name, $mimes = null ) {
        $ext = strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );

        return array( 'ext' => isset( $mimes[ $ext ] ) ? $ext : false, 'type' => false );
}

function home_url( $path = '' ) {
        return 'http://example.com/' . ltrim( (string) $path, '/' );
}

function rest_url( $path = '' ) {
        return 'http://example.com/wp-json/' . ltrim( (string) $path, '/' );
}

function wp_list_pluck( $list, $field, $index_key = null ) {
        $out = array();

        foreach ( (array) $list as $key => $item ) {
                $value = is_object( $item ) ? ( $item->$field ?? null ) : ( $item[ $field ] ?? null );

                if ( null === $index_key ) {
                        $out[] = $value;
                } else {
                        $index = is_object( $item ) ? ( $item->$index_key ?? null ) : ( $item[ $index_key ] ?? null );
                        $out[ $index ] = $value;
                }
        }

        return $out;
}

function rest_ensure_response( $data ) {
        return $data;
}

// ---------------------------------------------------------------------------
// WP_Error.
// ---------------------------------------------------------------------------

class WP_Error {

        private $errors = array();
        private $error_data = array();

        public function __construct( $code = '', $message = '', $data = null ) {
                if ( '' !== (string) $code && '' !== (string) $message ) {
                        $this->errors[ $code ] = $message;

                        if ( null !== $data ) {
                                $this->error_data[ $code ] = $data;
                        }
                }
        }

        public function get_error_code() {
                $codes = array_keys( $this->errors );

                return $codes ? $codes[0] : '';
        }

        public function get_error_codes() {
                return array_keys( $this->errors );
        }

        public function get_error_message( $code = '' ) {
                if ( '' === $code ) {
                        $code = $this->get_error_code();
                }

                return isset( $this->errors[ $code ] ) ? $this->errors[ $code ] : '';
        }

        public function add_data( $data, $code = '' ) {
                if ( '' === $code ) {
                        $code = $this->get_error_code();
                }

                if ( '' !== $code ) {
                        $this->error_data[ $code ] = $data;
                }

                return true;
        }

        public function get_error_data( $code = '' ) {
                if ( '' === $code ) {
                        $code = $this->get_error_code();
                }

                return isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
        }
}

function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
}

// ---------------------------------------------------------------------------
// wpdb shim on SQLite.
// ---------------------------------------------------------------------------

/**
 * Faithful-enough $wpdb replacement backed by PDO SQLite.
 */
class TaskFlow_Fake_WPDB {

        public $prefix = 'wp_';
        public $insert_id = 0;
        public $rows_affected = 0;

        /**
         * @var PDO
         */
        private $pdo;

        public function __construct( PDO $pdo ) {
                $this->pdo = $pdo;
        }

        public function prepare( $query, ...$args ) {
                if ( 1 === count( $args ) && is_array( $args[0] ) ) {
                        $args = $args[0];
                }

                $args = array_values( $args );
                $index = 0;

                return preg_replace_callback(
                        '/%[sdf]/',
                        function ( $match ) use ( $args, &$index ) {
                                $value = isset( $args[ $index ] ) ? $args[ $index ] : null;
                                $index++;

                                switch ( $match[0] ) {
                                        case '%d':
                                                return (string) (int) $value;
                                        case '%f':
                                                return (string) (float) $value;
                                        default:
                                                return null === $value ? 'NULL' : $this->pdo->quote( (string) $value );
                                }
                        },
                        (string) $query
                );
        }

        public function query( $sql ) {
                $this->rows_affected = $this->pdo->exec( $sql );

                if ( false === $this->rows_affected ) {
                        throw new RuntimeException( 'SQL failed: ' . $sql );
                }

                $this->insert_id = (int) $this->pdo->lastInsertId();

                return $this->rows_affected;
        }

        public function get_var( $sql ) {
                $row = $this->pdo->query( $sql )->fetch( PDO::FETCH_NUM );

                return $row ? $row[0] : null;
        }

        public function get_row( $sql, $output = ARRAY_A ) {
                $row = $this->pdo->query( $sql )->fetch( PDO::FETCH_ASSOC );

                return $row ? $row : null;
        }

        public function get_col( $sql ) {
                $rows = $this->pdo->query( $sql )->fetchAll( PDO::FETCH_NUM );
                $out  = array();

                foreach ( $rows as $row ) {
                        $out[] = $row[0];
                }

                return $out;
        }

        public function get_results( $sql, $output = ARRAY_A ) {
                $rows = $this->pdo->query( $sql )->fetchAll( PDO::FETCH_ASSOC );

                return $rows ? $rows : array();
        }

        public function insert( $table, $data, $format = null ) {
                $columns = array_keys( $data );
                $values  = array_map(
                        function ( $value ) {
                                return null === $value ? 'NULL' : ( is_numeric( $value ) && 'int' !== gettype( $value ) ? $this->pdo->quote( (string) $value ) : ( is_int( $value ) ? (string) $value : $this->pdo->quote( (string) $value ) ) );
                        },
                        array_values( $data )
                );

                $sql = sprintf(
                        'INSERT INTO %s (%s) VALUES (%s)',
                        $table,
                        implode( ', ', $columns ),
                        implode( ', ', $values )
                );

                $this->rows_affected = $this->pdo->exec( $sql );
                $this->insert_id     = (int) $this->pdo->lastInsertId();

                return $this->rows_affected;
        }

        public function update( $table, $data, $where, $format = null, $where_format = null ) {
                $set = array();

                foreach ( $data as $column => $value ) {
                        $set[] = null === $value ? "$column = NULL" : "$column = " . $this->pdo->quote( (string) $value );
                }

                $conditions = array();

                foreach ( $where as $column => $value ) {
                        $conditions[] = "$column = " . $this->pdo->quote( (string) $value );
                }

                $sql = sprintf( 'UPDATE %s SET %s WHERE %s', $table, implode( ', ', $set ), implode( ' AND ', $conditions ) );

                $this->rows_affected = $this->pdo->exec( $sql );

                return $this->rows_affected;
        }

        public function delete( $table, $where, $format = null ) {
                $conditions = array();

                foreach ( $where as $column => $value ) {
                        $conditions[] = "$column = " . $this->pdo->quote( (string) $value );
                }

                $this->rows_affected = $this->pdo->exec( sprintf( 'DELETE FROM %s WHERE %s', $table, implode( ' AND ', $conditions ) ) );

                return $this->rows_affected;
        }

        public function esc_like( $text ) {
                return addcslashes( (string) $text, '_%\\' );
        }
}

$GLOBALS['taskflow_pdo'] = new PDO( 'sqlite::memory:' );
$GLOBALS['taskflow_pdo']->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

// MySQL GREATEST() equivalent for the SQLite test database.
$GLOBALS['taskflow_pdo']->sqliteCreateFunction(
        'GREATEST',
        static function ( ...$values ) {
                return max( array_map( 'floatval', $values ) );
        },
        -1
);

require_once __DIR__ . '/../includes/class-taskflow-schema.php';

foreach ( TaskFlow_Schema::get_sqlite_schema() as $ddl ) {
        // Match the production wp_ table prefix.
        $ddl = str_replace( 'CREATE TABLE IF NOT EXISTS taskflow_', 'CREATE TABLE IF NOT EXISTS wp_taskflow_', $ddl );
        $ddl = str_replace( '"', "'", $ddl ); // SQLite accepts both; keep it SQL-ish.
        $GLOBALS['taskflow_pdo']->exec( $ddl );
}

$GLOBALS['wpdb'] = new TaskFlow_Fake_WPDB( $GLOBALS['taskflow_pdo'] );

taskflow_test_reset_users();

// The base test case needs PHPUnit; skip it for plain CLI debugging scripts.
if ( class_exists( 'PHPUnit\Framework\TestCase' ) ) {
        require_once __DIR__ . '/TaskFlow_TestCase.php';
}

// Plugin autoloader (mirrors taskflow.php).
spl_autoload_register(
        static function ( $class_name ) {
                if ( 0 !== strpos( $class_name, 'TaskFlow_' ) ) {
                        return;
                }

                $slug = strtolower( str_replace( '_', '-', $class_name ) );
                $file = dirname( __DIR__ ) . '/includes/class-' . $slug . '.php';

                if ( file_exists( $file ) ) {
                        require_once $file;
                }
        }
);
