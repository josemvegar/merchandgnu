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

        // Registrar hooks separados para cada proveedor
        add_action('multicatalogo_batch_upload_zecat', array('cMulticatalogoGNUCron', 'handle_batch_upload'), 10, 2);
        add_action('multicatalogo_batch_upload_cdo', array('cMulticatalogoGNUCron', 'handle_batch_upload'), 10, 2);
        add_action('multicatalogo_batch_upload_promoimport', array('cMulticatalogoGNUCron', 'handle_batch_upload'), 10, 2);

        // ===== NUEVOS HOOKS PARA ACTUALIZACIÓN DE STOCK =====
        add_action('multicatalogo_batch_stock_zecat', array('cMulticatalogoGNUCron', 'handle_batch_stock'), 10, 2);
        add_action('multicatalogo_batch_stock_cdo', array('cMulticatalogoGNUCron', 'handle_batch_stock'), 10, 2);
        add_action('multicatalogo_batch_stock_promoimport', array('cMulticatalogoGNUCron', 'handle_batch_stock'), 10, 2);

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
     * Subir nuevos productos
     */
    public static function upload_all_products() {
        error_log('[MultiCatalogo Cron] Iniciando subida de productos - ' . current_time('mysql'));
        
        try {

            // Primero ejecutar limpiezas
            self::clean_duplicate_products();
            self::clean_products_without_images();

            error_log('[MultiCatalogo Cron] Subida de productos ZECAT inciada...');
            self::upload_from_json("ZECAT");

            error_log('[MultiCatalogo Cron] Subida de productos ZECAT inciada...');
            self::upload_from_json("CDO");

            error_log('[MultiCatalogo Cron] Subida de productos Promo Import inciada...');
            self::upload_from_json("promoimport");

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
            // Actualizar Zecat
            error_log('[MultiCatalogo Cron] Actualizando stock y precios Zecat...');
            self::update_stock_from_json('ZECAT');
            
            // Actualizar CDO
            error_log('[MultiCatalogo Cron] Actualizando stock y precios CDO...');
            self::update_stock_from_json('CDO');
            
            // Actualizar PromoImport
            error_log('[MultiCatalogo Cron] Actualizando stock y precios PromoImport...');
            self::update_stock_from_json('promoimport');
            
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

            foreach ($zecatProduct['families'] as $family) {
                $families[] = mb_convert_case(trim($family['description']), MB_CASE_TITLE, "UTF-8");
            }
            foreach ($zecatProduct['images'] as $image) {
                $images[] = $image['image_url'];
            }
            foreach ($zecatProduct['products'] as $varAttr) {
                if ($varAttr['size'] !== '' && $varAttr['color'] !== '') {
                    $variableAttributes['Tamaño'][] = $varAttr['size'];
                    $variableAttributes['Color'][] = $varAttr['color'];

                    $added = ['Combinations' => ['Tamaño' => $varAttr['size'], 'Color' => $varAttr['color']], 'Stock' => $varAttr['stock'], 'Precio' => $zecatProduct['price'], 'sku' => 'zt0' . $varAttr['sku']];
                } else {
                    $variableAttributes['Variante'][] = $varAttr['element_description_1'] . ' / ' . $varAttr['element_description_2'] . ' / ' . $varAttr['element_description_3'];

                    $added = [ 'Combinations' => ['Variante' => $varAttr['element_description_1'] . ' / ' . $varAttr['element_description_2'] . ' / ' . $varAttr['element_description_3']], 
                            'Stock' => $varAttr['stock'],
                            'Precio' => $zecatProduct['price'],
                            'sku' => 'zt0' . $varAttr['sku']
                    ];
                }

                $variations[] = $added;
            }
            foreach ($zecatProduct['subattributes'] as $infoAttr) {
                $infoAttributes[$infoAttr['attribute_name']] = trim($infoAttr['name']);
            }

            $mergedProducts[] = [
                'ID' => "zt0" . $zecatProduct['id'],
                'sku_proveedor' => $zecatProduct['external_id'],
                'nombre_del_producto' => $zecatProduct['name'],
                'descripcion' => $zecatProduct['description'],
                'precio' => $zecatProduct['price'],
                'image' => isset($zecatProduct['images'][0]['image_url'])
                    ? '<a href="' . $zecatProduct['images'][0]['image_url'] . '" target="_blank">Ver imagen</a>'
                    : '',
                'galery' => $images,
                'stock' => isset($zecatProduct['products'][0]['stock']) ? $zecatProduct['products'][0]['stock'] : 0,
                'proveedor' => 'ZECAT',
                'categorias' => $families,
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
            foreach ($cdoProduct['variants'] as $variant) {
                $images[] = $variant['picture']['original'];
                $images[] = $variant['detail_picture']['original'];
                $images[] = $variant['other_pictures'][0]['original'];

                if (isset($variant['color'])) {
                    $color_name = mb_convert_case(trim($variant['color']['name']), MB_CASE_TITLE, "UTF-8");
                    $variableAttributes['Color'][] = $color_name;
                    
                    $variations[] = [
                        'Combinations' => ['Color' => $color_name],
                        'Stock' => isset($variant['stock_available']) ? $variant['stock_available'] : 0,
                        'Precio' => isset($variant['list_price']) ? floatval($variant['list_price']) : 0,
                        'sku' => 'ss0' . $variant['id']
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
                        'Precio' => isset($variant['list_price']) ? floatval($variant['list_price']) : 0,
                        'sku' => 'ss0' . $variant['id']
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

            $mergedProducts[] = [
                'ID' => "ss0" . $cdoProduct['id'],
                'sku_proveedor' => $cdoProduct['code'],
                'nombre_del_producto' => $cdoProduct['name'],
                'descripcion' => $cdoProduct['description'],
                'precio' => isset($cdoProduct['variants'][0]['list_price']) ? floatval($cdoProduct['variants'][0]['list_price']) : 0,
                'image' => isset($cdoProduct['variants'][0]['picture']['original'])
                    ? '<a href="' . $cdoProduct['variants'][0]['picture']['original'] . '" target="_blank">Ver imagen</a>'
                    : '',
                'galery' => $images,
                'stock' => isset($cdoProduct['variants'][0]['stock_available']) ? $cdoProduct['variants'][0]['stock_available'] : 0,
                'proveedor' => 'CDO',
                'categorias' => $categories,
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

            $variableAttributes = [];
            $variations = [];
            foreach ($promoProduct['atributos'] as $atributo) {
                if (isset($atributo['value']) && $atributo['value'] !== '') {
                    $variableAttributes['Color'][] = mb_convert_case(trim($atributo['value']), MB_CASE_TITLE, "UTF-8");
                }

                $variations[] = [
                    'Combinations' => isset($atributo['value']) ? ['Color' => mb_convert_case(trim($atributo['value']), MB_CASE_TITLE, "UTF-8")] : [],
                    'Stock' => isset($atributo['stock']) ? intval($atributo['stock']) : 0,
                    'Precio' => isset($promoProduct['precio']) ? floatval($promoProduct['precio']) : 0,
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

            $mergedProducts[] = [
                'ID' => "pi0" . $promoProduct['sku'],
                'sku_proveedor' => $promoProduct['sku'],
                'nombre_del_producto' => $promoProduct['titulo'],
                'descripcion' => strip_tags($promoProduct['descripcion']),
                'precio' => floatval($promoProduct['precio']),
                'image' => isset($promoProduct['fotoPrincipal'])
                    ? '<a href="' . $promoProduct['fotoPrincipal'] . '" target="_blank">Ver imagen</a>'
                    : '',
                'galery' => $images,
                'stock' => isset($promoProduct['atributos'][0]['stock']) ? intval($promoProduct['atributos'][0]['stock']) : 0,
                'proveedor' => 'promoimport',
                'categorias' => $categorias,
                'infoAttributes' => $infoAttributes,
                'isVariable' => count($variableAttributes) > 0 ? true : false,
                'variableAttributes' => $variableAttributes,
                'variations' => $variations
            ];
        }

        $finalJson = [ 'data' => $mergedProducts ];
        $mergedFilePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';
        
        if (file_put_contents($mergedFilePath, json_encode($finalJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            error_log('[MultiCatalogo Cron] Archivo JSON combinado creado con éxito. Productos: ' . count($mergedProducts));
            return true;
        } else {
            error_log('[MultiCatalogo Cron] Error al guardar el archivo JSON combinado');
            return false;
        }
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

    /**
     * Subir productos desde JSON (versión silenciosa para cron)
     */
    private static function upload_from_json($provider, $offset = 0, $batch_size = 5) {
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
        
        if ($total_productos === 0) {
            error_log('[MultiCatalogo Cron] No se encontraron productos ' . $provider . ' para procesar.');
            return false;
        }

        // Procesar lote actual
        $productBatch = array_slice($productsFilter, $offset, $batch_size);
        $creados = 0;
        $errors = [];

        foreach ($productBatch as $productData) {
            try {
                $result = cMulticatalogoGNUCatalog::createOrUpdateProductFromNormalizedData($productData);
                if ($result) {
                    $creados++;
                    error_log("✅ PRODUCTO CREADO: {$productData['ID']} - {$productData['nombre_del_producto']}");
                }
            } catch (Exception $e) {
                $errors[] = "Error con producto {$productData['ID']}: " . $e->getMessage();
                error_log("❌ ERROR: {$productData['ID']} - " . $e->getMessage());
            }
        }

        $nuevo_offset = $offset + $batch_size;
        $progreso = round(($nuevo_offset / $total_productos) * 100, 2);

        // Log del progreso
        error_log("[MultiCatalogo Cron] Lote {$provider}: {$offset}-{$nuevo_offset} de {$total_productos} ({$progreso}%) - Creados: {$creados}");

        // Si hay más productos, programar siguiente lote
        if ($nuevo_offset < $total_productos) {
            $next_batch_time = time() + 0; // 10 segundos de delay

            // Al programar el siguiente lote, usa el hook específico del proveedor
            $cron_hook = 'multicatalogo_batch_upload_' . strtolower($provider);

            if (!wp_next_scheduled($cron_hook, array($provider, $nuevo_offset))) {
                wp_schedule_single_event($next_batch_time, $cron_hook, array($provider, $nuevo_offset));
                error_log("[MultiCatalogo Cron] Siguiente lote programado para: " . date('H:i:s', $next_batch_time) . " - Proveedor: " . $provider);
            }
            
        } else {
            // Proceso completado
            error_log("[MultiCatalogo Cron] ✅ IMPORTACIÓN {$provider} COMPLETADA: {$total_productos} productos procesados");
        }
    }

    public static function handle_batch_upload($provider, $offset) {
        error_log("[MultiCatalogo Cron] Ejecutando lote para {$provider} desde offset: {$offset}");
        self::upload_from_json($provider, $offset);
    }

    /**
     * Eliminar productos duplicados (mantener el más antiguo por ID)
     */
    private static function clean_duplicate_products() {
        global $wpdb;
        
        error_log('[MultiCatalogo Clean] Buscando productos duplicados...');
        
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
        
        if (!empty($duplicates)) {
            error_log('[MultiCatalogo Clean] Encontrados ' . count($duplicates) . ' productos duplicados a eliminar');
            
            foreach ($duplicates as $duplicate) {
                error_log("[MultiCatalogo Clean] Eliminando producto duplicado (más reciente) - ID: {$duplicate->duplicate_id}, SKU: {$duplicate->sku}");
                
                // Eliminar producto y sus meta datos
                wp_delete_post($duplicate->duplicate_id, true);
                
                error_log("[MultiCatalogo Clean] Producto eliminado: {$duplicate->duplicate_id}");
            }
        } else {
            error_log('[MultiCatalogo Clean] No se encontraron productos duplicados');
        }
    }

    /**
     * Eliminar productos padres sin imagen principal con SKUs específicos
     */
    private static function clean_products_without_images() {
        global $wpdb;
        
        error_log('[MultiCatalogo Clean] Buscando productos sin imagen...');
        
        $query = "
            SELECT p.ID, p.post_title, pm_sku.meta_value as sku
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_thumb ON (p.ID = pm_thumb.post_id AND pm_thumb.meta_key = '_thumbnail_id')
            INNER JOIN {$wpdb->postmeta} pm_sku ON (p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku')
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm_thumb.meta_value IS NULL
            AND (pm_sku.meta_value LIKE 'zt0%' OR pm_sku.meta_value LIKE 'ss0%' OR pm_sku.meta_value LIKE 'pi0%')
        ";
        
        $products_without_images = $wpdb->get_results($query);
        
        if (!empty($products_without_images)) {
            error_log('[MultiCatalogo Clean] Encontrados ' . count($products_without_images) . ' productos sin imagen');
            
            foreach ($products_without_images as $product) {
                error_log("[MultiCatalogo Clean] Eliminando producto sin imagen - ID: {$product->ID}, SKU: {$product->sku}, Título: {$product->post_title}");
                
                // Eliminar producto y sus meta datos
                wp_delete_post($product->ID, true);
                
                error_log("[MultiCatalogo Clean] Producto sin imagen eliminado: {$product->ID}");
            }
        } else {
            error_log('[MultiCatalogo Clean] No se encontraron productos sin imagen');
        }
    }

    private static function update_stock_from_json($provider, $offset = 0, $batch_size = 50) {
        // Ruta al archivo JSON unificado
        $filePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';

        if (!file_exists($filePath)) {
            error_log('[Stock Cron] Archivo JSON no encontrado: ' . $filePath);
            return false;
        }

        $jsonContent = file_get_contents($filePath);
        $productsData = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[Stock Cron] Error al decodificar JSON: ' . json_last_error_msg());
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
        
        if ($total_productos === 0) {
            error_log('[Stock Cron] No se encontraron productos ' . $provider . ' para actualizar stock.');
            return false;
        }

        // Procesar lote actual
        $productBatch = array_slice($productsFilter, $offset, $batch_size);
        $actualizados = 0;
        $errors = [];

        foreach ($productBatch as $productData) {
            try {
                $result = cMulticatalogoGNUStock::update_product_stock($productData);
                if ($result) {
                    $actualizados++;
                    error_log("✅ STOCK ACTUALIZADO: {$productData['ID']} - {$productData['nombre_del_producto']}");
                }
            } catch (Exception $e) {
                $errors[] = "Error actualizando stock producto {$productData['ID']}: " . $e->getMessage();
                error_log("❌ ERROR STOCK: {$productData['ID']} - " . $e->getMessage());
            }
        }

        $nuevo_offset = $offset + $batch_size;
        $progreso = round(($nuevo_offset / $total_productos) * 100, 2);

        // Log del progreso
        error_log("[Stock Cron] Lote {$provider}: {$offset}-{$nuevo_offset} de {$total_productos} ({$progreso}%) - Actualizados: {$actualizados}");

        // Si hay más productos, programar siguiente lote
        if ($nuevo_offset < $total_productos) {
            $next_batch_time = time() + 0; // 5 segundos de delay

            // Al programar el siguiente lote, usa el hook específico del proveedor
            $cron_hook = 'multicatalogo_batch_stock_' . strtolower($provider);

            if (!wp_next_scheduled($cron_hook, array($provider, $nuevo_offset))) {
                wp_schedule_single_event($next_batch_time, $cron_hook, array($provider, $nuevo_offset));
                error_log("[Stock Cron] Siguiente lote programado para: " . date('H:i:s', $next_batch_time) . " - Proveedor: " . $provider);
            }
            
        } else {
            // Proceso completado
            error_log("[Stock Cron] ✅ ACTUALIZACIÓN STOCK {$provider} COMPLETADA: {$total_productos} productos actualizados");
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
        error_log("[Stock Cron] Ejecutando lote stock para {$provider} desde offset: {$offset}");
        return self::update_stock_from_json($provider, $offset);
    }

    /**
     * Ejecutar actualización para todos los proveedores via Cron
     */
    public static function update_all_providers_stock_cron() {
        $providers = ['PROMOIMPORT', 'ZECAT', 'CDO'];
        $results = [];
        
        foreach ($providers as $provider) {
            $results[$provider] = self::update_stock_cron($provider);
        }
        
        error_log("[Stock Cron] Resumen actualización: " . print_r($results, true));
        return $results;
    }

}

// Inicializar los hooks de cron
cMulticatalogoGNUCron::init();
