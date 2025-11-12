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

    $table_categories = $wpdb->prefix . 'multicatalogo_category_mapping';

    $sql_categories = "CREATE TABLE $table_categories (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        source_category varchar(255) NOT NULL,
        target_category int(11) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY source_category (source_category)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_config );
    dbDelta( $sql_categories );

    multicatalogognu_migrate_category_mapping_table();

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

    $existing_currency = $wpdb->get_var($wpdb->prepare(
        "SELECT config_value FROM $table_config WHERE config_key = %s", 
        'currency_type'
    ));

    if (is_null($existing_currency)) {
        $wpdb->insert(
            $table_config,
            array(
                'config_key' => 'currency_type',
                'config_value' => 'usd'
            ),
            array('%s', '%s')
        );
    }
    
    // Programar los cron jobs
    if ( ! wp_next_scheduled( 'multicatalogo_hourly_update_json' ) ) {
        //wp_schedule_event( time(), 'thirty_minutes', 'multicatalogo_hourly_update_json' );
        //wp_schedule_event( time(), 'twicedaily', 'multicatalogo_hourly_update_json' );
        wp_schedule_event( time(), 'hourly', 'multicatalogo_hourly_update_json' );
    }
    
    if ( ! wp_next_scheduled( 'multicatalogo_hourly_upload_products' ) ) {
        //wp_schedule_event( time(), 'thirty_minutes', 'multicatalogo_hourly_upload_products' );
        //wp_schedule_event( time(), 'twicedaily', 'multicatalogo_hourly_upload_products' );
        wp_schedule_event( time(), 'hourly', 'multicatalogo_hourly_upload_products' );
    }

    if ( ! wp_next_scheduled( 'multicatalogo_hourly_update_prices_stock' ) ) {
        //wp_schedule_event( time(), 'thirty_minutes', 'multicatalogo_hourly_update_prices_stock' );
        //wp_schedule_event( time(), 'twicedaily', 'multicatalogo_hourly_update_prices_stock' );
        wp_schedule_event( time(), 'hourly', 'multicatalogo_hourly_update_prices_stock' );
    }
}

function multicatalogognu_migrate_category_mapping_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'multicatalogo_category_mapping';
    
    // Verificar si la tabla existe
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    
    if (!$table_exists) {
        return; // La tabla se creará por dbDelta
    }
    
    // Verificar si tiene la estructura antigua (columnas provider y category)
    $columns = $wpdb->get_col("DESCRIBE $table_name", 0);
    $has_old_structure = in_array('provider', $columns) && in_array('category', $columns);
    $has_new_structure = in_array('source_category', $columns) && in_array('target_category', $columns);
    
    $indexes = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name = 'provider_category'");
    if (!empty($indexes)) {
        $wpdb->query("ALTER TABLE $table_name DROP INDEX provider_category");
    }
    
    // Si tiene la estructura antigua, migrar a la nueva
    if ($has_old_structure && !$has_new_structure) {
        // Respaldar datos existentes
        $existing_mappings = $wpdb->get_results(
            "SELECT provider, category, wc_category_id, created_at, updated_at 
             FROM $table_name",
            ARRAY_A
        );
        
        // Eliminar la tabla antigua
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        // Crear la nueva tabla con la estructura correcta
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            source_category varchar(255) NOT NULL,
            target_category int(11) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY source_category (source_category)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        
        // Restaurar datos migrando el formato
        // En la estructura antigua, la combinación era: provider + category -> wc_category_id
        // En la nueva estructura es: source_category -> target_category
        if (!empty($existing_mappings)) {
            foreach ($existing_mappings as $mapping) {
                // Usar la categoría como source_category (ya no necesitamos provider)
                $source_cat = $mapping['category'];
                $target_cat = $mapping['wc_category_id'];
                
                // Solo insertar si ambos valores existen
                if (!empty($source_cat) && !empty($target_cat)) {
                    $wpdb->insert(
                        $table_name,
                        array(
                            'source_category' => $source_cat,
                            'target_category' => $target_cat,
                            'created_at' => $mapping['created_at'],
                            'updated_at' => $mapping['updated_at']
                        ),
                        array('%s', '%d', '%s', '%s')
                    );
                }
            }
        }
    }
    
    if ($has_new_structure) {
        // Verificar que tenga todas las columnas necesarias
        if (!in_array('created_at', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP");
        }
        if (!in_array('updated_at', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
        
        $correct_index = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name = 'source_category'");
        if (empty($correct_index)) {
            // Intentar crear el índice único para source_category
            $wpdb->query("ALTER TABLE $table_name ADD UNIQUE KEY source_category (source_category)");
        }
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
