<?php
/**
 * Admin options MultiCatalogo
 * @link        https://josecortesia.cl
 * @since       2.0.0
 * 
 * @package     base
 * @subpackage  base/include
 */


class cMulticatalogoGNUAdmin {

    public static function combinar_json_zecat_cdo() {
        // Rutas a los archivos JSON de Zecat, CDO y PromoImport
        $filePathZecat = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/zecat_products.json';
        $filePathCDO = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/cdo_products.json';
        $filePathPromoImport = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/promoimport_products.json';
    
        // Verificar existencia de archivos
        if (!file_exists($filePathZecat) || !file_exists($filePathCDO) || !file_exists($filePathPromoImport)) {
            wp_send_json_error('Uno o más archivos JSON no fueron encontrados.');
            return;
        }
    
        // Leer y decodificar los contenidos de los archivos JSON
        $productsZecat = json_decode(file_get_contents($filePathZecat), true);
        $productsCDO = json_decode(file_get_contents($filePathCDO), true);
        $productsPromo = json_decode(file_get_contents($filePathPromoImport), true);
    
        // Verificar errores de decodificación
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Error al decodificar los archivos JSON.');
            return;
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
            foreach ($zecatProduct['products'] as $index => $varAttr) {
                if ($varAttr['size'] !== '' && $varAttr['color'] !== '') {
                    $variableAttributes['Tamaño'][] = $varAttr['size'];
                    $variableAttributes['Color'][] = $varAttr['color'];

                    $added = ['Combinations' => ['Tamaño' => $varAttr['size'], 'Color' => $varAttr['color']], 'Stock' => $varAttr['stock'], 'Precio' => $zecatProduct['price'], 'sku' => 'zt0' . $varAttr['sku']];
                }else{
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
    
        // Crear el archivo final
        $finalJson = [ 'data' => $mergedProducts ];
    
        // Guardar el JSON combinado
        $mergedFilePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';
        file_put_contents($mergedFilePath, json_encode($finalJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
        wp_send_json_success('Archivo JSON combinado creado con éxito.');
    }
    
	public static function fAjaxEndpointMerchan(){
        // Verificar que el archivo existe
        $jsonPath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';
        
        if (!file_exists($jsonPath)) {
            // Si no existe, intentar crearlo combinando los JSON
            self::combinar_json_zecat_cdo();
        }
        
        // Verificar nuevamente después de intentar crear
        if (!file_exists($jsonPath)) {
            wp_send_json_error(['message' => 'El archivo dataMerchan.json no existe. Por favor, actualiza primero los JSON de los proveedores.']);
            wp_die();
        }
        
        // Leer el contenido
        $content = file_get_contents($jsonPath);
        
        // Verificar que el contenido es válido
        $json = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => 'Error al leer el archivo JSON: ' . json_last_error_msg()]);
            wp_die();
        }
        
        // Establecer headers correctos para JSON
        header('Content-Type: application/json');
        echo $content;
        wp_die();
	}

    // Registrar el menú del administrador
    public static function fMultiCatalogoGNUAdmin() {
        // Menú principal
        add_menu_page( 
            'Configuración', 
            'MultiCatalogoGnu', 
            'manage_options',
            'multicatalogo_options',
            array( 'cMulticatalogoGNUAdmin', 'fOpcionesMultiCatalogo' ),
            'dashicons-money-alt',
            '65'
        );

        // Submenú: Configuración de Precios
        add_submenu_page(
            'multicatalogo_options',
            'Configuración de Precios',
            'Configuración de Precios',
            'manage_options',
            'multicatalogo_config',
            array( 'cMulticatalogoGNUAdmin', 'fPaginaConfiguracion' )
        );

        // Submenú: Gestión de Categorías
        add_submenu_page(
            'multicatalogo_options',
            'Gestión de Categorías',
            'Gestión de Categorías',
            'manage_options',
            'multicatalogo_categories',
            array( 'cMulticatalogoGNUAdmin', 'fPaginaCategorias' )
        );
    }
    

    public static function fOpcionesMultiCatalogo() {

        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }

        ?>
        <div class="wrap">
            <h1><?php echo get_admin_page_title();  ?></h1>
            <div class="wrap" style="max-width: 100%;margin: auto;padding: 35px;">
            <div class="card opciones-merchan" style="max-width: 100%;">
                <h2 class="title">Opciones Merchan</h2>

                <p class="submit">

                    <input type="submit" name="submit" id="ActualizarListaProductos" class="button button-primary" value="<?php  _e('Actualizar Lista de productos', 'MultiCatalogoGNU')?>">

                    <input type="submit" name="submit" id="ActualizarCatalogoZecat" class="button button-primary" value="<?php  _e('Actualizar lista de productos ZECAT', 'MultiCatalogoGNU')?>">

                    <input type="submit" name="submit" id="ActualizarCatalogoCDO" class="button button-primary" value="<?php  _e('Actualizar lista de productos CDO', 'MultiCatalogoGNU')?>">

                    <input type="submit" name="submit" id="ActualizarCatalogoPromoImport" class="button button-primary" value="<?php  _e('Actualizar lista de productos PromoImport', 'MultiCatalogoGNU')?>">

                    <input type="submit" name="submit" id="PublicarProductosZecat" class="button button-primary" value="<?php  _e('Publicar Productos ZECAT', 'MultiCatalogoGNU')?>">

                    <input type="submit" name="submit" id="PublicarProductosCDO" class="button button-primary" value="<?php  _e('Publicar Productos CDO', 'MultiCatalogoGNU')?>">

                    <input type="submit" name="submit" id="PublicarProductosPromoImport" class="button button-primary" value="<?php  _e('Publicar Productos PromoImport', 'MultiCatalogoGNU')?>">

                    <input type="submit" name="submit" id="ActualizarStockZecat" class="button button-primary" value="<?php  _e('Actualizar Stock en Woocommerce ZECAT', 'MultiCatalogoGNU')?>">

                    <input type="submit" name="submit" id="ActualizarStockCDO" class="button button-primary" value="<?php  _e('Actualizar Stock en Woocommerce CDO', 'MultiCatalogoGNU')?>">

                    <input type="submit" name="submit" id="ActualizarStockPromoImport" class="button button-primary" value="<?php  _e('Actualizar Stock en Woocommerce PromoImport', 'MultiCatalogoGNU')?>">

                    <input type="submit" name="submit" id="ActualizarPrecioZecat" class="button button-primary" value="<?php  _e('Actualizar Precio en Woocommerce Zecat', 'MultiCatalogoGNU')?>">

                    <input type="submit" name="submit" id="ActualizarPrecioCDO" class="button button-primary" value="<?php  _e('Actualizar Precio en Woocommerce CDO', 'MultiCatalogoGNU')?>">
                    
                    <div id="progressContainer" style="width: 100%;">
                        <p><strong>Total de productos:</strong> <span id="totalProducts">0</span></p>
                        <p><strong>Productos publicados:</strong> <span id="publishedProducts">0</span></p>
                        <div id="progressBar" style="width: 100%; background-color: #f3f3f3;">
                            <div id="progress" style="height: 20px; width: 0%; background-color: green;"></div>
                        </div>
                        <!-- <p><strong>Productos nuevos econtrados: </strong> <span id="newproduct">0</span></p> -->
                    </div>

                    <div class="table card" style="max-width: 100%;">
                        <table id="MerchanCatalog" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>id</th>
                                    <th>Sku Proveedor</th>
                                    <th>Nombre del producto</th>
                                    <th>Descripcion</th> 
                                    <th>Precio</th>
                                    <th>Imagen</th>
                                    <th>Stock</th>
                                    <th>Proveedor</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                                <tr>
                                    <th>id</th>
                                    <th>Sku Proveedor</th>
                                    <th>Nombre del producto</th>
                                    <th>Descripcion</th> 
                                    <th>Precio</th>
                                    <th>Imagen</th>
                                    <th>Stock</th>
                                    <th>Proveedor</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </p>
            </div>
        </div>

        <div class="popup-overlay-merchan"></div>
        <div class="loadermerchan centered-merchan" style='display: none;'></div>

        <div class="cd-popup" role="alert">
            <div class="cd-popup-container">
                <div class="view-config"></div>
                <a href="#0" class="cd-popup-close"></a>
            </div>
        </div>

        </div>
        <?php
    }

