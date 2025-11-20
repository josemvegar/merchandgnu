<?php


class cMulticatalogoGNUCron {

    
    public static function init() {

        add_action('multicatalogo_hourly_update_json', array('cMulticatalogoGNUCron', 'update_all_json'));
        

        add_action('multicatalogo_hourly_update_prices_stock', array('cMulticatalogoGNUCron', 'update_all_prices_stock'));


        add_action('multicatalogo_hourly_upload_products', array('cMulticatalogoGNUCron', 'upload_all_products'));


        add_action('multicatalogo_batch_upload_zecat', array('cMulticatalogoGNUCron', 'handle_batch_upload'), 10, 2);
        add_action('multicatalogo_batch_upload_cdo', array('cMulticatalogoGNUCron', 'handle_batch_upload'), 10, 2);
        add_action('multicatalogo_batch_upload_promoimport', array('cMulticatalogoGNUCron', 'handle_batch_upload'), 10, 2);


        add_action('multicatalogo_batch_stock_zecat', array('cMulticatalogoGNUCron', 'handle_batch_stock'), 10, 2);
        add_action('multicatalogo_batch_stock_cdo', array('cMulticatalogoGNUCron', 'handle_batch_stock'), 10, 2);
        add_action('multicatalogo_batch_stock_promoimport', array('cMulticatalogoGNUCron', 'handle_batch_stock'), 10, 2);


        add_action('multicatalogo_batch_price_zecat', array('cMulticatalogoGNUCron', 'handle_batch_price'), 10, 2);
        add_action('multicatalogo_batch_price_cdo', array('cMulticatalogoGNUCron', 'handle_batch_price'), 10, 2);
        add_action('multicatalogo_batch_price_promoimport', array('cMulticatalogoGNUCron', 'handle_batch_price'), 10, 2);

    }

    
    public static function update_all_json() {
        error_log('[MultiCatalogo Cron] Iniciando actualización de JSON - ' . current_time('mysql'));
        
        try {

            cMultiCatalogoGNUApiRequest::fgetProductsZecat();
            

            cMultiCatalogoGNUApiRequest::fgetProductsCdo();
            

            cMultiCatalogoGNUApiRequest::fgetProductsPromoImport();
            

            self::combine_json_silent();
            
            } catch (Exception $e) {
            }
    }

    
    public static function upload_all_products() {
        error_log('[MultiCatalogo Cron] Iniciando subida de productos - ' . current_time('mysql'));
        
        try {


            self::clean_duplicate_products();
            self::clean_products_without_images();

            self::upload_from_json("ZECAT");

            self::upload_from_json("CDO");

            self::upload_from_json("promoimport");

            } catch (Exception $e) {
            }
    }

    
    public static function update_all_prices_stock() {
        error_log('[MultiCatalogo Cron] Iniciando actualización de precios y stock - ' . current_time('mysql'));
        
        try {

            self::update_stock_from_json('ZECAT');
            self::update_price_from_json('ZECAT');
            

            self::update_stock_from_json('CDO');
            self::update_price_from_json('CDO');
            

            self::update_stock_from_json('promoimport');
            self::update_price_from_json('promoimport');
            
            } catch (Exception $e) {
            }
    }

    
    private static function combine_json_silent() {
        $filePathZecat = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/zecat_products.json';
        $filePathCDO = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/cdo_products.json';
        $filePathPromoImport = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/promoimport_products.json';

        if (!file_exists($filePathZecat) || !file_exists($filePathCDO) || !file_exists($filePathPromoImport)) {
            return false;
        }

        $productsZecat = json_decode(file_get_contents($filePathZecat), true);
        $productsCDO = json_decode(file_get_contents($filePathCDO), true);
        $productsPromo = json_decode(file_get_contents($filePathPromoImport), true);


        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        $mergedProducts = [];


        foreach ($productsZecat as $zecatProduct) {
            $families = [];
            $images = [];
            $variableAttributes = [];
            $infoAttributes = [];
            $variations = [];

            $precioFinalZecat = cMulticatalogoGNUConfig::calculate_final_price($zecatProduct['price']);

            foreach ($zecatProduct['families'] as $family) {
                $families[] = mb_convert_case(trim($family['description']), MB_CASE_TITLE, "UTF-8");
            }
            foreach ($zecatProduct['images'] as $image) {
                $images[] = $image['image_url'];
            }
            foreach ($zecatProduct['products'] as $index => $varAttr) {
                if ($varAttr['size'] !== '' && $varAttr['color'] !== '') {
                    $variableAttributes['Tamaño'][] = $varAttr['size'];
                    $variableAttributes['Color'][] = $varAttr['color'];

                    $added = ['Combinations' => ['Tamaño' => $varAttr['size'], 'Color' => $varAttr['color']], 'Stock' => $varAttr['stock'], 'Precio' => $precioFinalZecat, 'sku' => 'zt0' . $varAttr['sku']];
                }else{
                    $variableAttributes['Variante'][] = $varAttr['element_description_1'] . ' / ' . $varAttr['element_description_2'] . ' / ' . $varAttr['element_description_3'];

                    $added = [ 'Combinations' => ['Variante' => $varAttr['element_description_1'] . ' / ' . $varAttr['element_description_2'] . ' / ' . $varAttr['element_description_3']], 
                            'Stock' => $varAttr['stock'],
                            'Precio' => $precioFinalZecat,
                            'sku' => 'zt0' . $varAttr['id'],
                            'sku_proveedor' => $varAttr['sku']
                    ];
                }

                $variations[] = $added;
            }
            foreach ($zecatProduct['subattributes'] as $infoAttr) {
                $infoAttributes[$infoAttr['attribute_name']] = trim($infoAttr['name']);
            }

            $categorias_mapeadas = cMulticatalogoGNUCategories::apply_category_mapping($families);

            $mergedProducts[] = [
                'ID' => "zt0" . $zecatProduct['id'],
                'sku_proveedor' => $zecatProduct['external_id'],
                'nombre_del_producto' => $zecatProduct['name'],
                'descripcion' => $zecatProduct['description'],
                'precio' => $precioFinalZecat,
                'image' => isset($zecatProduct['images'][0]['image_url'])
                    ? '<a href="' . $zecatProduct['images'][0]['image_url'] . '" target="_blank">Ver imagen</a>'
                    : '',
                'galery' => $images,
                'stock' => isset($zecatProduct['products'][0]['stock']) ? $zecatProduct['products'][0]['stock'] : 0,
                'proveedor' => 'ZECAT',
                'categorias' => $categorias_mapeadas, // Usar categorías mapeadas
                'infoAttributes' => $infoAttributes,
                'isVariable' => count($variableAttributes) > 0 ? true : false,
                'variableAttributes' => $variableAttributes,
                'variations' => $variations
            ];
        }


        foreach ($productsCDO as $cdoProduct) {

            $images = [];
            $variableAttributes = [];
            $infoAttributes = [];
            $variations = [];

            $precioBaseCDO = isset($cdoProduct['variants'][0]['list_price']) ? floatval($cdoProduct['variants'][0]['list_price']) : 0;
            $precioFinalCDO = cMulticatalogoGNUConfig::calculate_final_price($precioBaseCDO);

            foreach ($cdoProduct['variants'] as $variant) {
                $images[] = $variant['picture']['original'];
                $images[] = $variant['detail_picture']['original'];
                $images[] = $variant['other_pictures'][0]['original'];

                $precioVariante = isset($variant['list_price']) ? floatval($variant['list_price']) : 0;
                $precioFinalVariante = cMulticatalogoGNUConfig::calculate_final_price($precioVariante);

                if (isset($variant['color'])) {
                    $color_name = mb_convert_case(trim($variant['color']['name']), MB_CASE_TITLE, "UTF-8");
                    $variableAttributes['Color'][] = $color_name;
                    
                    $variations[] = [
                        'Combinations' => ['Color' => $color_name],
                        'Stock' => isset($variant['stock_available']) ? $variant['stock_available'] : 0,
                        'Precio' => $precioFinalVariante,
                        'sku' => 'SS' . $variant['id'],
                        'sku_proveedor' => $variant['sku']
                    ];
                } elseif (isset($variant['colors'])){

                    $color_names = [];
                    foreach ($variant['colors'] as $color) {
                        $color_names[] = mb_convert_case(trim($color['name']), MB_CASE_TITLE, "UTF-8");
                    }
                    

                    $colors_string = implode(' / ', $color_names);
                    
                    $variableAttributes['Color'][] = $colors_string;

                    $variations[] = [
                        'Combinations' => ['Color' => $colors_string],
                        'Stock' => isset($variant['stock_available']) ? $variant['stock_available'] : 0,
                        'Precio' => $precioFinalVariante,
                        'sku' => 'SS' . $variant['id'],
                        'sku_proveedor' => $variant['sku']
                    ];
                }


            }
            if (isset($cdoProduct['icons']) && !empty($cdoProduct['icons']) ) {
                 foreach ($cdoProduct['icons'] as $icon) {
                    if ($icon['label'] != $icon['short_name']) {
                        $infoAttributes['Métodos de impresión'][] = mb_convert_case(trim($icon['label']), MB_CASE_TITLE, "UTF-8");
                    }else{
                        $infoAttributes['Información adicional'][] = mb_convert_case(trim($icon['label']), MB_CASE_TITLE, "UTF-8");
                    }
                }
            }

            $categories = [];
            foreach ($cdoProduct['categories'] as $category) {
                $categories[] = mb_convert_case(trim($category['name']), MB_CASE_TITLE, "UTF-8");
            }

            $categorias_mapeadas = cMulticatalogoGNUCategories::apply_category_mapping($categories);

            $mergedProducts[] = [
                'ID' => "SS" . $cdoProduct['code'],
                'sku_proveedor' => $cdoProduct['code'],
                'nombre_del_producto' => $cdoProduct['name'],
                'descripcion' => $cdoProduct['description'],
                'precio' => $precioFinalCDO,
                'image' => isset($cdoProduct['variants'][0]['picture']['original'])
                    ? '<a href="' . $cdoProduct['variants'][0]['picture']['original'] . '" target="_blank">Ver imagen</a>'
                    : '',
                'galery' => $images,
                'stock' => isset($cdoProduct['variants'][0]['stock_available']) ? $cdoProduct['variants'][0]['stock_available'] : 0,
                'proveedor' => 'CDO',
                'categorias' => $categorias_mapeadas, // Usar categorías mapeadas
                'infoAttributes' => $infoAttributes,
                'isVariable' => count($variableAttributes) > 0 ? true : false,
                'variableAttributes' => $variableAttributes,
                'variations' => $variations
            ];
        }


        foreach ($productsPromo as $promoProduct) {
            $images = [$promoProduct['fotoPrincipal']];
            foreach ($promoProduct['images'] as $image) {
                $images[] = $image['src'];
            }

            $precioBasePromo = isset($promoProduct['precio']) ? floatval($promoProduct['precio']) : 0;
            $precioFinalPromo = cMulticatalogoGNUConfig::calculate_final_price($precioBasePromo);

            $variableAttributes = [];
            $variations = [];
            foreach ($promoProduct['atributos'] as $atributo) {
                if (isset($atributo['value']) && $atributo['value'] !== '') {
                    $variableAttributes['Color'][] = mb_convert_case(trim($atributo['value']), MB_CASE_TITLE, "UTF-8");
                }

                $variations[] = [
                    'Combinations' => isset($atributo['value']) ? ['Color' => mb_convert_case(trim($atributo['value']), MB_CASE_TITLE, "UTF-8")] : [],
                    'Stock' => isset($atributo['stock']) ? intval($atributo['stock']) : 0,
                    'Precio' => $precioFinalPromo,
                    'sku' => 'pi0' . $promoProduct['sku'] . '-' . $atributo['value']
                ];
            }


            $infoAttributes = [];
            $descripcion = $promoProduct['descripcion'];
            

            if (preg_match_all('/•\s*([^:]+):(.*?)(?=<br\s*\/>|$)/s', $descripcion, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $clave = trim($match[1]);
                    $valor = trim($match[2]);
                    

                    if (stripos($clave, 'color') !== false) {
                        continue;
                    }
                    

                    if (empty($valor)) {
                        continue;
                    }
                    

                    $valorProcesado = $valor;
                    

                    if (preg_match('/\s*[\/\\\\,]\s*/', $valor)) {
                        $partes = preg_split('/\s*[\/\\\\,]\s*/', $valor);
                        $partes = array_map('trim', $partes);
                        $partes = array_filter($partes);
                        

                        $partes = array_map(function($item) {
                            return rtrim($item, '.');
                        }, $partes);
                        
                        if (count($partes) > 1) {
                            $valorProcesado = array_values($partes);
                        } else {
                            $valorProcesado = reset($partes);
                        }
                    } else {

                        $valorProcesado = rtrim($valor, '.');
                    }
                    
                    $infoAttributes[$clave] = $valorProcesado;
                }
            }

            $categorias = [];
            foreach ($promoProduct['categorias'] as $categoria) {
                $categorias[] = mb_convert_case(trim($categoria['value']), MB_CASE_TITLE, "UTF-8");
            }

            $categorias_mapeadas = cMulticatalogoGNUCategories::apply_category_mapping($categorias);

            $mergedProducts[] = [
                'ID' => "pi0" . $promoProduct['sku'],
                'sku_proveedor' => $promoProduct['sku'],
                'nombre_del_producto' => $promoProduct['titulo'],
                'descripcion' => strip_tags($promoProduct['descripcion']),
                'precio' => $precioFinalPromo,
                'image' => isset($promoProduct['fotoPrincipal'])
                    ? '<a href="' . $promoProduct['fotoPrincipal'] . '" target="_blank">Ver imagen</a>'
                    : '',
                'galery' => $images,
                'stock' => isset($promoProduct['atributos'][0]['stock']) ? intval($promoProduct['atributos'][0]['stock']) : 0,
                'proveedor' => 'promoimport',
                'categorias' => $categorias_mapeadas, // Usar categorías mapeadas
                'infoAttributes' => $infoAttributes,
                'isVariable' => count($variableAttributes) > 0 ? true : false,
                'variableAttributes' => $variableAttributes,
                'variations' => $variations
            ];
        }

        $finalJson = [ 'data' => $mergedProducts ];
        $mergedFilePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';
        
        if (file_put_contents($mergedFilePath, json_encode($finalJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            return true;
        } else {
            return false;
        }
    }

    
    private static 

    
    private static 

    
    private static 

    
    private static function upload_from_json($provider, $offset = 0, $batch_size = 5) {

        $filePathZecat = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';

        if (!file_exists($filePathZecat)) {
            return false;
        }

        $jsonContent = file_get_contents($filePathZecat);
        $productsData = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        if (isset($productsData['data'])) {
            $productsData = $productsData['data'];
        }


        $productsFilter = array_filter($productsData, function($product) use ($provider) {
            return isset($product['proveedor']) && $product['proveedor'] === $provider;
        });

        $productsFilter = array_values($productsFilter);
        $total_productos = count($productsFilter);
        
        if ($total_productos === 0) {
            return false;
        }


        $productBatch = array_slice($productsFilter, $offset, $batch_size);
        $creados = 0;
        $errors = [];

        foreach ($productBatch as $productData) {
            try {
                $result = cMulticatalogoGNUCatalog::createOrUpdateProductFromNormalizedData($productData);
                if ($result) {
                    $creados++;
                    }
            } catch (Exception $e) {
                $errors[] = "Error con producto {$productData['ID']}: " . $e->getMessage();
                }
        }

        $nuevo_offset = $offset + $batch_size;
        $progreso = round(($nuevo_offset / $total_productos) * 100, 2);



        if ($nuevo_offset < $total_productos) {
            $next_batch_time = time() + 0; // 10 segundos de delay


            $cron_hook = 'multicatalogo_batch_upload_' . strtolower($provider);

            if (!wp_next_scheduled($cron_hook, array($provider, $nuevo_offset))) {
                wp_schedule_single_event($next_batch_time, $cron_hook, array($provider, $nuevo_offset));
                }
            
        } else {

            }
    }

    public static function handle_batch_upload($provider, $offset) {
        self::upload_from_json($provider, $offset);
    }

    
    private static function clean_duplicate_products() {
        global $wpdb;
        

        $query = "
            SELECT p1.ID as duplicate_id, p1.post_date, pm1.meta_value as sku
            FROM {$wpdb->posts} p1
            INNER JOIN {$wpdb->postmeta} pm1 ON (p1.ID = pm1.post_id AND pm1.meta_key = '_sku')
            INNER JOIN (
                SELECT meta_value as sku, MAX(p.ID) as max_id
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_sku')
                WHERE p.post_type = 'product'
                AND p.post_status = 'publish'
                AND meta_value != ''
                GROUP BY meta_value
                HAVING COUNT(*) > 1
            ) duplicados ON (pm1.meta_value = duplicados.sku AND p1.ID = duplicados.max_id)
            WHERE p1.post_type = 'product'
            AND p1.post_status = 'publish'
        ";
        
        $duplicates = $wpdb->get_results($query);
        
        if (!empty($duplicates)) {
            foreach ($duplicates as $duplicate) {

                wp_delete_post($duplicate->duplicate_id, true);
                
                }
        } else {
            }
    }

    
    private static function clean_products_without_images() {
        global $wpdb;
        
        $query = "
            SELECT p.ID, p.post_title, pm_sku.meta_value as sku
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_thumb ON (p.ID = pm_thumb.post_id AND pm_thumb.meta_key = '_thumbnail_id')
            INNER JOIN {$wpdb->postmeta} pm_sku ON (p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku')
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm_thumb.meta_value IS NULL
            AND (pm_sku.meta_value LIKE 'zt0%' OR pm_sku.meta_value LIKE 'SS%' OR pm_sku.meta_value LIKE 'pi0%')
        ";
        
        $products_without_images = $wpdb->get_results($query);
        
        if (!empty($products_without_images)) {
            foreach ($products_without_images as $product) {

                wp_delete_post($product->ID, true);
                
                }
        } else {
            }
    }

    private static function update_stock_from_json($provider, $offset = 0, $batch_size = 50) {

        $filePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';

        if (!file_exists($filePath)) {
            return false;
        }

        $jsonContent = file_get_contents($filePath);
        $productsData = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        if (isset($productsData['data'])) {
            $productsData = $productsData['data'];
        }


        $productsFilter = array_filter($productsData, function($product) use ($provider) {
            return isset($product['proveedor']) && $product['proveedor'] === $provider;
        });

        $productsFilter = array_values($productsFilter);
        $total_productos = count($productsFilter);
        
        if ($total_productos === 0) {
            return false;
        }


        $productBatch = array_slice($productsFilter, $offset, $batch_size);
        $actualizados = 0;
        $errors = [];

        foreach ($productBatch as $productData) {
            try {
                $result = cMulticatalogoGNUStock::update_product_stock($productData);
                if ($result) {
                    $actualizados++;
                    }
            } catch (Exception $e) {
                $errors[] = "Error actualizando stock producto {$productData['ID']}: " . $e->getMessage();
                }
        }

        $nuevo_offset = $offset + $batch_size;
        $progreso = round(($nuevo_offset / $total_productos) * 100, 2);



        if ($nuevo_offset < $total_productos) {
            $next_batch_time = time() + 0; // 5 segundos de delay


            $cron_hook = 'multicatalogo_batch_stock_' . strtolower($provider);

            if (!wp_next_scheduled($cron_hook, array($provider, $nuevo_offset))) {
                wp_schedule_single_event($next_batch_time, $cron_hook, array($provider, $nuevo_offset));
                }
            
        } else {

            }

        return [
            'processed' => $nuevo_offset,
            'total' => $total_productos,
            'batch_updated' => $actualizados,
            'percentage' => $progreso,
            'completed' => ($nuevo_offset >= $total_productos)
        ];
    }

    public static function handle_batch_stock($provider, $offset = 0) {
        return self::update_stock_from_json($provider, $offset);
    }

    
    public static 



    private static function update_price_from_json($provider, $offset = 0, $batch_size = 50) {

        $filePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';

        if (!file_exists($filePath)) {
            return false;
        }

        $jsonContent = file_get_contents($filePath);
        $productsData = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        if (isset($productsData['data'])) {
            $productsData = $productsData['data'];
        }


        $productsFilter = array_filter($productsData, function($product) use ($provider) {
            return isset($product['proveedor']) && $product['proveedor'] === $provider;
        });

        $productsFilter = array_values($productsFilter);
        $total_productos = count($productsFilter);
        
        if ($total_productos === 0) {
            return false;
        }


        $productBatch = array_slice($productsFilter, $offset, $batch_size);
        $actualizados = 0;
        $errors = [];

        foreach ($productBatch as $productData) {
            try {
                $result = cMulticatalogoGNUPrice::update_product_price($productData);
                if ($result) {
                    $actualizados++;
                    }
            } catch (Exception $e) {
                $errors[] = "Error actualizando precio producto {$productData['ID']}: " . $e->getMessage();
                }
        }

        $nuevo_offset = $offset + $batch_size;
        $progreso = round(($nuevo_offset / $total_productos) * 100, 2);



        if ($nuevo_offset < $total_productos) {
            $next_batch_time = time() + 5; // 5 segundos de delay

            $cron_hook = 'multicatalogo_batch_price_' . strtolower($provider);

            if (!wp_next_scheduled($cron_hook, array($provider, $nuevo_offset))) {
                wp_schedule_single_event($next_batch_time, $cron_hook, array($provider, $nuevo_offset));
                }
            
        } else {
            }

        return [
            'processed' => $nuevo_offset,
            'total' => $total_productos,
            'batch_updated' => $actualizados,
            'percentage' => $progreso,
            'completed' => ($nuevo_offset >= $total_productos)
        ];
    }

    public static function handle_batch_price($provider, $offset = 0) {
        return self::update_price_from_json($provider, $offset);
    }

    public static 

}


cMulticatalogoGNUCron::init();
