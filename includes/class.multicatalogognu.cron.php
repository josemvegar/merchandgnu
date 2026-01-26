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

        // Hook para subir productos cada hora
        add_action('multicatalogo_hourly_upload_products', array('cMulticatalogoGNUCron', 'upload_all_products'));

        // Registrar hooks separados para cada proveedor (usando wrappers seguros)
        add_action('multicatalogo_batch_upload_zecat', 'multicatalogo_wrapper_handle_batch_upload', 10, 2);
        add_action('multicatalogo_batch_upload_cdo', 'multicatalogo_wrapper_handle_batch_upload', 10, 2);
        add_action('multicatalogo_batch_upload_promoimport', 'multicatalogo_wrapper_handle_batch_upload', 10, 2);

        // ===== NUEVOS HOOKS PARA ACTUALIZACIÓN DE STOCK =====
        add_action('multicatalogo_batch_stock_zecat', 'multicatalogo_wrapper_handle_batch_stock', 10, 2);
        add_action('multicatalogo_batch_stock_cdo', 'multicatalogo_wrapper_handle_batch_stock', 10, 2);
        add_action('multicatalogo_batch_stock_promoimport', 'multicatalogo_wrapper_handle_batch_stock', 10, 2);

        // En cMulticatalogoGNUCron::init() agregar:
        add_action('multicatalogo_batch_price_zecat', 'multicatalogo_wrapper_handle_batch_price', 10, 2);
        add_action('multicatalogo_batch_price_cdo', 'multicatalogo_wrapper_handle_batch_price', 10, 2);
        add_action('multicatalogo_batch_price_promoimport', 'multicatalogo_wrapper_handle_batch_price', 10, 2);

        // Hook para reverse sync (tienda -> json) que elimina productos no presentes en dataMerchan.json
        add_action('multicatalogo_hourly_reverse_sync', array('cMulticatalogoGNUCron', 'reverse_sync_products'));

    }

    /**
     * Actualizar todos los JSON de los proveedores
     */
    public static function update_all_json() {
        error_log('[MultiCatalogo Cron] Iniciando actualización de JSON - ' . current_time('mysql'));
        
        try {
            // Actualizar proveedores y combinar JSON (silencioso)
            cMultiCatalogoGNUApiRequest::fgetProductsZecat();
            cMultiCatalogoGNUApiRequest::fgetProductsCdo();
            cMultiCatalogoGNUApiRequest::fgetProductsPromoImport();
            self::combine_json_silent();
            error_log('[MultiCatalogo Cron] Actualización de JSON completada exitosamente');
            
        } catch (Exception $e) {
            error_log('[MultiCatalogo Cron] Error al actualizar JSON: ' . $e->getMessage());
        }
    }

    /**
     * Subir nuevos productos
     */
    public static function upload_all_products() {
        error_log('[MultiCatalogo Cron] Iniciando subida de productos - ' . current_time('mysql'));
        
        try {
            // Primero ejecutar limpiezas
            self::clean_duplicate_products();
            self::clean_products_without_images();

            // Ejecutar subida por proveedores (silencioso)
            // Forzar programación inicial para que cada proveedor pueda iniciar desde 0
            self::upload_from_json("ZECAT", 0, 5, true);
            self::upload_from_json("CDO", 0, 5, true);
            self::upload_from_json("promoimport", 0, 5, true);

            error_log('[MultiCatalogo Cron] Subida de productos completada exitosamente');
        } catch (Exception $e) {
            error_log('[MultiCatalogo Cron] Error al subir productos: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar todos los precios y stock
     */
    public static function update_all_prices_stock() {
        error_log('[MultiCatalogo Cron] Iniciando actualización de precios y stock - ' . current_time('mysql'));
        
        try {
            // Actualizar proveedores (silencioso)
            // Forzar programación inicial de lotes por proveedor (stock y precio)
            self::update_stock_from_json('ZECAT', 0, 50, true);
            self::update_price_from_json('ZECAT', 0, 50, true);

            self::update_stock_from_json('CDO', 0, 50, true);
            self::update_price_from_json('CDO', 0, 50, true);

            self::update_stock_from_json('promoimport', 0, 50, true);
            self::update_price_from_json('promoimport', 0, 50, true);

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

        // Verificar errores de decodificación
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[MultiCatalogo Cron] Error al decodificar los archivos JSON');
            return false;
        }

        $mergedProducts = [];

        // --- Zecat ---
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

        // --- CDO ---
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
                    // Crear array con todos los nombres de colores
                    $color_names = [];
                    foreach ($variant['colors'] as $color) {
                        $color_names[] = mb_convert_case(trim($color['name']), MB_CASE_TITLE, "UTF-8");
                    }
                    
                    // Combinar colores en un string separado por "/"
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

        // --- PromoImport ---
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

            // EXTRAER ATRIBUTOS DE LA DESCRIPCIÓN
            $infoAttributes = [];
            $descripcion = $promoProduct['descripcion'];
            
            // Buscar todos los atributos que comienzan con • y terminan con :
            if (preg_match_all('/•\s*([^:]+):(.*?)(?=<br\s*\/>|$)/s', $descripcion, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $clave = trim($match[1]);
                    $valor = trim($match[2]);
                    
                    // Ignorar claves relacionadas con colores
                    if (stripos($clave, 'color') !== false) {
                        continue;
                    }
                    
                    // Si el valor está vacío, saltar
                    if (empty($valor)) {
                        continue;
                    }
                    
                    // Procesar el valor para dividir en array si tiene separadores
                    $valorProcesado = $valor;
                    
                    // Si contiene separadores, dividir en array
                    if (preg_match('/\s*[\/\\\\,]\s*/', $valor)) {
                        $partes = preg_split('/\s*[\/\\\\,]\s*/', $valor);
                        $partes = array_map('trim', $partes);
                        $partes = array_filter($partes);
                        
                        // Eliminar puntos finales de CADA elemento
                        $partes = array_map(function($item) {
                            return rtrim($item, '.');
                        }, $partes);
                        
                        if (count($partes) > 1) {
                            $valorProcesado = array_values($partes);
                        } else {
                            $valorProcesado = reset($partes);
                        }
                    } else {
                        // Si no hay separadores, eliminar punto final del string completo
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
            error_log('[MultiCatalogo Cron] Error al guardar el archivo JSON combinado');
            return false;
        }
    }

    /**
     * Simple lock helper para evitar ejecuciones concurrentes de batches
     */
    private static function acquire_lock($name, $ttl = 1800) {
        $transient = 'multicatalogo_lock_' . $name;
        // Si ya existe, no adquirir
        if (get_transient($transient)) {
            return false;
        }
        // Intentar fijar el transient
        return set_transient($transient, time(), $ttl);
    }

    private static function release_lock($name) {
        $transient = 'multicatalogo_lock_' . $name;
        return delete_transient($transient);
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
            $sku = "SS" . $productData['id'];
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
        
        return $updated;
    }

    /**
     * Subir productos desde JSON (versión silenciosa para cron)
     */
    private static function upload_from_json($provider, $offset = 0, $batch_size = 5, $force_schedule = false) {
        // start log removed to reduce verbosity
        // Ruta al archivo JSON normalizado
        $filePathZecat = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';

        if (!file_exists($filePathZecat)) {
            error_log('[MultiCatalogo Cron] Archivo JSON no encontrado: ' . $filePathZecat);
            return false;
        }

        $jsonContent = file_get_contents($filePathZecat);
        $productsData = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[MultiCatalogo Cron] Error al decodificar JSON: ' . json_last_error_msg());
            return false;
        }

        if (isset($productsData['data'])) {
            $productsData = $productsData['data'];
        }

        // Filtrar solo productos del proveedor
        $productsFilter = array_filter($productsData, function($product) use ($provider) {
            return isset($product['proveedor']) && $product['proveedor'] === $provider;
        });

        $productsFilter = array_values($productsFilter);
        $total_productos = count($productsFilter);

        // Diagnostic: temporary log to verify provider detection for UPLOAD (renamed below)
        // error_log(sprintf('[MultiCatalogo Debug Upload] provider=%s total_products=%d offset=%d', $provider, $total_productos, $offset));

        if ($total_productos === 0) {
            return false;
        }

        // If caller wants to force scheduling the initial batch (e.g. main cron restart),
        // schedule provider offset=0 regardless of current locks/scheduled state.
        if ($offset === 0 && $force_schedule) {
            $cron_hook = 'multicatalogo_batch_upload_' . strtolower($provider);
            if (!wp_next_scheduled($cron_hook, array($provider, 0))) {
                wp_schedule_single_event(time() + 5, $cron_hook, array($provider, 0));
                set_transient('multicatalogo_scheduled_batch_' . strtolower($provider), true, 2 * HOUR_IN_SECONDS);
                // forced initial schedule log removed
            } else {
                // initial batch already scheduled (log removed)
            }
            return true;
        }

        // Intentar adquirir lock para evitar ejecuciones concurrentes
        $lock_name = 'upload_' . strtolower($provider);
        $got_lock = self::acquire_lock($lock_name, 1800);
        if (!$got_lock) {
            error_log('[MultiCatalogo Cron] Upload lock active for provider: ' . $provider . ' - scheduling retry');
            // schedule a retry for this same offset after a short delay
            $cron_hook = 'multicatalogo_batch_upload_' . strtolower($provider);
            if (!wp_next_scheduled($cron_hook, array($provider, $offset))) {
                wp_schedule_single_event(time() + 30, $cron_hook, array($provider, $offset));
                set_transient('multicatalogo_scheduled_batch_' . strtolower($provider), true, 2 * HOUR_IN_SECONDS);
                // scheduled retry (log removed)
            } else {
                // retry already scheduled (log removed)
            }
            return false;
        }
        // lock acquired (log removed)

        // Procesar lote actual
        $productBatch = array_slice($productsFilter, $offset, $batch_size);
        $creados = 0;
        $errors = [];
        try {
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

            // Batch summary log removed to reduce verbosity

        } catch (Exception $e) {
            // liberar lock y rethrow para entornos PHP sin "finally"
            self::release_lock($lock_name);
            error_log('[MultiCatalogo Upload] Lock released for provider (after exception): ' . $provider . ' offset: ' . $offset . ' - Exception: ' . $e->getMessage());
            throw $e;
        }

        // liberar lock siempre al terminar sin excepción
        self::release_lock($lock_name);
        // lock released (log removed)

        $nuevo_offset = $offset + $batch_size;
        $progreso = round(($nuevo_offset / $total_productos) * 100, 2);

        // progreso calculado pero no logueado para evitar spam

        // Si hay más productos, programar siguiente lote (por proveedor)
        $scheduled_key = 'multicatalogo_scheduled_batch_' . strtolower($provider);
        if ($nuevo_offset < $total_productos) {
            $next_batch_time = time() + 5; // ejecutar lo antes posible

            // Hook específico del proveedor
            $cron_hook = 'multicatalogo_batch_upload_' . strtolower($provider);

            // Comprobar si existe un evento ya programado para este hook con estos args
            $already_for_hook = wp_next_scheduled($cron_hook, array($provider, $nuevo_offset));

            if (!$already_for_hook) {
                wp_schedule_single_event($next_batch_time, $cron_hook, array($provider, $nuevo_offset));
                // Marcar que hay un batch programado para este proveedor
                set_transient($scheduled_key, true, 2 * HOUR_IN_SECONDS);
                // scheduled next batch (log removed)
            } else {
                // next batch already scheduled (log removed)
                // Ensure transient is set for this provider in case it wasn't
                set_transient($scheduled_key, true, 2 * HOUR_IN_SECONDS);
            }

        } else {
            // Proceso completado: limpiar marca de programado para este proveedor
            if (get_transient($scheduled_key)) {
                delete_transient($scheduled_key);
                // completed provider cleared scheduled transient (log removed)
            }
        }
    }

    public static function handle_batch_upload($provider, $offset) {
        self::upload_from_json($provider, $offset);
    }

    /**
     * Eliminar productos duplicados (mantener el más antiguo por ID)
     */
    private static function clean_duplicate_products() {
        global $wpdb;
        
        // Buscar productos duplicados (no se loguea cada paso)
        
        // Consulta corregida - usar MAX(ID) en lugar de MAX(post_date)
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
        
        $deleted = 0;
        if (!empty($duplicates)) {
            foreach ($duplicates as $duplicate) {
                wp_delete_post($duplicate->duplicate_id, true);
                $deleted++;
            }
        }
        // Solo un log resumen si se eliminaron duplicados
        if ($deleted > 0) {
            // Resumen de limpieza eliminado para evitar llenar los logs en producción.
            // error_log('[MultiCatalogo Clean] Productos duplicados eliminados: ' . $deleted);
        }
    }

    /**
     * Eliminar productos padres sin imagen principal con SKUs específicos
     */
    private static function clean_products_without_images() {
        global $wpdb;
        
        // Buscar productos sin imagen (no se loguea cada paso)
        
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
        
        $deleted = 0;
        if (!empty($products_without_images)) {
            foreach ($products_without_images as $product) {
                wp_delete_post($product->ID, true);
                $deleted++;
            }
        }
        if ($deleted > 0) {
            // Resumen de limpieza eliminado para reducir ruido en error.log
            // error_log('[MultiCatalogo Clean] Productos sin imagen eliminados: ' . $deleted);
        }
    }

    private static function update_stock_from_json($provider, $offset = 0, $batch_size = 50, $force_schedule = false) {
        // Ruta al archivo JSON unificado
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

        // Filtrar solo productos del proveedor
        $productsFilter = array_filter($productsData, function($product) use ($provider) {
            return isset($product['proveedor']) && $product['proveedor'] === $provider;
        });

        $productsFilter = array_values($productsFilter);
        $total_productos = count($productsFilter);

        // Diagnostic: temporary log to verify provider detection for STOCK
        // error_log(sprintf('[MultiCatalogo Debug Stock] provider=%s total_products=%d offset=%d', $provider, $total_productos, $offset));

        // If caller wants to force scheduling the initial batch (e.g. main cron restart),
        // schedule provider offset=0 regardless of current locks/scheduled state.
        if ($offset === 0 && $force_schedule) {
            $cron_hook = 'multicatalogo_batch_stock_' . strtolower($provider);
            if (!wp_next_scheduled($cron_hook, array($provider, 0))) {
                wp_schedule_single_event(time() + 5, $cron_hook, array($provider, 0));
                set_transient('multicatalogo_scheduled_batch_' . strtolower($provider), true, 2 * HOUR_IN_SECONDS);
                return true;
            }
            return true;
        }

        if ($total_productos === 0) {
            // No se encontraron productos para actualizar — evitar log por cada ejecución vacía
            // error_log('[Stock Cron] No se encontraron productos ' . $provider . ' para actualizar stock.');
            return false;
        }

        // Intentar adquirir lock para evitar ejecuciones concurrentes
        $lock_name = 'stock_' . strtolower($provider);
        $got_lock = self::acquire_lock($lock_name, 1800);
        if (!$got_lock) {
            error_log('[MultiCatalogo Cron] Stock lock active for provider: ' . $provider . ' - scheduling retry');
            // schedule a retry for this same offset after a short delay
            $cron_hook = 'multicatalogo_batch_stock_' . strtolower($provider);
            if (!wp_next_scheduled($cron_hook, array($provider, $offset))) {
                wp_schedule_single_event(time() + 30, $cron_hook, array($provider, $offset));
                set_transient('multicatalogo_scheduled_batch_' . strtolower($provider), true, 2 * HOUR_IN_SECONDS);
            }
            return false;
        }

        // Procesar lote actual
        $productBatch = array_slice($productsFilter, $offset, $batch_size);
        $actualizados = 0;
        $errors = [];

        try {
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

        } catch (Exception $e) {
            // liberar lock y rethrow para entornos PHP sin "finally"
            self::release_lock($lock_name);
            error_log('[MultiCatalogo Stock] Lock released for provider (after exception): ' . $provider . ' offset: ' . $offset . ' - Exception: ' . $e->getMessage());
            throw $e;
        }

        // liberar lock siempre al terminar sin excepción
        self::release_lock($lock_name);

        $nuevo_offset = $offset + $batch_size;
        $progreso = round(($nuevo_offset / $total_productos) * 100, 2);

        // progreso calculado pero no logueado para evitar spam

        // Si hay más productos, programar siguiente lote
        if ($nuevo_offset < $total_productos) {
            $next_batch_time = time() + 0; // 5 segundos de delay

            // Al programar el siguiente lote, usa el hook específico del proveedor
            $cron_hook = 'multicatalogo_batch_stock_' . strtolower($provider);

            if (!wp_next_scheduled($cron_hook, array($provider, $nuevo_offset))) {
                wp_schedule_single_event($next_batch_time, $cron_hook, array($provider, $nuevo_offset));
            }
            
        } else {
            // Proceso completado (sin log para evitar crecimiento de logs)
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

    /**
     * Ejecutar actualización para todos los proveedores via Cron
     */
    public static function update_all_providers_stock_cron() {
        $providers = ['promoimport', 'CDO', 'ZECAT'];
        $results = [];
        
        foreach ($providers as $provider) {
            $results[$provider] = self::update_stock_from_json($provider);
        }
        return $results;
    }


    // Función para procesamiento por lotes de precios
    private static function update_price_from_json($provider, $offset = 0, $batch_size = 50, $force_schedule = false) {
        // Misma estructura que update_stock_from_json pero llamando a update_product_price
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

        // Filtrar solo productos del proveedor
        $productsFilter = array_filter($productsData, function($product) use ($provider) {
            return isset($product['proveedor']) && $product['proveedor'] === $provider;
        });

        $productsFilter = array_values($productsFilter);
        $total_productos = count($productsFilter);
        // Diagnostic: temporary log to verify provider detection
        // error_log(sprintf('[MultiCatalogo Debug Price] provider=%s total_products=%d offset=%d', $provider, $total_productos, $offset));

        // If caller wants to force scheduling the initial batch (e.g. main cron restart),
        // schedule provider offset=0 regardless of current locks/scheduled state.
        if ($offset === 0 && $force_schedule) {
            $cron_hook = 'multicatalogo_batch_price_' . strtolower($provider);
            if (!wp_next_scheduled($cron_hook, array($provider, 0))) {
                wp_schedule_single_event(time() + 5, $cron_hook, array($provider, 0));
                set_transient('multicatalogo_scheduled_batch_' . strtolower($provider), true, 2 * HOUR_IN_SECONDS);
                return true;
            }
            return true;
        }

        if ($total_productos === 0) {
            return false;
        }

        // Intentar adquirir lock para evitar ejecuciones concurrentes
        $lock_name = 'price_' . strtolower($provider);
        $got_lock = self::acquire_lock($lock_name, 1800);
        if (!$got_lock) {
            error_log('[MultiCatalogo Cron] Price lock active for provider: ' . $provider . ' - scheduling retry');
            // schedule a retry for this same offset after a short delay
            $cron_hook = 'multicatalogo_batch_price_' . strtolower($provider);
            if (!wp_next_scheduled($cron_hook, array($provider, $offset))) {
                wp_schedule_single_event(time() + 30, $cron_hook, array($provider, $offset));
                set_transient('multicatalogo_scheduled_batch_' . strtolower($provider), true, 2 * HOUR_IN_SECONDS);
            }
            return false;
        }

        // Procesar lote actual
        $productBatch = array_slice($productsFilter, $offset, $batch_size);
        $actualizados = 0;
        $errors = [];

        try {
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

        } catch (Exception $e) {
            // liberar lock y rethrow para entornos PHP sin "finally"
            self::release_lock($lock_name);
            error_log('[MultiCatalogo Price] Lock released for provider (after exception): ' . $provider . ' offset: ' . $offset . ' - Exception: ' . $e->getMessage());
            throw $e;
        }

        // liberar lock siempre al terminar sin excepción
        self::release_lock($lock_name);

        $nuevo_offset = $offset + $batch_size;
        $progreso = round(($nuevo_offset / $total_productos) * 100, 2);

        // progreso calculado pero no logueado para evitar spam

        // Si hay más productos, programar siguiente lote
        if ($nuevo_offset < $total_productos) {
            $next_batch_time = time() + 5; // 5 segundos de delay

            $cron_hook = 'multicatalogo_batch_price_' . strtolower($provider);

            if (!wp_next_scheduled($cron_hook, array($provider, $nuevo_offset))) {
                wp_schedule_single_event($next_batch_time, $cron_hook, array($provider, $nuevo_offset));
            }
            
        } else {
            // completado (sin log para minimizar ruido)
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

    public static function update_all_providers_price_cron() {
        $providers = ['promoimport', 'CDO', 'ZECAT'];
        $results = [];
        
        foreach ($providers as $provider) {
            // Force scheduling initial batch for each provider so it runs via the per-provider hook
            $results[$provider] = self::update_price_from_json($provider, 0, 50, true);
        }
        return $results;
    }

    /**
     * Reverse Sync (Tienda → JSON): eliminar productos en tienda cuyos SKUs empiezan
     * con zt0, SS o pi0 que no estén presentes en el JSON combinado (padre o variación).
     */
    public static function reverse_sync_products($last_id = 0, $batch_size = 100) {
        // 1. Asegurar Batch Size (Blindaje)
        if (!is_numeric($batch_size) || $batch_size < 1) {
            $batch_size = 100;
        }

        $filePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';
        if (!file_exists($filePath)) {
            error_log("[MultiCatalogo] Error: Archivo JSON no encontrado.");
            return false;
        }

        $jsonContent = file_get_contents($filePath);
        $productsData = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[MultiCatalogo] Error: JSON inválido.");
            return false;
        }

        if (isset($productsData['data'])) {
            $productsData = $productsData['data'];
        }

        // 2. Mapa de SKUs del JSON
        $presentSkus = array();
        foreach ($productsData as $p) {
            if (isset($p['ID'])) $presentSkus[strtoupper(trim($p['ID']))] = true;
            if (isset($p['variations']) && is_array($p['variations'])) {
                foreach ($p['variations'] as $v) {
                    if (isset($v['sku'])) $presentSkus[strtoupper(trim($v['sku']))] = true;
                }
            }
        }

        global $wpdb;

        // Bloqueo de concurrencia
        $lock_name = 'reverse_sync';
        if (!self::acquire_lock($lock_name, 3600)) {
            error_log("[MultiCatalogo] Salto: Lock activo.");
            return false;
        }

        // Función para eliminar imágenes de un post/producto
        function delete_post_images($post_id, $post_type = 'product') {
            // Eliminar imagen destacada
            $featured_image_id = get_post_thumbnail_id($post_id);
            if ($featured_image_id) {
                wp_delete_attachment($featured_image_id, true);
                error_log("[MultiCatalogo] Imagen destacada eliminada para ID: $post_id");
            }
            
            // Para productos, eliminar también imágenes de la galería
            if ($post_type === 'product') {
                $product = wc_get_product($post_id);
                if ($product) {
                    $gallery_ids = $product->get_gallery_image_ids();
                    foreach ($gallery_ids as $gallery_image_id) {
                        wp_delete_attachment($gallery_image_id, true);
                    }
                    if (!empty($gallery_ids)) {
                        error_log("[MultiCatalogo] " . count($gallery_ids) . " imágenes de galería eliminadas para producto ID: $post_id");
                    }
                }
            }
        }

        $deleted_count = 0;
        $processed_count = 0;
        $current_max_id = $last_id;

        try {
            /**
             * CONSULTA MEJORADA: 
             * Usamos ID > %d para no perdernos al borrar.
             */
            $query = $wpdb->prepare(
                "SELECT p.ID, p.post_type, pm.meta_value as sku 
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.ID > %d 
                AND p.post_type IN ('product', 'product_variation') 
                AND p.post_status = 'publish'
                AND pm.meta_key = '_sku'
                AND (pm.meta_value REGEXP '^(ZT0|SS|PI0)')
                ORDER BY p.ID ASC LIMIT %d", 
                $last_id,
                $batch_size
            );

            $rows = $wpdb->get_results($query);
            $total_in_batch = count($rows);

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $processed_count++;
                    $post_id = intval($row->ID);
                    $sku = strtoupper(trim($row->sku));
                    $post_type = $row->post_type;
                    $current_max_id = $post_id; // Actualizamos el puntero al último ID real

                    if (!isset($presentSkus[$sku])) {
                        // Eliminar imágenes antes de eliminar el post
                        delete_post_images($post_id, $post_type);
                        
                        if ($post_type === 'product') {
                            // Para productos variables, eliminar imágenes de variaciones primero
                            $variations = get_children(['post_parent' => $post_id, 'post_type' => 'product_variation', 'fields' => 'ids']);
                            foreach ($variations as $v_id) {
                                // Eliminar imágenes de la variación
                                delete_post_images($v_id, 'product_variation');
                                // Eliminar la variación permanentemente
                                wp_delete_post($v_id, true);
                                error_log("[MultiCatalogo] Variación eliminada: ID $v_id");
                            }
                            // Eliminar producto principal (a la papelera)
                            wp_delete_post($post_id, false);
                        } else {
                            // Para variaciones, eliminar permanentemente
                            wp_delete_post($post_id, true);
                        }
                        
                        $deleted_count++;
                        error_log("[MultiCatalogo] ELIMINADO: ID $post_id (SKU: $sku) tipo: $post_type");
                    }
                }
            }

            error_log(sprintf(
                "[MultiCatalogo] Lote ID > %d finalizado. Analizados: %d, Borrados: %d, Último ID: %d",
                $last_id, $processed_count, $deleted_count, $current_max_id
            ));

        } finally {
            self::release_lock($lock_name);
        }

        // RE-PROGRAMACIÓN
        // Si el número de filas recibidas es igual al batch_size, es probable que queden más
        if ($total_in_batch >= $batch_size) {
            $cron_hook = 'multicatalogo_hourly_reverse_sync';
            // Pasamos el $current_max_id como primer argumento para la siguiente vuelta
            wp_schedule_single_event(time() + 5, $cron_hook, array($current_max_id, $batch_size)); 
            error_log("[MultiCatalogo] Hay más. Programando siguiente lote desde ID: $current_max_id");
        } else {
            error_log("[MultiCatalogo] Sincronización COMPLETADA. No quedan más productos con esos prefijos.");
        }

        return $deleted_count;
    }

}

// Inicializar los hooks de cron
cMulticatalogoGNUCron::init();

// Wrappers seguros para callbacks de cron: evitan fatal si la clase/método no están disponibles
function multicatalogo_wrapper_handle_batch_upload($provider = null, $offset = 0) {
    if (class_exists('cMulticatalogoGNUCron') && method_exists('cMulticatalogoGNUCron', 'handle_batch_upload')) {
        try {
            return cMulticatalogoGNUCron::handle_batch_upload($provider, $offset);
        } catch (Exception $e) {
            error_log('[MultiCatalogo Cron] Exception in handle_batch_upload wrapper: ' . $e->getMessage());
            return false;
        }
    }
    error_log('[MultiCatalogo Cron] handle_batch_upload not available');
    return false;
}

function multicatalogo_wrapper_handle_batch_stock($provider = null, $offset = 0) {
    if (class_exists('cMulticatalogoGNUCron') && method_exists('cMulticatalogoGNUCron', 'handle_batch_stock')) {
        try {
            return cMulticatalogoGNUCron::handle_batch_stock($provider, $offset);
        } catch (Exception $e) {
            error_log('[MultiCatalogo Cron] Exception in handle_batch_stock wrapper: ' . $e->getMessage());
            return false;
        }
    }
    error_log('[MultiCatalogo Cron] handle_batch_stock not available');
    return false;
}

function multicatalogo_wrapper_handle_batch_price($provider = null, $offset = 0) {
    if (class_exists('cMulticatalogoGNUCron') && method_exists('cMulticatalogoGNUCron', 'handle_batch_price')) {
        try {
            return cMulticatalogoGNUCron::handle_batch_price($provider, $offset);
        } catch (Exception $e) {
            error_log('[MultiCatalogo Cron] Exception in handle_batch_price wrapper: ' . $e->getMessage());
            return false;
        }
    }
    error_log('[MultiCatalogo Cron] handle_batch_price not available');
    return false;
}
