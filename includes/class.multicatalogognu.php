<?php
/**
 * Initial options MultiCatalogoGNU
 * @link        https://josecortesia.cl
 * @since       1.0.0
 * 
 * @package     base
 * @subpackage  base/include
 */


class cMultiCatalogoGNU {

    private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::init_hooks();
		}
	}

	/**
	 * Initializes WordPress hooks
	 */
	private static function init_hooks() {
		self::$initiated = true;

		add_action( 'admin_enqueue_scripts', array( 'cMultiCatalogoGNU', 'load_resources' ) );
        
        add_action( 'admin_menu', array( 'cMulticatalogoGNUAdmin', 'fMultiCatalogoGNUAdmin' ));

		
		add_action( 'wp_ajax_ActualizarCatalogoZecat', array( 'cMultiCatalogoGNUApiRequest', 'fgetProductsZecat' ));
		add_action( 'wp_ajax_ActualizarCatalogoCDO', array( 'cMultiCatalogoGNUApiRequest', 'fgetProductsCdo' ));
		add_action( 'wp_ajax_ActualizarCatalogoPromoImport', array( 'cMultiCatalogoGNUApiRequest', 'fgetProductsPromoImport' ));

		add_action('wp_ajax_PublicarProductosPromoImport', array( 'cMulticatalogoGNUCatalog', 'fcreateWooCommerceProductsFromPromoImportJsonGlobo' ));
		
		add_action('wp_ajax_PublicarProductosZecat', array( 'cMulticatalogoGNUCatalog', 'fcreateWooCommerceProductsFromJsonGlobo' ));

		add_action('wp_ajax_PublicarProductosCDO', array( 'cMulticatalogoGNUCatalog', 'fcreateWooCommerceProductsFromCDOJsonGlobo' ));

		add_action( 'wp_ajax_ActualizarStockZecat', array( 'cMulticatalogoGNUStock', 'fUpdateStockZecatGlobo' ));
		add_action( 'wp_ajax_ActualizarStockCDO', array( 'cMulticatalogoGNUStock', 'fUpdateStockCDOGlobo' ));
		add_action( 'wp_ajax_ActualizarStockPromoImport', array( 'cMulticatalogoGNUStock', 'fUpdateStockPromoImportGlobo' ));
		// Agregar esta única acción para la función unificada:
		add_action( 'wp_ajax_fUpdateStockGlobo', array( 'cMulticatalogoGNUStock', 'fUpdateStockGlobo' ));

		add_action( 'wp_ajax_ActualizarPrecioZecat', array( 'cMulticatalogoGNUPrice', 'fUpdatePriceZecat' ));
	    add_action( 'wp_ajax_ActualizarPrecioCDO', array( 'cMulticatalogoGNUPrice', 'fUpdatePriceCDO' ));

		add_action( 'wp_ajax_datatables_endpoint_merchan',			array( 'cMulticatalogoGNUAdmin',	  'fAjaxEndpointMerchan'));
		add_action( 'wp_ajax_no_priv_datatables_endpoint_merchan', 	array( 'cMulticatalogoGNUAdmin',	  'fAjaxEndpointMerchan')); 

		add_action( 'wp_ajax_ActualizarListaProductos', 	array( 'cMulticatalogoGNUAdmin',	  'combinar_json_zecat_cdo')); 



	}


	/**
	 * Cargar los JS y CSS
	 */
	public static function load_resources() {

		//Cargar Resources Vendor
		wp_enqueue_script('jquery.dataTables.min',		MUTICATALOGOGNU__PLUGIN_URL . 'admin/vendor/datatables/jquery.dataTables.min.js', array('jquery'), '1.11.3');
		wp_enqueue_script('dataTables.responsive.min',	MUTICATALOGOGNU__PLUGIN_URL . 'admin/vendor/datatables/dataTables.responsive.min.js', array('jquery'), '2.2.9');
		wp_enqueue_script('dataTables.select.min',		MUTICATALOGOGNU__PLUGIN_URL . 'admin/vendor/datatables/dataTables.select.min.js', array('jquery'), '1.3.3');
		//wp_enqueue_script('dataTables.buttons.min',		MUTICATALOGOGNU__PLUGIN_URL . 'admin/vendor/datatables/dataTables.buttons.min.js', array('jquery'), '2.1.0');
		//wp_enqueue_script('pdfmake.min.js',				MUTICATALOGOGNU__PLUGIN_URL . 'admin/vendor/datatables/pdfmake.min.js', array('jquery'), '1.0');
		//wp_enqueue_script('vfs_fonts',					MUTICATALOGOGNU__PLUGIN_URL . 'admin/vendor/datatables/vfs_fonts.js', array('jquery'), '1.0');
		//wp_enqueue_script('jszip.min',					MUTICATALOGOGNU__PLUGIN_URL . 'admin/vendor/datatables/jszip.min.js', array('jquery'), '1.0');
		//wp_enqueue_script('buttons.print.min',			MUTICATALOGOGNU__PLUGIN_URL . 'admin/vendor/datatables/buttons.print.min.js', array('jquery'), '1.0');
		//wp_enqueue_script('buttons.html5.min',			MUTICATALOGOGNU__PLUGIN_URL . 'admin/vendor/datatables/buttons.html5.min.js', array('jquery'), '1.0');
		wp_enqueue_style('jquery.dataTables.min',		MUTICATALOGOGNU__PLUGIN_URL . 'admin/vendor/datatables/jquery.dataTables.min.css', array(), '1.11.3', 'all');
		wp_enqueue_style('responsive.dataTables.min',	MUTICATALOGOGNU__PLUGIN_URL . 'admin/vendor/datatables/responsive.dataTables.min.css', array(), '2.2.9', 'all');
		wp_enqueue_style('select.dataTables.min',		MUTICATALOGOGNU__PLUGIN_URL . 'admin/vendor/datatables/select.dataTables.min.css', array(), '1.3.3', 'all');
		//wp_enqueue_style('buttons.dataTables.min',		MUTICATALOGOGNU__PLUGIN_URL . 'admin/vendor/datatables/buttons.dataTables.min.css', array(), '2.1.0', 'all');
		wp_enqueue_style('font-awesome.min',			MUTICATALOGOGNU__PLUGIN_URL . 'admin/vendor/font-awesome/font-awesome.min.css', array(), '4.7.0', 'all');
        
		
		//Cargar Resources Admin
        wp_enqueue_style('admin-multicatalogognu',MUTICATALOGOGNU__PLUGIN_URL . 'admin/css/admin-multicatalogognu.css', array(), '1.3', 'all');
        wp_enqueue_script('admin-multicatalogognu',MUTICATALOGOGNU__PLUGIN_URL . 'admin/js/admin-multicatalogognu.js', array('jquery'), '2.4');
		
        //Funciones personalizadas
		wp_localize_script('admin-multicatalogognu','Global', array('url'    => admin_url( 'admin-ajax.php' ),'nonce'  => wp_create_nonce( 'segu' )));

        wp_localize_script('admin-multicatalogognu','fgetProductsZecat',	array('action' => 'ActualizarCatalogoZecat'));
        wp_localize_script('admin-multicatalogognu','fgetProductsCdo',	array('action' => 'ActualizarCatalogoCDO'));
		wp_localize_script('admin-multicatalogognu','fgetProductsPromoImport',	array('action' => 'ActualizarCatalogoPromoImport'));


	    ///funcion para procesar por lotes el stock
		wp_localize_script('admin-multicatalogognu', 'fcreateWooCommerceProductsFromPromoImportJsonGlobo',
		    array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('publicar_promoimport_nonce'),
				'action'   => 'PublicarProductosPromoImport',
			)
		);

	    ///funcion para procesar por lotes el stock
		wp_localize_script('admin-multicatalogognu', 'fcreateWooCommerceProductsFromJsonGlobo',
		    array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('publicar_zecat_nonce'),
				'action'   => 'PublicarProductosZecat',
			)
		);

	    ///funcion para procesar por lotes el stock
		wp_localize_script('admin-multicatalogognu', 'fcreateWooCommerceProductsFromCDOJsonGlobo',
		    array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('publicar_cdo_nonce'),
				'action'   => 'PublicarProductosCDO',
			)
		);


	    ///funcion para procesar por lotes el stock
		wp_localize_script('admin-multicatalogognu', 'fUpdateStockZecatGlobo',
		    array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('stock_zecat_nonce'),
				'action'   => 'ActualizarStockZecat',
			)
		);


	    ///funcion para procesar por lotes el stock
		wp_localize_script('admin-multicatalogognu', 'fUpdateStockCDOGlobo',
		    array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('stock_cdo_nonce'),
				'action'   => 'ActualizarStockCDO',
			)
		);

	    ///funcion para procesar por lotes el stock
		wp_localize_script('admin-multicatalogognu', 'fUpdateStockPromoImportGlobo',
		    array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('stock_promoimport_nonce'),
				'action'   => 'ActualizarStockPromoImport',
			)
		);

		///funcion para procesar por lotes el stock
		wp_localize_script('admin-multicatalogognu', 'fUpdateStockGlobo',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce_promoimport' => wp_create_nonce('stock_promoimport_nonce'),
				'nonce_zecat' => wp_create_nonce('stock_zecat_nonce'),
				'nonce_cdo' => wp_create_nonce('stock_cdo_nonce'),
				'action' => 'fUpdateStockGlobo',
			)
		);


			    ///funcion para procesar por lotes el stock
		wp_localize_script('admin-multicatalogognu', 'fUpdatePriceZecat',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('price_zecat_nonce'),
				'action'   => 'ActualizarPrecioZecat',
				)
			);
		

		wp_localize_script('admin-multicatalogognu', 'fUpdatePriceCDO',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('price_cdo_nonce'),
				'action'   => 'ActualizarPrecioCDO',
				)
        );

		wp_localize_script('admin-multicatalogognu', 'combinar_json_zecat_cdo',
		array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('lista_productos_nonce'),
			'action'   => 'ActualizarListaProductos',
			)
	);


    }

}