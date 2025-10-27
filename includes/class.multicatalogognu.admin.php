<?php
/**
 * Admin options MultiCatalogo
 * @link        https://josecortesia.cl
 * @since       1.0.0
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
    
        // --- CDO ---
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
    
        // --- PromoImport ---
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
    
        // Crear el archivo final
        $finalJson = [ 'data' => $mergedProducts ];
    
        // Guardar el JSON combinado
        $mergedFilePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';
        file_put_contents($mergedFilePath, json_encode($finalJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
        wp_send_json_success('Archivo JSON combinado creado con éxito.');
    }
    
	public static function fAjaxEndpointMerchan(){

        echo file_get_contents(MUTICATALOGOGNU__PLUGIN_DIR.'admin/dataMulticatalogoGNU/dataMerchan.json');
        wp_die();

	}
    //funcion para registrar el menu del administrador
    public static function fMultiCatalogoGNUAdmin() {
        add_menu_page( 
            'Configuración', 
            'MultiCatalogoGnu', 
            'manage_options',
            'multicatalogo_options',
             array( 'cMulticatalogoGNUAdmin', 'fOpcionesMultiCatalogo' ),
            'dashicons-money-alt',
            '65'
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

                    <input type="submit" name="submit" id="PublicarProductosZecat" class="button button-primary" value="<?php  _e('Publicar Productos ZECAT Sin Variaciones', 'MultiCatalogoGNU')?>">

                    <input type="submit" name="submit" id="PublicarProductosCDO" class="button button-primary" value="<?php  _e('Publicar Productos CDO Sin Variaciones', 'MultiCatalogoGNU')?>">

                    <input type="submit" name="submit" id="PublicarProductosPromoImport" class="button button-primary" value="<?php  _e('Publicar Productos PromoImport Sin Variaciones', 'MultiCatalogoGNU')?>">

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
                        <p><strong>Productos nuevos econtrados: </strong> <span id="newproduct">0</span></p>
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
            </div> <!-- cd-popup-container -->
        </div> <!-- cd-popup -->


        </div>



        <?php

    }


}