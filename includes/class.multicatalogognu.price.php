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
























    public static function fUpdatePriceGlobo() {
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        
        // Verificar nonce según el proveedor
        $nonce_actions = [
            'promoimport' => 'price_promoimport_nonce',
            'zecat' => 'price_zecat_nonce', 
            'cdo' => 'price_cdo_nonce'
        ];
        
        if (!isset($nonce_actions[$provider])) {
            wp_send_json_error('Proveedor no válido.');
        }
        
        check_ajax_referer($nonce_actions[$provider], 'nonce');
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes.');
        }
    
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $tamano_lote = isset($_POST['tamano_lote']) ? intval($_POST['tamano_lote']) : 10;
    
        // Ruta al archivo JSON unificado
        $filePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';
    
        if (!file_exists($filePath)) {
            wp_send_json_error('Archivo JSON no encontrado.');
        }
    
        $jsonContent = file_get_contents($filePath);
        $jsonContentUtf8 = mb_convert_encoding($jsonContent, 'UTF-8', 'auto');
        $allProductsData = json_decode($jsonContentUtf8, true);
    
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Error al decodificar JSON.');
        }

        // Verificar estructura del JSON (con array 'data')
        if (!isset($allProductsData['data']) || !is_array($allProductsData['data'])) {
            wp_send_json_error('Estructura JSON inválida. Se esperaba array "data".');
        }
    
        // Filtrar productos por proveedor
        $providerProducts = array_filter($allProductsData['data'], function($product) use ($provider) {
            return isset($product['proveedor']) && strtoupper($product['proveedor']) === strtoupper($provider);
        });
    
        // Reindexar array después del filtro
        $providerProducts = array_values($providerProducts);
    
        $total_productos = count($providerProducts);
        $productBatch = array_slice($providerProducts, $offset, $tamano_lote);
        $actualizados = 0;
    
        foreach ($productBatch as $productData) {
            $updated = self::update_product_price($productData);
            if ($updated) {
                $actualizados++;
            }
        }
    
        wp_send_json_success(array(
            'total' => $total_productos,
            'actualizados' => $actualizados,
            'offset' => $offset + $tamano_lote,
            'provider' => $provider
        ));
    }

    /**
     * Lógica centralizada para actualizar precio de un producto
     */
    public static function update_product_price($productData) {
        // Determinar SKU según proveedor
        $sku = self::generate_sku($productData);
        
        if (!$sku) {
            error_log("No se pudo generar SKU para producto (precio): " . print_r($productData['ID'] ?? 'Sin ID', true));
            return false;
        }

        $existingProductId = wc_get_product_id_by_sku($sku);
        
        if (!$existingProductId) {
            error_log("Producto no encontrado con SKU (precio): " . $sku);
            return false;
        }

        $product = wc_get_product($existingProductId);
        if (!$product) {
            error_log("No se pudo obtener el objeto del producto con SKU (precio): " . $sku);
            return false;
        }

        // Manejar precio según tipo de producto
        $isVariable = isset($productData['isVariable']) ? ($productData['isVariable'] == true ? true : false) : false;
        
        if ($isVariable && $product->is_type('variable')) {
            return self::update_variable_product_price($product, $productData, $sku);
        } else {
            return self::update_simple_product_price($product, $productData, $sku);
        }
    }

    /**
     * Actualizar precio para productos variables
     */
    private static function update_variable_product_price($parent_product, $productData, $parent_sku) {
        $total_variations_updated = 0;
        
        // Verificar si hay variaciones para actualizar
        if (!empty($productData['variations']) && is_array($productData['variations'])) {
            foreach ($productData['variations'] as $variation) {
                $variation_price = self::get_variation_price($variation);
                
                // Actualizar variación individual si existe
                if (isset($variation['sku']) && $variation_price > 0) {
                    $variation_updated = self::update_variation_price($variation['sku'], $variation_price);
                    if ($variation_updated) {
                        $total_variations_updated++;
                    }
                }
            }
        }

        $parent_product->set_price('');
        $parent_product->set_regular_price('');
        $parent_product->set_sale_price('');
        $parent_price = self::get_parent_price($productData);

        $save_result = $parent_product->save();

        if ($save_result) {
            error_log("Precio producto variable actualizado - SKU: " . $parent_sku . " - Precio: " . $parent_price . " - Variaciones actualizadas: " . $total_variations_updated);
            return true;
        } else {
            error_log("Error al guardar precio producto variable con SKU: " . $parent_sku);
            return false;
        }
    }

    /**
     * Obtener precio para una variación según proveedor
     */
    private static function get_variation_price($variation) {
        return isset($variation['Precio']) ? floatval($variation['Precio']) : 0;
    }

    /**
     * Obtener precio para producto padre
     */
    private static function get_parent_price($productData) {
        return isset($productData['precio']) ? floatval($productData['precio']) : 0;
    }

    /**
     * Actualizar precio para productos simples
     */
    private static function update_simple_product_price($product, $productData, $sku) {
        $new_price = self::get_simple_price($productData);

        // Actualizar producto
        if ($new_price > 0) {
            $product->set_price($new_price);
            $product->set_regular_price($new_price);
        }

        $save_result = $product->save();

        if ($save_result) {
            error_log("Precio actualizado para SKU: " . $sku . " - Precio: " . $new_price . " - Proveedor: " . $productData['proveedor']);
            return true;
        } else {
            error_log("Error al guardar el precio para el producto con SKU: " . $sku);
            return false;
        }
    }

    /**
     * Obtener precio para productos simples según proveedor
     */
    private static function get_simple_price($productData) {
        return isset($productData['precio']) ? floatval($productData['precio']) : 0;
    }

    /**
     * Actualizar precio de variación individual
     */
    private static function update_variation_price($variation_sku, $price) {
        $variation_id = wc_get_product_id_by_sku($variation_sku);
        
        if ($variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation) {
                $variation->set_price($price);
                $variation->set_regular_price($price);
                $save_result = $variation->save();
                
                if ($save_result) {
                    error_log("Precio variación actualizado - SKU: " . $variation_sku . " - Precio: " . $price);
                    return true;
                }
            }
        } else {
            error_log("Variación no encontrada con SKU (precio): " . $variation_sku);
        }
        
        return false;
    }

    /**
     * Generar SKU según proveedor (misma función que en stock)
     */
    private static function generate_sku($productData) {
        if (!isset($productData['proveedor'])) {
            return false;
        }

        $provider = strtoupper($productData['proveedor']);
        $prefixes = [
            'PROMOIMPORT' => 'pi0',
            'ZECAT' => 'zt0', 
            'CDO' => 'ss0'
        ];

        if (!isset($prefixes[$provider])) {
            return false;
        }

        // Usar ID o sku_proveedor según disponibilidad
        if (isset($productData['ID']) && !empty($productData['ID'])) {
            $id = $productData['ID'];
        } elseif (isset($productData['sku_proveedor']) && !empty($productData['sku_proveedor'])) {
            $id = $productData['sku_proveedor'];
        } else {
            return false;
        }

        // Remover prefijo si ya existe (para evitar duplicados)
        foreach ($prefixes as $prefijo) {
            if (strpos($id, $prefijo) === 0) {
                $id = substr($id, strlen($prefijo));
                break;
            }
        }

        return $prefixes[$provider] . $id;
    }



}