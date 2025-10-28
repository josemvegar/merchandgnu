<?php
/**
 * TecnoGlobalCatalog Actualizar Catalogo
 * @link        https://josecortesia.cl
 * @since       1.0.0
 * 
 * @package     base
 * @subpackage  base/include
 */

class cMulticatalogoGNUCatalog {


    public static function fcreateWooCommerceProductsFromPromoImportJsonGlobo() {
        check_ajax_referer('publicar_promoimport_nonce', 'nonce');
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes.');
        }
    
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $tamano_lote = isset($_POST['tamano_lote']) ? intval($_POST['tamano_lote']) : 2;
    
        // Ruta al archivo JSON de PromoImport
        $filePathPromoImport = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/promoimport_products.json';
    
        if (!file_exists($filePathPromoImport)) {
            wp_send_json_error('Archivo JSON no encontrado.');
        }
    
        $jsonContent = file_get_contents($filePathPromoImport);
        $jsonContentUtf8 = mb_convert_encoding($jsonContent, 'UTF-8', 'auto');
        $productsData = json_decode($jsonContentUtf8, true);
    
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Error al decodificar JSON.');
        }
    
        $total_productos = count($productsData);
        $productBatch = array_slice($productsData, $offset, $tamano_lote);
        $actualizados = 0;
    
        foreach ($productBatch as $productData) {
            $sku = "PI0" . $productData['sku'];
            $existingProductId = wc_get_product_id_by_sku($sku);
    
            if ($existingProductId) {
                continue;
            }
    
            $product = new WC_Product_Simple();
            $product->set_name($productData['titulo']);
            $product->set_description(strip_tags($productData['descripcion']));
            $product->set_sku($sku);
    
            // Precio
            $precio = isset($productData['precio']) ? floatval($productData['precio']) : 0;
            $product->set_regular_price($precio);
    
            // Peso y dimensiones no están disponibles en el JSON, pero puedes agregarlos aquí si tienes esa info.
            $product->set_weight('');
            $product->set_length('');
            $product->set_width('');
            $product->set_height('');
    
            // Atributo Color desde 'atributos'
            $attributes = [];
            if (!empty($productData['atributos'])) {
                $attribute_color = new WC_Product_Attribute();
                $attribute_color->set_name('Color');
    
                $colores = [];
                foreach ($productData['atributos'] as $attr) {
                    $color = trim($attr['value']);
                    if (!in_array($color, $colores)) {
                        $colores[] = $color;
                    }
                }
    
                $attribute_color->set_options($colores);
                $attribute_color->set_position(0);
                $attribute_color->set_visible(true);
                $attribute_color->set_variation(false);
                $attributes[] = $attribute_color;
            }
    
            // Atributo Marcaje no disponible, pero puedes añadirlo vacío si es necesario
            $attribute_marcaje = new WC_Product_Attribute();
            $attribute_marcaje->set_name('Marcaje');
            $attribute_marcaje->set_options(['Sin Marcaje']);
            $attribute_marcaje->set_position(1);
            $attribute_marcaje->set_visible(true);
            $attribute_marcaje->set_variation(false);
            $attributes[] = $attribute_marcaje;
    
            $product->set_attributes($attributes);
    
            // Stock (sumar los stocks si hay más de un atributo)
            $totalStock = 0;
            foreach ($productData['atributos'] as $attr) {
                $totalStock += intval($attr['stock']);
            }
            $product->set_manage_stock(true);
            $product->set_stock_quantity($totalStock);
    
            // Guardar producto
            $product_id = $product->save();
    
            // Categoría desde 'categorias'
            if (!empty($product_id) && !empty($productData['categorias'])) {
                foreach ($productData['categorias'] as $categoria) {
                    $category_name = $categoria['value'];
                    $term = term_exists(sanitize_title($category_name), 'product_cat');
                    if ($term === 0 || $term === null) {
                        $term = wp_insert_term($category_name, 'product_cat', array(
                            'description' => '',
                            'slug' => sanitize_title($category_name)
                        ));
                    }
                    wp_set_object_terms($product_id, array(sanitize_title($category_name)), 'product_cat');
                }
            }
    
            // Imagen principal
            if (!empty($productData['fotoPrincipal'])) {
                $main_image_id = cMulticatalogoGNUCatalog::descargarSubirImagen($productData['fotoPrincipal'], $product_id);
                set_post_thumbnail($product_id, $main_image_id);
            }
    
            // Galería desde 'images'
            if (!empty($productData['images'])) {
                $gallery_image_ids = [];
                foreach ($productData['images'] as $image) {
                    if (!empty($image['src'])) {
                        $gallery_image_ids[] = cMulticatalogoGNUCatalog::descargarSubirImagen($image['src'], $product_id);
                    }
                }
    
                $product = wc_get_product($product_id);
                $product->set_gallery_image_ids($gallery_image_ids);
                $product->save();
            }
    
            $actualizados++;
        }
    
        wp_send_json_success(array(
            'total' => $total_productos,
            'actualizados' => $actualizados,
            'offset' => $offset + $tamano_lote
        ));
    }
    
    /*public static function fcreateWooCommerceProductsFromZecatJsonGlobo() {
        check_ajax_referer('publicar_zecat_nonce', 'nonce');
        
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
            // Verificar si el producto ya existe mediante el SKU
            $sku = "ZT0" . $productData['id'];
            $existingProductId = wc_get_product_id_by_sku($sku);

            if ($existingProductId) {
                // El producto ya existe, omitir la creación
                continue;
            } else {
                // Crear un producto simple
                $product = new WC_Product_Simple();
                
                // Configuración básica del producto
                $product->set_name($productData['name']);
                $product->set_image_id(5); // Asegúrate de que esta imagen exista o maneja dinámicamente
                $product->set_description($productData['description']);
                $product->set_sku($sku);
                
                // Establecer el precio
                $basePrice = isset($productData['price']) ? floatval($productData['price']) : 0;
                $product->set_regular_price($basePrice);
                
                // Configurar propiedades adicionales del producto
                $unitWeight = isset($productData['unit_weight']) ? floatval($productData['unit_weight']) / 1000 : 0; // Convertir a kg si es necesario
                $product->set_weight($unitWeight);
                $product->set_height(isset($productData['height']) ? floatval($productData['height']) : 0);
                $product->set_width(isset($productData['width']) ? floatval($productData['width']) : 0);
                $product->set_length(isset($productData['length']) ? floatval($productData['length']) : 0);
                
                // Crear atributos
                $attributes = [];

                // Atributo Color
                if (!empty($productData['products'])) {
                    $attribute_color = new WC_Product_Attribute();
                    $attribute_color->set_name('Color');
                    
                    $colors = [];
                    foreach ($productData['products'] as $producto_individual) {
                        // Obtener combinaciones de color
                        $color_1 = isset($producto_individual['element_description_1']) ? trim($producto_individual['element_description_1']) : '';
                        $color_2 = isset($producto_individual['element_description_2']) ? trim($producto_individual['element_description_2']) : '';
                        $color_3 = isset($producto_individual['element_description_3']) ? trim($producto_individual['element_description_3']) : '';

                        // Concatenar los colores con "/" y agregarlos al arreglo de combinaciones
                        $combinacion = implode('/', array_filter([$color_1, $color_2, $color_3]));
                        if (!in_array($combinacion, $colors) && !empty($combinacion)) {
                            $colors[] = $combinacion;
                        }
                    }

                    $attribute_color->set_options($colors);
                    $attribute_color->set_position(0);
                    $attribute_color->set_visible(true);
                    $attribute_color->set_variation(false); // No es una variación
                    $attributes[] = $attribute_color;
                }

                // Atributo Marcaje
                if (!empty($productData['subattributes'])) {
                    $attribute_marcaje = new WC_Product_Attribute();
                    $attribute_marcaje->set_name('Marcaje');

                    // Definir la lista de palabras específicas
                    $palabras_especificas = array(
                        'Bordado',
                        'Laser',
                        'Transfer monocolor',
                        'Transfer full color',
                        'Grabado en pantógrafo',
                        'Impresión Digital',
                        'Serigrafía',
                        'Tampografía',
                        'UV',
                        'Grabado Láser UV',
                        'Tampografia', // Asegúrate de incluir todas las variantes necesarias
                    );

                    $marcajes = [];

                    foreach ($productData['subattributes'] as $subatributo) {
                        $nombre = isset($subatributo['name']) ? trim($subatributo['name']) : '';
                        if (in_array($nombre, $palabras_especificas)) {
                            $marcajes[] = $nombre; // Agregar el nombre al array de marcajes
                        }
                    }

                    // Agregar "Sin Marcaje" al arreglo $marcajes
                    $marcajes[] = 'Sin Marcaje';
                    $marcajes = array_unique($marcajes); // Evita duplicados

                    $attribute_marcaje->set_options($marcajes);
                    $attribute_marcaje->set_position(1);
                    $attribute_marcaje->set_visible(true);
                    $attribute_marcaje->set_variation(false); // No es una variación
                    $attributes[] = $attribute_marcaje;
                }

                // Asignar los atributos al producto
                if (!empty($attributes)) {
                    $product->set_attributes($attributes);
                }

                // Gestionar stock
                $product->set_manage_stock(true);
                if (isset($productData['stock_available'])) {
                    $product->set_stock_quantity(intval($productData['stock_available']));
                } else {
                    // Si el stock está en variantes, sumar el stock total
                    if (!empty($productData['products'])) {
                        $totalStock = 0;
                        foreach ($productData['products'] as $producto_individual) {
                            $stock = isset($producto_individual['stock']) ? intval($producto_individual['stock']) : 0;
                            $totalStock += $stock;
                        }
                        $product->set_stock_quantity($totalStock);
                    }
                }

                // Guardar el producto para aplicar los atributos correctamente
                $product_id = $product->save();

                // Asignar categorías
                if ($product_id && !empty($productData['families'])) {
                    foreach ($productData['families'] as $family) {
                        // Verificar si 'title' y 'meta' están definidos
                        $category_name = isset($family['title']) ? $family['title'] : 'Sin Nombre';
                        $category_description = isset($family['meta']) ? $family['meta'] : '';

                        // Crear la categoría si no existe
                        $term = term_exists(sanitize_title($category_name), 'product_cat');
                        if ($term === 0 || $term === null) {
                            $term = wp_insert_term($category_name, 'product_cat', array(
                                'description' => $category_description,
                                'slug'        => sanitize_title($category_name)
                            ));

                            if (is_wp_error($term)) {
                                error_log("Error al crear la categoría: " . $category_name . " - " . $term->get_error_message());
                                continue;
                            }
                        }

                        // Asignar la categoría al producto por ID
                        wp_set_object_terms($product_id, array(sanitize_title($category_name)), 'product_cat');
                    }
                }

                // Asignar imágenes
                if (!empty($productData['images'])) {
                    cMulticatalogoGNUCatalog::asignar_imagen_principal_y_galeria_zecat($product_id, $productData['images']);
                }
                $actualizados++;
            }
    
            
        }
    
        // Devolver la respuesta JSON con el progreso
        wp_send_json_success(array(
            'total' => $total_productos,
            'actualizados' => $actualizados,
            'offset' => $offset + $tamano_lote,
        ));
    }*/

    public static function fcreateWooCommerceProductsFromCDOJsonGlobo() {

        check_ajax_referer('publicar_cdo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes.');
        }
    
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $tamano_lote = isset($_POST['tamano_lote']) ? intval($_POST['tamano_lote']) : 2;
    
        // Ruta al archivo JSON de CDO
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
            // Verificar si el producto ya existe mediante el SKU
            $sku = "SS0" . $productData['id'];
            $existingProductId = wc_get_product_id_by_sku($sku);

            if ($existingProductId) {
                // El producto ya existe, omitir la creación
                continue;
            } else {
                // Crear un producto simple
                $product = new WC_Product_Simple();
                
                // Configuración básica del producto
                $product->set_name(ucwords(strtolower($productData['name'])));
                $product->set_image_id(5); // Asegúrate de que esta imagen exista o maneja dinámicamente
                $product->set_description($productData['description']);
                $product->set_sku($sku);
                
                
                
                // Configurar propiedades adicionales del producto
                $unitWeight = isset($productData['packing']['weight']) ? floatval($productData['packing']['weight']) : 0;
                $product->set_weight($unitWeight);
                $product->set_height(isset($productData['packing']['height']) ? floatval($productData['packing']['height']) : 0);
                $product->set_width(isset($productData['packing']['width']) ? floatval($productData['packing']['width']) : 0);
                $product->set_length(isset($productData['packing']['length']) ? floatval($productData['packing']['length']) : 0);
                
                // Crear atributos
                $attributes = [];

                // Atributo Color
                if (!empty($productData['variants'])) {
                    $attribute_color = new WC_Product_Attribute();
                    $attribute_color->set_name('Color');
                    
                    $colors = [];
                    foreach ($productData['variants'] as $variant) {
                        $colorName = isset($variant['color']['name']) ? trim($variant['color']['name']) : '';
                        if (!empty($colorName) && !in_array($colorName, $colors)) {
                            $colors[] = $colorName;
                        }
                    }

                    $attribute_color->set_options($colors);
                    $attribute_color->set_position(0);
                    $attribute_color->set_visible(true);
                    $attribute_color->set_variation(false); // No es una variación
                    $attributes[] = $attribute_color;
                }

                // Atributo Marcaje
                if (!empty($productData['icons'])) {
                    $attribute_marcaje = new WC_Product_Attribute();
                    $attribute_marcaje->set_name('Marcaje');

                    // Definir la lista de palabras específicas
                    $palabras_especificas = array(
                        'Bordado',
                        'Laser',
                        'Transfer monocolor',
                        'Transfer full color',
                        'Grabado en pantógrafo',
                        'Impresión Digital',
                        'Serigrafía',
                        'Tampografía',
                        'UV',
                        'Grabado Láser UV',
                        'Tampografia', // Asegúrate de incluir todas las variantes necesarias
                    );

                    $marcajes = [];

                    foreach ($productData['icons'] as $subatributo) {
                        $nombre = isset($subatributo['label']) ? trim($subatributo['label']) : '';
                        if (in_array($nombre, $palabras_especificas)) {
                            $marcajes[] = $nombre; // Agregar el nombre al array de marcajes
                        }
                    }

                    // Agregar "Sin Marcaje" al arreglo $marcajes
                    $marcajes[] = 'Sin Marcaje';
                    $marcajes = array_unique($marcajes); // Evita duplicados

                    $attribute_marcaje->set_options($marcajes);
                    $attribute_marcaje->set_position(1);
                    $attribute_marcaje->set_visible(true);
                    $attribute_marcaje->set_variation(false); // No es una variación
                    $attributes[] = $attribute_marcaje;
                }

                // Asignar los atributos al producto
                if (!empty($attributes)) {
                    $product->set_attributes($attributes);
                }

                // Gestionar stock
                $product->set_manage_stock(true);
                if (isset($productData['stock_available'])) {
                    $product->set_stock_quantity(intval($productData['stock_available']));
                } else {
                    // Si el stock está en variantes, sumar el stock total
                    if (!empty($productData['variants'])) {
                        $totalStock = 0;
                        foreach ($productData['variants'] as $variant) {
                            $stock = isset($variant['stock_available']) ? intval($variant['stock_available']) : 0;

                            $totalStock += $stock;
                            // Establecer el precio
                            $basePrice = isset($variant['list_price']) ? floatval($variant['list_price']) : 0;
                            $precio_con_margen = round($basePrice * 1.5, 0, PHP_ROUND_HALF_UP); // Aplicamos 50% de ganancia
                            $product->set_regular_price($precio_con_margen);
                            error_log("Precio: " . $precio_con_margen);
                        }
                        $product->set_stock_quantity($totalStock);
                    }
                }

                // Guardar el producto para aplicar los atributos correctamente
                $product_id = $product->save();

                // Asignar categorías
                if ($product_id && !empty($productData['categories'])) {
                    foreach ($productData['categories'] as $family) {
                        // Verificar si 'name' y 'meta' están definidos
                        $category_name = isset($family['name']) ? $family['name'] : 'Sin Nombre';
                        $category_description = isset($family['description']) ? $family['description'] : '';

                        // Crear la categoría si no existe
                        $term = term_exists(sanitize_title($category_name), 'product_cat');
                        if ($term === 0 || $term === null) {
                            $term = wp_insert_term($category_name, 'product_cat', array(
                                'description' => $category_description,
                                'slug'        => sanitize_title($category_name)
                            ));

                            if (is_wp_error($term)) {
                                error_log("Error al crear la categoría: " . $category_name . " - " . $term->get_error_message());
                                continue;
                            }
                        }

                        // Asignar la categoría al producto por ID
                        wp_set_object_terms($product_id, array(sanitize_title($category_name)), 'product_cat');
                    }
                }

                // Asignar imágenes
                if (!empty($productData)) {
                    cMulticatalogoGNUCatalog::asignarImagenesProducto($product_id, $productData);
                    error_log("Entro ");
                }
                $actualizados++;
            }
                
            

        }

        // Devolver la respuesta JSON con el progreso
        wp_send_json_success(array(
            'total' => $total_productos,
            'actualizados' => $actualizados,
            'offset' => $offset + $tamano_lote
        ));

    }

    public static function asignarImagenesProducto($new_product_id, $product_data) {
        if (empty($product_data['variants'])) {
            return false;
        }
    
        // Imagen principal
        $main_image_url = $product_data['variants'][0]['picture']['original'];
        $main_image_id = cMulticatalogoGNUCatalog::descargarSubirImagen($main_image_url, $new_product_id);
        if ($main_image_id) {
            set_post_thumbnail($new_product_id, $main_image_id);
        }
    
        // Galería
        $gallery_image_ids = [];
        foreach ($product_data['variants'] as $variant) {
            if (!empty($variant['detail_picture']['original'])) {
                $gallery_image_id = cMulticatalogoGNUCatalog::descargarSubirImagen($variant['detail_picture']['original'], $new_product_id);
                if ($gallery_image_id) {
                    $gallery_image_ids[] = $gallery_image_id;
                }
            }
        }
    
        // Asignar galería
        if (!empty($gallery_image_ids)) {
            $product = wc_get_product($new_product_id);
            $product->set_gallery_image_ids($gallery_image_ids);
            $product->save();
        }
    
        return true;
    }
    
    
    public static function descargarSubirImagen($image_url, $post_id) {
        $upload_dir = wp_upload_dir();
        $image_name = basename(cMulticatalogoGNUCatalog::generar_nombre_aleatorio() . ".jpg");
        $image_path = $upload_dir['path'] . '/' . $image_name;
    
        // Descargar la imagen con cURL y user-agent
        $ch = curl_init($image_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $image_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        if ($http_code !== 200 || empty($image_data)) {
            error_log("Error al descargar imagen: $image_url (HTTP $http_code)");
            return false;
        }
    
        file_put_contents($image_path, $image_data);
    
        $wp_filetype = wp_check_filetype($image_name, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => sanitize_file_name($image_name),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
    
        $attachment_id = wp_insert_attachment($attachment, $image_path, $post_id);
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attachment_id, $image_path);
        wp_update_attachment_metadata($attachment_id, $attach_data);
    
        return $attachment_id;
    }
    

    public static function asignar_imagen_principal_y_galeria_zecat($new_product_id, $images) {
        // Verificar si hay imágenes para procesar
        if (empty($images)) {
            return false;
        }
    
        // Obtener la URL de la imagen principal marcada como 'main' en el arreglo de imágenes
        $main_image_url = '';
        foreach ($images as $image) {
            if (!empty($image['main']) && $image['main'] === true) {
                $main_image_url = $image['image_url'];
                break;
            }
        }
    
        // Verificar si se encontró la imagen principal
        if (empty($main_image_url)) {
            return false;
        }
    
        // Descargar la imagen principal y guardarla en el directorio de subidas de WordPress
        $upload_dir = wp_upload_dir();
        $main_image_name = basename(cMulticatalogoGNUCatalog::generar_nombre_aleatorio().".jpg");
        $main_file = $upload_dir['path'] . '/' . $main_image_name;
        file_put_contents($main_file, file_get_contents($main_image_url));
    
        // Obtener el tipo de archivo de la imagen principal
        $wp_filetype_main = wp_check_filetype($main_image_name, null);
    
        // Configurar los datos del archivo adjunto principal
        $main_attachment = array(
            'post_mime_type' => $wp_filetype_main['type'],
            'post_title' => sanitize_file_name($main_image_name),
            'post_content' => '',
            'post_status' => 'inherit'
        );
    
        // Crear el adjunto principal en la base de datos de WordPress
        $main_attachment_id = wp_insert_attachment($main_attachment, $main_file, $new_product_id);
    
        // Generar los metadatos del adjunto principal
        $main_attach_data = wp_generate_attachment_metadata($main_attachment_id, $main_file);
    
        // Asignar los metadatos al adjunto principal
        wp_update_attachment_metadata($main_attachment_id, $main_attach_data);
    
        // Asignar la imagen principal al producto
        set_post_thumbnail($new_product_id, $main_attachment_id);
    
        // Descargar las imágenes de la galería restantes y guardarlas en el directorio de subidas de WordPress
        $gallery_image_ids = array();
        $gallery_image_count = 0;
        foreach ($images as $image) {
            if ($gallery_image_count >= 4) { // Limitar la galería a 4 imágenes
                break;
            }
    
            // Ignorar la imagen principal que ya se procesó
            if (!empty($image['main']) && $image['main'] === true) {
                continue;
            }
    
            $gallery_image_url = $image['image_url'];
            $gallery_image_name = basename(cMulticatalogoGNUCatalog::generar_nombre_aleatorio().".jpg");
            $gallery_file = $upload_dir['path'] . '/' . $gallery_image_name;
            file_put_contents($gallery_file, file_get_contents($gallery_image_url));
    
            // Obtener el tipo de archivo de las imágenes de la galería
            $wp_filetype_gallery = wp_check_filetype($gallery_image_name, null);
    
            // Configurar los datos del archivo adjunto de la galería
            $gallery_attachment = array(
                'post_mime_type' => $wp_filetype_gallery['type'],
                'post_title' => sanitize_file_name($gallery_image_name),
                'post_content' => '',
                'post_status' => 'inherit'
            );
    
            // Crear el adjunto de la galería en la base de datos de WordPress
            $gallery_attachment_id = wp_insert_attachment($gallery_attachment, $gallery_file, $new_product_id);
    
            // Generar los metadatos del adjunto de la galería
            $gallery_attach_data = wp_generate_attachment_metadata($gallery_attachment_id, $gallery_file);
    
            // Asignar los metadatos al adjunto de la galería
            wp_update_attachment_metadata($gallery_attachment_id, $gallery_attach_data);
    
            // Agregar el ID del adjunto de la galería a la lista de IDs de imágenes de la galería
            $gallery_image_ids[] = $gallery_attachment_id;
    
            $gallery_image_count++;
        }
    
        // Asignar los IDs de imágenes de la galería al producto
        $product = wc_get_product($new_product_id);
        $product->set_gallery_image_ids($gallery_image_ids);
        $product->save();
    
        return true;
    }
    
    public static function generar_nombre_aleatorio() {
        $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $caracteres_longitud = strlen($caracteres);
        $nombre_aleatorio = '';
        $longitud = 10;
    
        for ($i = 0; $i < $longitud; $i++) {
            $indice_caracter = rand(0, $caracteres_longitud - 1);
            $nombre_aleatorio .= $caracteres[$indice_caracter];
        }
    
        return $nombre_aleatorio;
    }
        
    

































public static function fcreateWooCommerceProductsFromZecatJsonGlobo() {
    check_ajax_referer('publicar_zecat_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permisos insuficientes.');
    }

    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $tamano_lote = isset($_POST['tamano_lote']) ? intval($_POST['tamano_lote']) : 2;

    // Ruta al archivo JSON normalizado
    $filePathZecat = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';

    if (!file_exists($filePathZecat)) {
        wp_send_json_error('Archivo JSON normalizado no encontrado.');
    }

    $jsonContent = file_get_contents($filePathZecat);
    $productsData = json_decode($jsonContent, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error('Error al decodificar JSON: ' . json_last_error_msg());
    }

    // ✅ CORRECCIÓN 1: Acceder al array 'data' si existe
    if (isset($productsData['data'])) {
        $productsData = $productsData['data'];
    }

    // ✅ CORRECCIÓN 2: No filtrar por proveedor o hacerlo opcional
    $zecatProducts = array_filter($productsData, function($product) {
        // Si no tiene proveedor o es ZECAT, incluirlo
        return !isset($product['proveedor']) || $product['proveedor'] === 'ZECAT';
    });

    $zecatProducts = array_values($zecatProducts);
    $total_productos = count($zecatProducts);
    
    // ✅ CORRECCIÓN 3: Verificar que hay productos
    if ($total_productos === 0) {
        wp_send_json_error('No se encontraron productos ZECAT para procesar.');
    }

    $productBatch = array_slice($zecatProducts, $offset, $tamano_lote);
    
    $creados = 0;
    $errors = [];

    foreach ($productBatch as $productData) {
        try {
            $result = self::createOrUpdateProductFromNormalizedData($productData);
            if ($result) {
                $creados++;
            }
        } catch (Exception $e) {
            $errors[] = "Error con producto {$productData['ID']}: " . $e->getMessage();
            error_log("Error creando producto {$productData['ID']}: " . $e->getMessage());
        }
    }

    $response = [
        'total' => $total_productos,
        'creados' => $creados,
        'offset' => $offset + $tamano_lote,
        'errors' => $errors
    ];

    wp_send_json_success($response);
}

