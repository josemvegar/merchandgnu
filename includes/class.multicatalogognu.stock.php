<?php



class cMulticatalogoGNUStock {


    public static function fUpdateStockPromoImportGlobo() {
        check_ajax_referer('stock_promoimport_nonce', 'nonce');
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes.');
        }
    
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $tamano_lote = isset($_POST['tamano_lote']) ? intval($_POST['tamano_lote']) : 2;
    

        $filePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/promoimport_products.json';
    
        if (!file_exists($filePath)) {
            wp_send_json_error('Archivo JSON no encontrado.');
        }
    
        $jsonContent = file_get_contents($filePath);
        $jsonContentUtf8 = mb_convert_encoding($jsonContent, 'UTF-8', 'auto');
        $productsData = json_decode($jsonContentUtf8, true);
    
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Error al decodificar JSON.');
        }
    
        $total_productos = count($productsData);
        $productBatch = array_slice($productsData, $offset, $tamano_lote);
        $actualizados = 0;
    
        foreach ($productBatch as $productData) {
            if (!isset($productData['sku'])) {
                continue;
            }
    
            $sku = "PI0" . $productData['sku'];
            $existingProductId = wc_get_product_id_by_sku($sku);
    
            if (!$existingProductId) {
                continue;
            }
    
            $product = wc_get_product($existingProductId);
            if (!$product) {
                continue;
            }
    

            $total_stock = 0;
            if (!empty($productData['atributos']) && is_array($productData['atributos'])) {
                foreach ($productData['atributos'] as $atributo) {
                    if (isset($atributo['stock'])) {
                        $total_stock += intval($atributo['stock']);
                    }
                }
            }
    

            $product->set_manage_stock(true);
            $product->set_stock_quantity($total_stock);
    

            $product->set_stock_status($total_stock > 0 ? 'instock' : 'outofstock');
    

            $save_result = $product->save();
    
            if ($save_result) {
                $actualizados++;
            } else {
                }
        }
    
        wp_send_json_success(array(
            'total' => $total_productos,
            'actualizados' => $actualizados,
            'offset' => $offset + $tamano_lote,
        ));
    }
    

    public static function fUpdateStockZecatGlobo() {
        check_ajax_referer('stock_zecat_nonce', 'nonce');
        
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


            if (!isset($productData['id'])) {
                continue;
            }
    
            $product_id = $productData['id'];
            $parent_sku = "ZT0" . $product_id;
    

            $parent_product_id = wc_get_product_id_by_sku($parent_sku);
            if (!$parent_product_id) {
                continue;
            }
    
            $parent_product = wc_get_product($parent_product_id);
            if (!$parent_product) {
                continue;
            }
    

            if (!isset($productData['products']) || !is_array($productData['products'])) {
                continue;
            }
    

            $hay_existencia = false;
            foreach ($productData['products'] as $variant) {
                if (isset($variant['stock']) && intval($variant['stock']) > 0) {
                    $hay_existencia = true;
                    break; // Si al menos una variante tiene stock, no es necesario seguir
                }
            }
    
            if ($hay_existencia) {

                $parent_product->set_stock_status('instock');


                $parent_product->set_manage_stock(true);
                $parent_product->set_stock_quantity(1000);
            } else {

                $parent_product->set_stock_status('outofstock');

                $parent_product->set_manage_stock(false);


            }
    

            $save_result = $parent_product->save();
    
            if ($save_result) {

                $actualizados++;
            } else {
                }
    
            
        }
    

        wp_send_json_success(array(
            'total' => $total_productos,
            'actualizados' => $actualizados,
            'offset' => $offset + $tamano_lote,
        ));
    }

    public static function fUpdateStockCDOGlobo() {

        check_ajax_referer('stock_cdo_nonce', 'nonce');
        
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


            $sku = "SS" . $productData['id'];
                        

            $existingProductId = wc_get_product_id_by_sku($sku);

            if ($existingProductId) {

                $product = wc_get_product($existingProductId);
                if (!$product) {
                    continue;
                }

                $new_stock = 0; // Inicializar el stock


                if (isset($productData['stock_available'])) {
                    $new_stock = intval($productData['stock_available']);
                }

                elseif (!empty($productData['variants']) && is_array($productData['variants'])) {
                    foreach ($productData['variants'] as $variant) {
                        if (isset($variant['stock_available'])) {
                            $new_stock += intval($variant['stock_available']);
                        }
                    }
                } else {

                    continue;
                }


                $product->set_stock_quantity($new_stock);
                $product->set_manage_stock(true); // Asegurar que la gestión de stock está habilitada


                if ($new_stock > 0) {
                    $product->set_stock_status('instock');
                } else {
                    $product->set_stock_status('outofstock');
                }


                $save_result = $product->save();

                if ($save_result) {

                    $actualizados++;
                } else {
                    }
            } else {

                }            
    
            
        }
    

        wp_send_json_success(array(
            'total' => $total_productos,
            'actualizados' => $actualizados,
            'offset' => $offset + $tamano_lote,
        ));

    }






































    
    public static function fUpdateStockGlobo() {
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        

        $nonce_actions = [
            'promoimport' => 'stock_promoimport_nonce',
            'zecat' => 'stock_zecat_nonce', 
            'cdo' => 'stock_cdo_nonce'
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
            $updated = self::update_product_stock($productData);
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

    
    public static function update_product_stock($productData) {

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
            return self::update_variable_product_stock($product, $productData, $sku);
        } else {
            return self::update_simple_product_stock($product, $productData, $sku);
        }
    }

    
    private static function update_variable_product_stock($parent_product, $productData, $parent_sku) {
        $has_stock = false;
        $total_variations_updated = 0;
        

        if (!empty($productData['variations']) && is_array($productData['variations'])) {
            foreach ($productData['variations'] as $variation) {
                $variation_stock = self::get_variation_stock($variation);
                
                if ($variation_stock > 0) {
                    $has_stock = true;
                }
                

                if (isset($variation['sku'])) {
                    $variation_updated = self::update_variation_stock($variation['sku'], $variation_stock);
                    if ($variation_updated) {
                        $total_variations_updated++;
                    }
                }
            }
        }


        $parent_product->set_manage_stock(false); // No gestionar stock a nivel padre
        $parent_product->set_stock_status($has_stock ? 'instock' : 'outofstock');

        $save_result = $parent_product->save();

        if ($save_result) {
            return true;
        } else {
            return false;
        }
    }

    
    private static function get_variation_stock($variation) {
        return isset($variation['Stock']) ? intval($variation['Stock']) : 0;
    }

    
    private static function update_simple_product_stock($product, $productData, $sku) {
        $new_stock = self::get_simple_stock($productData);


        $product->set_manage_stock(true);
        $product->set_stock_quantity($new_stock);
        $product->set_stock_status($new_stock > 0 ? 'instock' : 'outofstock');

        $save_result = $product->save();

        if ($save_result) {
            return true;
        } else {
            return false;
        }
    }

    
    private static function get_simple_stock($productData) {
        return isset($productData['Stock']) ? intval($productData['Stock']) : 0;
    }

    
    private static function update_variation_stock($variation_sku, $stock) {
        $variation_id = wc_get_product_id_by_sku($variation_sku);
        
        if ($variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation) {
                $variation->set_manage_stock(true);
                $variation->set_stock_quantity($stock);
                $variation->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
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