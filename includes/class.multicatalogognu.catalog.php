<?php
/** 
     * Eliminar productos de WooCommerce que coincidan con SKUs del catálogo JSON
     * Procesa en lotes para evitar timeouts
     */
    function fDeleteProductsFromCatalogBatch() {
    // Verificar nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'eliminar_productos_catalogo_nonce')) {
        wp_send_json_error(['message' => 'Nonce inválido']);
        return;
    }

    // Leer el archivo JSON principal
    $jsonPath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';
    
    if (!file_exists($jsonPath)) {
        wp_send_json_error(['message' => 'El archivo dataMerchan.json no existe.']);
        return;
    }

    $jsonContent = file_get_contents($jsonPath);
    $jsonData = json_decode($jsonContent, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($jsonData['data'])) {
        wp_send_json_error(['message' => 'Error al leer el archivo JSON.']);
        return;
    }

    $allProducts = $jsonData['data'];
    $totalProducts = count($allProducts);

    // Obtener parámetros de paginación
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $batch_size = isset($_POST['tamano_lote']) ? intval($_POST['tamano_lote']) : 10;

    // Extraer el lote actual
    $currentBatch = array_slice($allProducts, $offset, $batch_size);
    
    $deletedCount = 0;
    $skippedCount = 0;
    $errors = [];

    // Función para eliminar imágenes de un producto
    function eliminar_imagenes_producto($product_id) {
        // Eliminar imagen destacada
        $featured_image_id = get_post_thumbnail_id($product_id);
        if ($featured_image_id) {
            wp_delete_attachment($featured_image_id, true);
        }
        
        // Eliminar imágenes de la galería
        $product = wc_get_product($product_id);
        if ($product) {
            $gallery_ids = $product->get_gallery_image_ids();
            foreach ($gallery_ids as $gallery_image_id) {
                wp_delete_attachment($gallery_image_id, true);
            }
        }
    }

    // Función para eliminar imágenes de variaciones
    function eliminar_imagenes_variacion($variation_id) {
        $featured_image_id = get_post_thumbnail_id($variation_id);
        if ($featured_image_id) {
            wp_delete_attachment($featured_image_id, true);
        }
    }

    foreach ($currentBatch as $product) {
        $sku = isset($product['ID']) ? $product['ID'] : '';
        
        if (empty($sku)) {
            $skippedCount++;
            continue;
        }

        // Buscar el producto en WooCommerce por SKU
        $product_id = wc_get_product_id_by_sku($sku);

        if (!$product_id) {
            // Producto no encontrado, no hacer nada
            $skippedCount++;
            continue;
        }

        // Obtener el producto
        $wc_product = wc_get_product($product_id);

        if (!$wc_product) {
            $skippedCount++;
            continue;
        }

        try {
            // Eliminar imágenes del producto principal
            eliminar_imagenes_producto($product_id);

            // Si es un producto variable, eliminar variaciones e imágenes
            if ($wc_product->is_type('variable')) {
                $variations = $wc_product->get_children();
                foreach ($variations as $variation_id) {
                    // Eliminar imágenes de la variación
                    eliminar_imagenes_variacion($variation_id);
                    // Eliminar la variación permanentemente
                    wp_delete_post($variation_id, true);
                }
            }

            // Eliminar el producto principal permanentemente
            $result = wp_delete_post($product_id, true);

            if ($result) {
                $deletedCount++;
            } else {
                $errors[] = "Error al eliminar producto SKU: {$sku}";
                $skippedCount++;
            }

        } catch (Exception $e) {
            $errors[] = "Excepción al eliminar SKU {$sku}: " . $e->getMessage();
            $skippedCount++;
        }
    }

    // Calcular nuevo offset
    $newOffset = $offset + $batch_size;

    wp_send_json_success([
        'total' => $totalProducts,
        'eliminados' => $deletedCount,
        'omitidos' => $skippedCount,
        'offset' => $newOffset,
        'errors' => $errors,
        'completed' => $newOffset >= $totalProducts
    ]);
}