private static function createOrUpdateProductFromNormalizedData($productData) {
    // ✅ CORRECCIÓN 4: Usar el mismo SKU que el original
    $sku = $productData['ID']; // Esto es "zt0383" en tu ejemplo
    
    // Verificar si el producto ya existe
    $existingProductId = wc_get_product_id_by_sku($sku);

    if ($existingProductId) {
        error_log("Producto ya existe, omitiendo: " . $sku);
        return false; // Producto ya existe, omitir
    }

    error_log("Creando producto: " . $sku . " - " . $productData['nombre_del_producto']);

    // Crear producto simple o variable según la estructura
    if (isset($productData['isVariable']) && $productData['isVariable'] === true) {
        return self::createVariableProduct($productData);
    } else {
        return self::createSimpleProduct($productData);
    }
}

private static function createSimpleProduct($productData) {
    $product = new WC_Product_Simple();
    
    // Configuración básica
    $product->set_name($productData['nombre_del_producto']);
    $product->set_description($productData['descripcion'] ?? '');
    $product->set_short_description($productData['descripcion'] ?? '');
    $product->set_sku($productData['ID']);
    $product->set_regular_price($productData['precio'] ?? 0);
    
    // Stock
    $product->set_manage_stock(true);
    $product->set_stock_quantity($productData['stock'] ?? 0);
    
    // Atributos de información
    $attributes = self::createInfoAttributes($productData);
    if (!empty($attributes)) {
        $product->set_attributes($attributes);
    }
    
    $product_id = $product->save();
    
    // Procesar categorías e imágenes
    self::processProductTaxonomies($product_id, $productData);
    self::processProductImages($product_id, $productData);
    
    return $product_id;
}

