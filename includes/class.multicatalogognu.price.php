<?php





class cMulticatalogoGNUPrice {


    public static function fUpdatePriceZecat() {

        check_ajax_referer('price_zecat_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes.');
        }
    
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $tamano_lote = isset($_POST['tamano_lote']) ? intval($_POST['tamano_lote']) : 2;
    

        $filePathZecat = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/zecat_products.json';
    

        if (!file_exists($filePathZecat)) {
            wp_send_json_error('Archivo JSON no encontrado.');
        }
    

        $jsonContent = file_get_contents($filePathZecat);
        $jsonContentUtf8 = mb_convert_encoding($jsonContent, 'UTF-8', 'auto');
        $productsData = json_decode($jsonContentUtf8, true);
    

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Error al decodificar JSON.');
        }
    

        $total_productos = count($productsData);
    

        $productBatch = array_slice($productsData, $offset, $tamano_lote);
        
        $actualizados = 0;
        $datos_api = [];
    
        foreach ($productBatch as $productData) {


            
        }
    

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
    

        $filePathCDO = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/cdo_products.json';
    

        if (!file_exists($filePathCDO)) {
            wp_send_json_error('Archivo JSON no encontrado.');
        }
    

        $jsonContent = file_get_contents($filePathCDO);
        $jsonContentUtf8 = mb_convert_encoding($jsonContent, 'UTF-8', 'auto');
        $productsData = json_decode($jsonContentUtf8, true);
    

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Error al decodificar JSON.');
        }
    

        $total_productos = count($productsData);
    

        $productBatch = array_slice($productsData, $offset, $tamano_lote);
        
        $actualizados = 0;
        $datos_api = [];
    
        foreach ($productBatch as $productData) {


            
        }
    

        wp_send_json_success(array(
            'total' => $total_productos,
            'actualizados' => $actualizados,
            'offset' => $offset + $tamano_lote,
        ));


    }
























    public static function fUpdatePriceGlobo() {
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        

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


        if (!isset($allProductsData['data']) || !is_array($allProductsData['data'])) {
            wp_send_json_error('Estructura JSON inválida. Se esperaba array "data".');
        }
    

        $providerProducts = array_filter($allProductsData['data'], function($product) use ($provider) {
            return isset($product['proveedor']) && strtoupper($product['proveedor']) === strtoupper($provider);
        });
    

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

    
    public static function update_product_price($productData) {

        $sku = self::generate_sku($productData);
        
        if (!$sku) {
            return false;
        }

        $existingProductId = wc_get_product_id_by_sku($sku);
        
        if (!$existingProductId) {
            return false;
        }

        $product = wc_get_product($existingProductId);
        if (!$product) {
            return false;
        }


        $isVariable = isset($productData['isVariable']) ? ($productData['isVariable'] == true ? true : false) : false;
        
        if ($isVariable && $product->is_type('variable')) {
            return self::update_variable_product_price($product, $productData, $sku);
        } else {
            return self::update_simple_product_price($product, $productData, $sku);
        }
    }

    
    private static function update_variable_product_price($parent_product, $productData, $parent_sku) {
        $total_variations_updated = 0;
        

        if (!empty($productData['variations']) && is_array($productData['variations'])) {
            foreach ($productData['variations'] as $variation) {
                $variation_price = self::get_variation_price($variation);
                

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
            return true;
        } else {
            return false;
        }
    }

    
    private static function get_variation_price($variation) {
        return isset($variation['Precio']) ? floatval($variation['Precio']) : 0;
    }

    
    private static function get_parent_price($productData) {
        return isset($productData['precio']) ? floatval($productData['precio']) : 0;
    }

    
    private static function update_simple_product_price($product, $productData, $sku) {
        $new_price = self::get_simple_price($productData);


        if ($new_price > 0) {
            $product->set_price($new_price);
            $product->set_regular_price($new_price);
        }

        $save_result = $product->save();

        if ($save_result) {
            return true;
        } else {
            return false;
        }
    }

    
    private static function get_simple_price($productData) {
        return isset($productData['precio']) ? floatval($productData['precio']) : 0;
    }

    
    private static function update_variation_price($variation_sku, $price) {
        $variation_id = wc_get_product_id_by_sku($variation_sku);
        
        if ($variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation) {
                $variation->set_price($price);
                $variation->set_regular_price($price);
                $save_result = $variation->save();
                
                if ($save_result) {
                    return true;
                }
            }
        } else {
            }
        
        return false;
    }

    
    private static function generate_sku($productData) {
        if (!isset($productData['proveedor'])) {
            return false;
        }

        $provider = strtoupper($productData['proveedor']);
        $prefixes = [
            'PROMOIMPORT' => 'pi0',
            'ZECAT' => 'zt0', 
            'CDO' => 'SS'
        ];

        if (!isset($prefixes[$provider])) {
            return false;
        }


        if (isset($productData['ID']) && !empty($productData['ID'])) {
            $id = $productData['ID'];
        } elseif (isset($productData['sku_proveedor']) && !empty($productData['sku_proveedor'])) {
            $id = $productData['sku_proveedor'];
        } else {
            return false;
        }


        foreach ($prefixes as $prefijo) {
            if (strpos($id, $prefijo) === 0) {
                $id = substr($id, strlen($prefijo));
                break;
            }
        }

        return $prefixes[$provider] . $id;
    }



}