    /**
     * Página de Configuración de Precios
     */
    public static function fPaginaConfiguracion() {
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }

        $config = new cMulticatalogoGNUConfig();
        $profit_margin = $config->get_profit_margin();
        $usd_to_clp = $config->get_usd_to_clp_rate();

        ?>
        <div class="wrap">
            <h1>Configuración de Precios</h1>
            
            <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
                <h2>Configuración de Conversión y Ganancia</h2>
                <p>Los precios se calcularán con la siguiente fórmula:</p>
                <p><strong>Precio Final (CLP) = (Precio USD × Tasa de Cambio) × (1 + Porcentaje de Ganancia / 100)</strong></p>
                
                <form id="config-precios-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="profit_margin">Porcentaje de Ganancia (%)</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="profit_margin" 
                                       name="profit_margin" 
                                       value="<?php echo esc_attr($profit_margin); ?>" 
                                       step="0.1" 
                                       min="0" 
                                       max="100"
                                       class="regular-text">
                                <p class="description">Ejemplo: 50 = 50% de ganancia sobre el precio base</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="usd_to_clp">Tasa de Cambio USD a CLP</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="usd_to_clp" 
                                       name="usd_to_clp" 
                                       value="<?php echo esc_attr($usd_to_clp); ?>" 
                                       step="1" 
                                       min="1"
                                       class="regular-text">
                                <p class="description">Valor actual del dólar en pesos chilenos</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary" id="guardar-config">
                            Guardar Configuración
                        </button>
                    </p>
                </form>