private static function createVariableProduct($productData) {
    $product = new WC_Product_Variable();
    
    // Configuración básica
    $product->set_name($productData['nombre_del_producto']);
    $product->set_description($productData['descripcion'] ?? '');
    $product->set_short_description($productData['descripcion'] ?? '');
    $product->set_sku($productData['ID']);
    
    // No establecer precio principal para productos variables
    $product->set_regular_price(''); 
    
    // Stock management - el producto variable no gestiona stock directamente
    $product->set_manage_stock(false);
    
    // Crear atributos para variaciones
    $attributes = self::createVariableAttributes($productData);
    if (!empty($attributes)) {
        $product->set_attributes($attributes);
    }
    
    // Atributos de información
    $infoAttributes = self::createInfoAttributes($productData);
    if (!empty($infoAttributes)) {
        // Combinar atributos variables y de información
        $allAttributes = array_merge($attributes, $infoAttributes);
        $product->set_attributes($allAttributes);
    }
    
    $product_id = $product->save();
    
    // Procesar categorías e imágenes
    self::processProductTaxonomies($product_id, $productData);
    self::processProductImages($product_id, $productData);
    
    // Crear variaciones
    if (isset($productData['variations']) && is_array($productData['variations'])) {
        self::createProductVariations($product_id, $productData);
    }
    
    return $product_id;
}

