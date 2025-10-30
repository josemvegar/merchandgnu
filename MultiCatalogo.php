<?php
/**
 * Multicatalogo Merchandising
 *
 * Plugin Name: Multicatalogo Merchandising
 * Plugin URI:  https://josecortesia.cl
 * Description: Integración de proveedor externos para Woocommerce.
 * Version:     2.0.0
 * Author:      Jose Cortesia
 * Author URI:  https://www.josecortesia.cl
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: MultiCatalogoGNU
 * Domain Path: /languages/
 *
 */

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

if(!defined('ABSPATH')){die('-1');}

//Variables de Entorno
define( 'MUTICATALOGOGNU__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MUTICATALOGOGNU__PLUGIN_URL', plugin_dir_url( __FILE__ ) );

//Clases con funcionalidades del plugin
require_once( MUTICATALOGOGNU__PLUGIN_DIR . '/includes/class.multicatalogognu.php' );
require_once( MUTICATALOGOGNU__PLUGIN_DIR . '/includes/class.multicatalogognu.api.php' );
require_once( MUTICATALOGOGNU__PLUGIN_DIR . '/includes/class.multicatalogognu.admin.php' );
require_once( MUTICATALOGOGNU__PLUGIN_DIR . '/includes/class.multicatalogognu.createcatalog.php' );
require_once( MUTICATALOGOGNU__PLUGIN_DIR . '/includes/class.multicatalogognu.stock.php' );
require_once( MUTICATALOGOGNU__PLUGIN_DIR . '/includes/class.multicatalogognu.price.php' );
require_once( MUTICATALOGOGNU__PLUGIN_DIR . '/includes/class.multicatalogognu.config.php' );
require_once( MUTICATALOGOGNU__PLUGIN_DIR . '/includes/class.multicatalogognu.categories.php' );
require_once( MUTICATALOGOGNU__PLUGIN_DIR . '/includes/class.multicatalogognu.cron.php' );

add_action( 'init', array( 'cMultiCatalogoGNU', 'init' ) );

// Agregar intervalo de 30 minutos para cron jobs
add_filter('cron_schedules', 'multicatalogo_add_cron_intervals');
function multicatalogo_add_cron_intervals($schedules) {
    // Intervalo de 30 minutos
    $schedules['thirty_minutes'] = array(
        'interval' => 30 * 60, // 1800 segundos
        'display' => __('Cada 30 minutos')
    );
    
    return $schedules;
}

// ==================== ACTIVACIÓN DEL PLUGIN ====================
register_activation_hook( __FILE__, 'multicatalogognu_activate' );

function multicatalogognu_activate() {
    global $wpdb;

    // Crear tabla de configuración
    $table_config = $wpdb->prefix . 'multicatalogo_config';
    $charset_collate = $wpdb->get_charset_collate();

    $sql_config = "CREATE TABLE $table_config (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        config_key varchar(100) NOT NULL,
        config_value text NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY config_key (config_key)
    ) $charset_collate;";

    // Crear tabla de redirección de categorías
    $table_categories = $wpdb->prefix . 'multicatalogo_category_mapping';

    $sql_categories = "CREATE TABLE $table_categories (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        provider varchar(50) NOT NULL,
        original_category varchar(255) NOT NULL,
        target_category varchar(255) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY provider_category (provider, original_category)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_config );
    dbDelta( $sql_categories );

    // Insertar configuración por defecto SOLO si no existen
    $existing_margin = $wpdb->get_var($wpdb->prepare(
        "SELECT config_value FROM $table_config WHERE config_key = %s", 
        'profit_margin_percentage'
    ));

    if (is_null($existing_margin)) {
        $wpdb->insert(
            $table_config,
            array(
                'config_key' => 'profit_margin_percentage',
                'config_value' => '50'
            ),
            array('%s', '%s')
        );
    }

    $existing_rate = $wpdb->get_var($wpdb->prepare(
        "SELECT config_value FROM $table_config WHERE config_key = %s", 
        'usd_to_clp_rate'
    ));

    if (is_null($existing_rate)) {
        $wpdb->insert(
            $table_config,
            array(
                'config_key' => 'usd_to_clp_rate',
                'config_value' => '950'
            ),
            array('%s', '%s')
        );
    }
    
    // Programar los cron jobs
    if ( ! wp_next_scheduled( 'multicatalogo_hourly_update_json' ) ) {
        wp_schedule_event( time(), 'hourly', 'multicatalogo_hourly_update_json' );
    }
    
    if ( ! wp_next_scheduled( 'multicatalogo_hourly_upload_products' ) ) {
        //wp_schedule_event( time(), 'thirty_minutes', 'multicatalogo_hourly_upload_products' );
        wp_schedule_event( time(), 'hourly', 'multicatalogo_hourly_upload_products' );
    }

    if ( ! wp_next_scheduled( 'multicatalogo_hourly_update_prices_stock' ) ) {
        //wp_schedule_event( time(), 'thirty_minutes', 'multicatalogo_hourly_update_prices_stock' );
        wp_schedule_event( time(), 'hourly', 'multicatalogo_hourly_update_prices_stock' );
    }
}

// ==================== DESACTIVACIÓN DEL PLUGIN ====================
register_deactivation_hook( __FILE__, 'multicatalogognu_deactivate' );

function multicatalogognu_deactivate() {
    // Eliminar los cron jobs programados - FORMA CORRECTA
    wp_clear_scheduled_hook('multicatalogo_hourly_update_json');
    wp_clear_scheduled_hook('multicatalogo_hourly_upload_products');
    wp_clear_scheduled_hook('multicatalogo_hourly_update_prices_stock');
}
