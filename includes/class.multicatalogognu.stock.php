<?php
/**
 * WooIntcomex Admin Clases plugin
 * @link        https://josecortesia.cl
 * @since       1.0.0
 * 
 * @package     base
 * @subpackage  base/include
 */


class cMulticatalogoGNUStock {


    public static function fUpdateStockPromoImportGlobo() {
        check_ajax_referer('stock_promoimport_nonce', 'nonce');
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes.');
        }
    
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $tamano_lote = isset($_POST['tamano_lote']) ? intval($_POST['tamano_lote']) : 2;
    
        // Ruta al archivo JSON de PromoImport
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
                error_log("Falta SKU en producto PromoImport: " . print_r($productData, true));
                continue;
            }
    
            $sku = "PI0" . $productData['sku'];
            $existingProductId = wc_get_product_id_by_sku($sku);
    
            if (!$existingProductId) {
                error_log("Producto no encontrado con SKU: " . $sku);
                continue;
            }
    
            $product = wc_get_product($existingProductId);
            if (!$product) {
                error_log("No se pudo obtener el objeto del producto con SKU: " . $sku);
                continue;
            }
    
            // Calcular el total de stock sumando los valores de los atributos
            $total_stock = 0;
            if (!empty($productData['atributos']) && is_array($productData['atributos'])) {
                foreach ($productData['atributos'] as $atributo) {
                    if (isset($atributo['stock'])) {
                        $total_stock += intval($atributo['stock']);
                    }
                }
            }
    
            // Actualizar la gestión y cantidad de stock
            $product->set_manage_stock(true);
            $product->set_stock_quantity($total_stock);
    
            // Establecer estado de inventario
            $product->set_stock_status($total_stock > 0 ? 'instock' : 'outofstock');
    
            // Guardar cambios
            $save_result = $product->save();
    
            if ($save_result) {
                error_log("Stock actualizado para SKU: $sku con cantidad: $total_stock");
                $actualizados++;
            } else {
                error_log("Error al guardar el stock para el producto con SKU: $sku");
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

            // Verificar que 'id' existe
            if (!isset($productData['id'])) {
                error_log("Campo 'id' no encontrado en el producto: " . print_r($productData, true));
                continue;
            }
    
            $product_id = $productData['id'];
            $parent_sku = "ZT0" . $product_id;
    
            // Obtener el producto por SKU
            $parent_product_id = wc_get_product_id_by_sku($parent_sku);
            if (!$parent_product_id) {
                error_log("Producto no encontrado con SKU: " . $parent_sku);
                continue;
            }
    
            $parent_product = wc_get_product($parent_product_id);
            if (!$parent_product) {
                error_log("No se pudo obtener el objeto del producto con SKU: " . $parent_sku);
                continue;
            }
    
            // Verificar si 'products' existe y es un array
            if (!isset($productData['products']) || !is_array($productData['products'])) {
                error_log("Campo 'products' no encontrado o no es un array para el producto ID: " . $product_id);
                continue;
            }
    
            // Verificar el stock de las variantes
            $hay_existencia = false;
            foreach ($productData['products'] as $variant) {
                if (isset($variant['stock']) && intval($variant['stock']) > 0) {
                    $hay_existencia = true;
                    break; // Si al menos una variante tiene stock, no es necesario seguir
                }
            }
    
            if ($hay_existencia) {
                // Hay stock disponible
                $parent_product->set_stock_status('instock');
                // Opcional: Establecer 'stock_quantity' a 1000
                // Nota: Solo se debe establecer si 'manage_stock' está habilitado
                $parent_product->set_manage_stock(true);
                $parent_product->set_stock_quantity(1000);
            } else {
                // No hay stock disponible
                $parent_product->set_stock_status('outofstock');
                // Opcional: Desactivar la gestión de stock
                $parent_product->set_manage_stock(false);
                // Opcional: Establecer 'stock_quantity' a 0
                // $parent_product->set_stock_quantity(0);
            }
    
            // Guardar los cambios
            $save_result = $parent_product->save();
    
            if ($save_result) {
                // Registrar la actualización exitosa
                error_log("Stock actualizado para SKU: " . $parent_sku . " a " . ($hay_existencia ? "instock (1000)" : "outofstock"));
                $actualizados++;
            } else {
                error_log("Error al guardar los cambios para el producto con SKU: " . $parent_sku);
            }
    
            
        }
    
        // Devolver la respuesta JSON con el progreso
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

            // Generar el SKU utilizado en la función de creación
            $sku = "SS" . $productData['id'];
                        
            // Obtener el ID del producto existente por SKU
            $existingProductId = wc_get_product_id_by_sku($sku);

            if ($existingProductId) {
                // Obtener el objeto del producto
                $product = wc_get_product($existingProductId);
                if (!$product) {
                    error_log("No se pudo obtener el objeto del producto con SKU: " . $sku);
                    continue;
                }

                $new_stock = 0; // Inicializar el stock

                // Caso 1: stock_available en el nivel del producto
                if (isset($productData['stock_available'])) {
                    $new_stock = intval($productData['stock_available']);
                }
                // Caso 2: stock_available dentro de variantes
                elseif (!empty($productData['variants']) && is_array($productData['variants'])) {
                    foreach ($productData['variants'] as $variant) {
                        if (isset($variant['stock_available'])) {
                            $new_stock += intval($variant['stock_available']);
                        }
                    }
                } else {
                    // Si no se encuentra stock_available ni en el producto ni en variantes
                    error_log("Campo 'stock_available' no encontrado para el producto con SKU: " . $sku);
                    continue;
                }

                // Actualizar el stock del producto
                $product->set_stock_quantity($new_stock);
                $product->set_manage_stock(true); // Asegurar que la gestión de stock está habilitada

                // Actualizar el estado del stock basado en la cantidad
                if ($new_stock > 0) {
                    $product->set_stock_status('instock');
                } else {
                    $product->set_stock_status('outofstock');
                }

                // Guardar los cambios
                $save_result = $product->save();

                if ($save_result) {
                    // Registrar la actualización exitosa
                    error_log("Stock actualizado para SKU: " . $sku . " a " . $new_stock);
                    $actualizados++;
                } else {
                    error_log("Error al guardar los cambios para el producto con SKU: " . $sku);
                }
            } else {
                // El producto con el SKU no existe
                error_log("Producto no encontrado con SKU: " . $sku);
            }            
    
            
        }
    
        // Devolver la respuesta JSON con el progreso
        wp_send_json_success(array(
            'total' => $total_productos,
            'actualizados' => $actualizados,
            'offset' => $offset + $tamano_lote,
        ));

    }

}