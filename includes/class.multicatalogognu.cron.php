<?php
/**
 * MultiCatalogo Cron Jobs
 * @link        https://josecortesia.cl
 * @since       2.0.0
 * 
 * @package     base
 * @subpackage  base/include
 */

class cMulticatalogoGNUCron {

    /**
     * Inicializar los hooks de cron
     */
    public static function init() {
        // Hook para actualizar JSON cada hora
        add_action('multicatalogo_hourly_update_json', array('cMulticatalogoGNUCron', 'update_all_json'));
        
        // Hook para actualizar precios y stock cada hora
        add_action('multicatalogo_hourly_update_prices_stock', array('cMulticatalogoGNUCron', 'update_all_prices_stock'));
    }

    /**
     * Actualizar todos los JSON de los proveedores
     */
    public static function update_all_json() {
        error_log('[MultiCatalogo Cron] Iniciando actualización de JSON - ' . current_time('mysql'));
        
        try {
            // Actualizar Zecat
            error_log('[MultiCatalogo Cron] Actualizando Zecat...');
            cMultiCatalogoGNUApiRequest::fgetProductsZecat();
            
            // Actualizar CDO
            error_log('[MultiCatalogo Cron] Actualizando CDO...');
            cMultiCatalogoGNUApiRequest::fgetProductsCdo();
            
            // Actualizar PromoImport
            error_log('[MultiCatalogo Cron] Actualizando PromoImport...');
            cMultiCatalogoGNUApiRequest::fgetProductsPromoImport();
            
            // Combinar JSON
            error_log('[MultiCatalogo Cron] Combinando JSON...');
            self::combine_json_silent();
            
            error_log('[MultiCatalogo Cron] Actualización de JSON completada exitosamente');
            
        } catch (Exception $e) {
            error_log('[MultiCatalogo Cron] Error al actualizar JSON: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar todos los precios y stock
     */
    public static function update_all_prices_stock() {
        error_log('[MultiCatalogo Cron] Iniciando actualización de precios y stock - ' . current_time('mysql'));
        
        try {
            // Actualizar Zecat
            error_log('[MultiCatalogo Cron] Actualizando stock y precios Zecat...');
            self::update_zecat_silent();
            
            // Actualizar CDO
            error_log('[MultiCatalogo Cron] Actualizando stock y precios CDO...');
            self::update_cdo_silent();
            
            // Actualizar PromoImport
            error_log('[MultiCatalogo Cron] Actualizando stock y precios PromoImport...');
            self::update_promoimport_silent();
            
            error_log('[MultiCatalogo Cron] Actualización de precios y stock completada');
            
        } catch (Exception $e) {
            error_log('[MultiCatalogo Cron] Error al actualizar precios/stock: ' . $e->getMessage());
        }
    }

    /**
     * Combinar JSON sin respuesta AJAX (para cron)
     */
    private static function combine_json_silent() {
        $filePathZecat = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/zecat_products.json';
        $filePathCDO = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/cdo_products.json';
        $filePathPromoImport = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/promoimport_products.json';
    
        if (!file_exists($filePathZecat) || !file_exists($filePathCDO) || !file_exists($filePathPromoImport)) {
            error_log('[MultiCatalogo Cron] Uno o más archivos JSON no encontrados');
            return false;
        }
    
        $productsZecat = json_decode(file_get_contents($filePathZecat), true);
        $productsCDO = json_decode(file_get_contents($filePathCDO), true);
        $productsPromo = json_decode(file_get_contents($filePathPromoImport), true);
    
        $mergedProducts = [];
    
        // Zecat
        foreach ($productsZecat as $zecatProduct) {
            $mergedProducts[] = [
                'ID' => "zt0" . $zecatProduct['id'],
                'sku_proveedor' => $zecatProduct['external_id'],
                'nombre_del_producto' => $zecatProduct['name'],
                'descripcion' => $zecatProduct['description'],
                'precio' => $zecatProduct['price'],
                'image' => isset($zecatProduct['images'][0]['image_url'])
                    ? '<a href="' . $zecatProduct['images'][0]['image_url'] . '" target="_blank">Ver imagen</a>'
                    : '',
                'stock' => isset($zecatProduct['products'][0]['stock']) ? $zecatProduct['products'][0]['stock'] : 0,
                'proveedor' => 'ZECAT'
            ];
        }
    
        // CDO
        foreach ($productsCDO as $cdoProduct) {
            $mergedProducts[] = [
                'ID' => "ss0" . $cdoProduct['id'],
                'sku_proveedor' => $cdoProduct['code'],
                'nombre_del_producto' => $cdoProduct['name'],
                'descripcion' => $cdoProduct['description'],
                'precio' => isset($cdoProduct['variants'][0]['list_price']) ? $cdoProduct['variants'][0]['list_price'] : 0,
                'image' => isset($cdoProduct['variants'][0]['picture']['original'])
                    ? '<a href="' . $cdoProduct['variants'][0]['picture']['original'] . '" target="_blank">Ver imagen</a>'
                    : '',
                'stock' => isset($cdoProduct['variants'][0]['stock_available']) ? $cdoProduct['variants'][0]['stock_available'] : 0,
                'proveedor' => 'CDO'
            ];
        }
    
        // PromoImport
        foreach ($productsPromo as $promoProduct) {
            $mergedProducts[] = [
                'ID' => "pi0" . $promoProduct['sku'],
                'sku_proveedor' => $promoProduct['sku'],
                'nombre_del_producto' => $promoProduct['titulo'],
                'descripcion' => strip_tags($promoProduct['descripcion']),
                'precio' => $promoProduct['precio'],
                'image' => isset($promoProduct['fotoPrincipal'])
                    ? '<a href="' . $promoProduct['fotoPrincipal'] . '" target="_blank">Ver imagen</a>'
                    : '',
                'stock' => isset($promoProduct['atributos'][0]['stock']) ? intval($promoProduct['atributos'][0]['stock']) : 0,
                'proveedor' => 'promoimport'
            ];
        }
    
        $finalJson = [ 'data' => $mergedProducts ];
        $mergedFilePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';
        file_put_contents($mergedFilePath, json_encode($finalJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return true;
    }

    /**
     * Actualizar stock y precios de Zecat (versión silenciosa para cron)
     */
    private static function update_zecat_silent() {
        $filePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/zecat_products.json';
        
        if (!file_exists($filePath)) {
            return false;
        }

        $jsonContent = file_get_contents($filePath);
        $productsData = json_decode($jsonContent, true);
        
        if (!$productsData) {
            return false;
        }

        $updated = 0;
        foreach ($productsData as $productData) {
            $sku = "ZT0" . $productData['id'];
            $product_id = wc_get_product_id_by_sku($sku);
            
            if (!$product_id) {
                continue;
            }

            $product = wc_get_product($product_id);
            
            // Actualizar precio con configuración
            if (isset($productData['price'])) {
                $final_price = cMulticatalogoGNUConfig::calculate_final_price($productData['price']);
                $product->set_regular_price($final_price);
            }
            
            // Actualizar stock
            if (!empty($productData['products'])) {
                $totalStock = 0;
                foreach ($productData['products'] as $variant) {
                    $totalStock += isset($variant['stock']) ? intval($variant['stock']) : 0;
                }
                $product->set_stock_quantity($totalStock);
                $product->set_stock_status($totalStock > 0 ? 'instock' : 'outofstock');
            }
            
            $product->save();
            $updated++;
        }
        
        error_log("[MultiCatalogo Cron] Zecat: $updated productos actualizados");
        return $updated;
    }

    /**
     * Actualizar stock y precios de CDO (versión silenciosa para cron)
     */
    private static function update_cdo_silent() {
        $filePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/cdo_products.json';
        
        if (!file_exists($filePath)) {
            return false;
        }

        $jsonContent = file_get_contents($filePath);
        $productsData = json_decode($jsonContent, true);
        
        if (!$productsData) {
            return false;
        }

        $updated = 0;
        foreach ($productsData as $productData) {
            $sku = "SS0" . $productData['id'];
            $product_id = wc_get_product_id_by_sku($sku);
            
            if (!$product_id) {
                continue;
            }

            $product = wc_get_product($product_id);
            
            // Actualizar precio y stock
            if (!empty($productData['variants'])) {
                $totalStock = 0;
                $price = 0;
                
                foreach ($productData['variants'] as $variant) {
                    $totalStock += isset($variant['stock_available']) ? intval($variant['stock_available']) : 0;
                    if (isset($variant['list_price']) && $price == 0) {
                        $price = floatval($variant['list_price']);
                    }
                }
                
                if ($price > 0) {
                    $final_price = cMulticatalogoGNUConfig::calculate_final_price($price);
                    $product->set_regular_price($final_price);
                }
                
                $product->set_stock_quantity($totalStock);
                $product->set_stock_status($totalStock > 0 ? 'instock' : 'outofstock');
            }
            
            $product->save();
            $updated++;
        }
        
        error_log("[MultiCatalogo Cron] CDO: $updated productos actualizados");
        return $updated;
    }

    /**
     * Actualizar stock y precios de PromoImport (versión silenciosa para cron)
     */
    private static function update_promoimport_silent() {
        $filePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/promoimport_products.json';
        
        if (!file_exists($filePath)) {
            return false;
        }

        $jsonContent = file_get_contents($filePath);
        $productsData = json_decode($jsonContent, true);
        
        if (!$productsData) {
            return false;
        }

        $updated = 0;
        foreach ($productsData as $productData) {
            $sku = "PI0" . $productData['sku'];
            $product_id = wc_get_product_id_by_sku($sku);
            
            if (!$product_id) {
                continue;
            }

            $product = wc_get_product($product_id);
            
            // Actualizar precio
            if (isset($productData['precio'])) {
                $final_price = cMulticatalogoGNUConfig::calculate_final_price($productData['precio']);
                $product->set_regular_price($final_price);
            }
            
            // Actualizar stock
            if (!empty($productData['atributos'])) {
                $totalStock = 0;
                foreach ($productData['atributos'] as $attr) {
                    $totalStock += isset($attr['stock']) ? intval($attr['stock']) : 0;
                }
                $product->set_stock_quantity($totalStock);
                $product->set_stock_status($totalStock > 0 ? 'instock' : 'outofstock');
            }
            
            $product->save();
            $updated++;
        }
        
        error_log("[MultiCatalogo Cron] PromoImport: $updated productos actualizados");
        return $updated;
    }
}

// Inicializar los hooks de cron
cMulticatalogoGNUCron::init();