private static function createVariableAttributes($productData) {
    $attributes = [];
    $position = 0;
    
    // Extraer atributos variables de las combinaciones
    $variableAttributes = self::extractVariableAttributesFromCombinations($productData);
    
    foreach ($variableAttributes as $attributeName => $attributeValues) {
        if (!empty($attributeValues)) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name($attributeName);
            $attribute->set_options($attributeValues);
            $attribute->set_position($position);
            $attribute->set_visible(true);
            $attribute->set_variation(true); // ¡IMPORTANTE: Esto lo hace variable!
            
            $attributes['pa_' . sanitize_title($attributeName)] = $attribute;
            $position++;
        }
    }
    
    return $attributes;
}

private static function extractVariableAttributesFromCombinations($productData) {
    $variableAttributes = [];
    
    if (isset($productData['variations']) && is_array($productData['variations'])) {
        foreach ($productData['variations'] as $variation) {
            if (isset($variation['Combinations']) && is_array($variation['Combinations'])) {
                foreach ($variation['Combinations'] as $attributeName => $attributeValue) {
                    if (!empty($attributeValue)) {
                        if (!isset($variableAttributes[$attributeName])) {
                            $variableAttributes[$attributeName] = [];
                        }
                        if (!in_array($attributeValue, $variableAttributes[$attributeName])) {
                            $variableAttributes[$attributeName][] = $attributeValue;
                        }
                    }
                }
            }
        }
    }
    
    return $variableAttributes;
}

