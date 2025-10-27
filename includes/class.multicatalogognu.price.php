<?php
/**
 * WooIntcomex Admin Clases plugin
 * @link        https://josecortesia.cl
 * @since       1.0.0
 * 
 * @package     base
 * @subpackage  base/include
 */




class cMulticatalogoGNUPrice {


    public static function fUpdatePriceZecat() {

        check_ajax_referer('price_zecat_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes.');
        }
    
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $tamano_lote = isset($_POST['tamano_lote']) ? intval($_POST['tamano_lote']) : 2;
    
        // Ruta al archivo JSON de Zecat
        $filePathZecat = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/zecat_products.json';
    
        // Verificar si el archivo existe
        if (!file_exists($filePathZecat)) {
            wp_send_json_error('Archivo JSON no encontrado.');
        }
    
        // Obtener y decodificar el contenido JSON
        $jsonContent = file_get_contents($filePathZecat);
        $jsonContentUtf8 = mb_convert_encoding($jsonContent, 'UTF-8', 'auto');
        $productsData = json_decode($jsonContentUtf8, true);
    
        // Verificar si la decodificación fue exitosa
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Error al decodificar JSON.');
        }
    
        // Total de productos
        $total_productos = count($productsData);
    
        // Obtener el lote de productos a procesar
        $productBatch = array_slice($productsData, $offset, $tamano_lote);
        
        $actualizados = 0;
        $datos_api = [];
    
        foreach ($productBatch as $productData) {


            
        }
    
        // Devolver la respuesta JSON con el progreso
        wp_send_json_success(array(
            'total' => $total_productos,
            'actualizados' => $actualizados,
            'offset' => $offset + $tamano_lote,
        ));


    }


    public static function fUpdatePriceCDO() {

        check_ajax_referer('price_cdo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes.');
        }
    
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $tamano_lote = isset($_POST['tamano_lote']) ? intval($_POST['tamano_lote']) : 2;
    
        // Ruta al archivo JSON de Zecat
        $filePathCDO = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/cdo_products.json';
    
        // Verificar si el archivo existe
        if (!file_exists($filePathCDO)) {
            wp_send_json_error('Archivo JSON no encontrado.');
        }
    
        // Obtener y decodificar el contenido JSON
        $jsonContent = file_get_contents($filePathCDO);
        $jsonContentUtf8 = mb_convert_encoding($jsonContent, 'UTF-8', 'auto');
        $productsData = json_decode($jsonContentUtf8, true);
    
        // Verificar si la decodificación fue exitosa
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Error al decodificar JSON.');
        }
    
        // Total de productos
        $total_productos = count($productsData);
    
        // Obtener el lote de productos a procesar
        $productBatch = array_slice($productsData, $offset, $tamano_lote);
        
        $actualizados = 0;
        $datos_api = [];
    
        foreach ($productBatch as $productData) {


            
        }
    
        // Devolver la respuesta JSON con el progreso
        wp_send_json_success(array(
            'total' => $total_productos,
            'actualizados' => $actualizados,
            'offset' => $offset + $tamano_lote,
        ));


    }



}