                <div id="config-mensaje" style="margin-top: 20px;"></div>

                <hr style="margin: 30px 0;">

                <h3>Ejemplo de Cálculo</h3>
                <p>Si un producto cuesta <strong>$10 USD</strong>:</p>
                <p id="ejemplo-calculo">
                    Precio Final = ($10 × <?php echo $usd_to_clp; ?>) × (1 + <?php echo $profit_margin; ?>/100) 
                    = <strong>$<?php echo number_format($config->calculate_final_price(10), 0, ',', '.'); ?> CLP</strong>
                </p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#config-precios-form').on('submit', function(e) {
                e.preventDefault();
                
                var profitMargin = $('#profit_margin').val();
                var usdToClp = $('#usd_to_clp').val();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'multicatalogo_save_config',
                        profit_margin: profitMargin,
                        usd_to_clp: usdToClp,
                        nonce: '<?php echo wp_create_nonce('multicatalogo_config_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#config-mensaje').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            
                            // Actualizar ejemplo
                            var precioFinal = (10 * usdToClp) * (1 + profitMargin/100);
                            $('#ejemplo-calculo').html(
                                'Precio Final = ($10 × ' + usdToClp + ') × (1 + ' + profitMargin + '/100) = <strong>$' + 
                                Math.round(precioFinal).toLocaleString('es-CL') + ' CLP</strong>'
                            );
                        } else {
                            $('#config-mensaje').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#config-mensaje').html('<div class="notice notice-error"><p>Error al guardar la configuración</p></div>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Página de Gestión de Categorías
     */
    public static function fPaginaCategorias() {
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }

        $categoryManager = new cMulticatalogoGNUCategories();
        $mappings = $categoryManager->get_all_mappings();
        
        // Obtener categorías sin mapear de cada proveedor
        $unmapped_zecat = $categoryManager->get_unmapped_categories('zecat');
        $unmapped_cdo = $categoryManager->get_unmapped_categories('cdo');
        $unmapped_promo = $categoryManager->get_unmapped_categories('promoimport');

        // Obtener todas las categorías de WooCommerce
        $woo_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ));

        ?>
        <div class="wrap">
            <h1>Gestión de Categorías</h1>
            
            <div class="card" style="padding: 20px; margin-top: 20px;">
                <h2>Mapeo de Categorías</h2>
                <p>Define cómo se deben reasignar las categorías de los proveedores a las categorías de WooCommerce.</p>
                
                <h3>Agregar Nueva Redirección</h3>
                <form id="add-mapping-form" style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                    <table class="form-table">
                        <tr>
                            <th><label for="provider">Proveedor</label></th>
                            <td>
                                <select id="provider" name="provider" class="regular-text">
                                    <option value="zecat">ZECAT</option>
                                    <option value="cdo">CDO</option>
                                    <option value="promoimport">PromoImport</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="source_category">Categoría Original</label></th>
                            <td>
                                <input type="text" id="source_category" name="source_category" class="regular-text" placeholder="Ej: Textiles">
                                <p class="description">Nombre de la categoría como viene desde la API del proveedor</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="target_category">Categoría Destino (WooCommerce)</label></th>
                            <td>
                                <select id="target_category" name="target_category" class="regular-text">
                                    <option value="">-- Seleccionar Categoría --</option>
                                    <?php foreach ($woo_categories as $cat): ?>
                                        <option value="<?php echo esc_attr($cat->term_id); ?>">
                                            <?php echo esc_html($cat->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <button type="submit" class="button button-primary">Agregar Redirección</button>
                </form>

                <div id="mapping-mensaje" style="margin-top: 20px;"></div>

                <hr style="margin: 30px 0;">

                <h3>Redirecciones Activas</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th>Categoría Original</th>
                            <th>→</th>
                            <th>Categoría Destino</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="mappings-list">
                        <?php if (empty($mappings)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No hay redirecciones configuradas</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($mappings as $mapping): ?>
                                <tr data-mapping-id="<?php echo esc_attr($mapping->id); ?>">
                                    <td><?php echo esc_html(strtoupper($mapping->provider)); ?></td>
                                    <td><?php echo esc_html($mapping->source_category); ?></td>
                                    <td style="text-align: center;">→</td>
                                    <td><?php echo esc_html($mapping->target_category_name); ?></td>
                                    <td>
                                        <button class="button button-small delete-mapping" data-id="<?php echo esc_attr($mapping->id); ?>">
                                            Eliminar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <hr style="margin: 30px 0;">

                <h3>Categorías Sin Redirigir</h3>
                
                <h4>ZECAT</h4>
                <?php if (empty($unmapped_zecat)): ?>
                    <p style="color: green;">✓ Todas las categorías de ZECAT están mapeadas</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($unmapped_zecat as $cat): ?>
                            <li><?php echo esc_html($cat); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h4>CDO</h4>
                <?php if (empty($unmapped_cdo)): ?>
                    <p style="color: green;">✓ Todas las categorías de CDO están mapeadas</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($unmapped_cdo as $cat): ?>
                            <li><?php echo esc_html($cat); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h4>PromoImport</h4>
                <?php if (empty($unmapped_promo)): ?>
                    <p style="color: green;">✓ Todas las categorías de PromoImport están mapeadas</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($unmapped_promo as $cat): ?>
                            <li><?php echo esc_html($cat); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Agregar mapeo
            $('#add-mapping-form').on('submit', function(e) {
                e.preventDefault();
                
                var provider = $('#provider').val();
                var sourceCategory = $('#source_category').val().trim();
                var targetCategory = $('#target_category').val();
                
                if (!sourceCategory || !targetCategory) {
                    $('#mapping-mensaje').html('<div class="notice notice-error"><p>Debes completar todos los campos</p></div>');
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'multicatalogo_save_mapping',
                        provider: provider,
                        source_category: sourceCategory,
                        target_category: targetCategory,
                        nonce: '<?php echo wp_create_nonce('multicatalogo_category_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#mapping-mensaje').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            location.reload(); // Recargar para mostrar la nueva redirección
                        } else {
                            $('#mapping-mensaje').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    }
                });
            });

            // Eliminar mapeo
            $('.delete-mapping').on('click', function() {
                if (!confirm('¿Estás seguro de eliminar esta redirección?')) {
                    return;
                }
                
                var mappingId = $(this).data('id');
                var row = $(this).closest('tr');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'multicatalogo_delete_mapping',
                        mapping_id: mappingId,
                        nonce: '<?php echo wp_create_nonce('multicatalogo_category_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            row.fadeOut(300, function() {
                                $(this).remove();
                                location.reload();
                            });
                        } else {
                            alert('Error al eliminar la redirección');
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
}