private static function createInfoAttributes($productData) {
    $attributes = [];
    $position = 100; // Posición alta para que aparezcan después de los atributos variables
    
    // Procesar infoAttributes para atributos de información (no variables)
    if (isset($productData['infoAttributes']) && is_array($productData['infoAttributes'])) {
        foreach ($productData['infoAttributes'] as $attributeName => $attributeValue) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name($attributeName);
            $attribute->set_options([$attributeValue]);
            $attribute->set_position($position);
            $attribute->set_visible(true);
            $attribute->set_variation(false); // No es variable
            
            $attributes['pa_' . sanitize_title($attributeName)] = $attribute;
            $position++;
        }
    }
    
    return $attributes;
}

private static function createProductVariations($product_id, $productData) {
    $product = wc_get_product($product_id);
    
    foreach ($productData['variations'] as $index => $variationData) {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($product_id);
        
        // Configurar atributos de la variación desde Combinations
        $variationAttributes = [];
        
        if (isset($variationData['Combinations']) && is_array($variationData['Combinations'])) {
            foreach ($variationData['Combinations'] as $attributeName => $attributeValue) {
                $taxonomy = 'pa_' . sanitize_title($attributeName);
                $variationAttributes[$taxonomy] = $attributeValue;
            }
        }
        
        $variation->set_attributes($variationAttributes);
        
        // Precio y stock
        $variation->set_regular_price($variationData['Precio'] ?? 0);
        $variation->set_manage_stock(true);
        $variation->set_stock_quantity($variationData['Stock'] ?? 0);
        
        // SKU único para la variación
        $variationSku = $productData['ID'] . '-var-' . ($index + 1);
        $variation->set_sku($variationSku);
        
        $variation->save();
    }
}

