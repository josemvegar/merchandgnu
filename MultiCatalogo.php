<?php
/**
 * Multicatalogo Merchandising
 *
 * Plugin Name: Multicatalogo Merchandising
 * Plugin URI:  https://josecortesia.cl
 * Description: Integración de proveedor externos para Woocommerce.
 * Version:     1.4.0
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


add_action( 'init', array( 'cMultiCatalogoGNU', 'init' ) );