private static function processProductTaxonomies($product_id, $productData) {
    // Procesar categorías
    if (isset($productData['categorias']) && is_array($productData['categorias'])) {
        $category_ids = [];
        
        foreach ($productData['categorias'] as $categoryName) {
            $cleanName = trim($categoryName);
            if (empty($cleanName)) continue;
            
            $term = term_exists($cleanName, 'product_cat');
            
            if (!$term) {
                $term = wp_insert_term($cleanName, 'product_cat', [
                    'slug' => sanitize_title($cleanName)
                ]);
            }
            
            if (!is_wp_error($term) && isset($term['term_id'])) {
                $category_ids[] = $term['term_id'];
            }
        }
        
        if (!empty($category_ids)) {
            wp_set_object_terms($product_id, $category_ids, 'product_cat');
        }
    }
}

private static function processProductImages($product_id, $productData) {
    // Procesar imágenes si existe la función
    if (method_exists('cMulticatalogoGNUCatalog', 'asignar_imagen_principal_y_galeria_zecat')) {
        $images = [];
        
        // Extraer URLs de imágenes de la galería
        if (isset($productData['galery']) && is_array($productData['galery'])) {
            foreach ($productData['galery'] as $imageUrl) {
                $images[] = ['image_url' => $imageUrl];
            }
        }
        
        if (!empty($images)) {
            cMulticatalogoGNUCatalog::asignar_imagen_principal_y_galeria_zecat($product_id, $images);
        }
    }
    
    // Fallback: Si no hay función específica, usar método nativo de WooCommerce
    elseif (isset($productData['galery']) && is_array($productData['galery']) && !empty($productData['galery'])) {
        $gallery_ids = [];
        
        foreach ($productData['galery'] as $imageUrl) {
            $image_id = self::uploadImageFromUrl($imageUrl);
            if ($image_id) {
                $gallery_ids[] = $image_id;
            }
        }
        
        if (!empty($gallery_ids)) {
            // Primera imagen como imagen principal
            $product = wc_get_product($product_id);
            $product->set_image_id($gallery_ids[0]);
            
            // Resto como galería
            if (count($gallery_ids) > 1) {
                $product->set_gallery_image_ids(array_slice($gallery_ids, 1));
            }
            
            $product->save();
        }
    }
}

private static function uploadImageFromUrl($image_url) {
    // Función auxiliar para subir imágenes desde URL
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    
    $image_id = media_sideload_image($image_url, 0, '', 'id');
    
    return is_wp_error($image_id) ? false : $image_id;
}


}