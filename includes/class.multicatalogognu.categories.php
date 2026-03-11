<?php
/**
 * MultiCatalogo Category Mapping Management
 * @link        https://josecortesia.cl
 * @since       2.0.0
 * 
 * @package     base
 * @subpackage  base/include
 */

class cMulticatalogoGNUCategories {

    /**
     * Obtener todas las redirecciones de categorías
     */
    public static function get_all_mappings() {
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_category_mapping';
        
        $results = $wpdb->get_results("
            SELECT m.*, t.name as target_category_name 
            FROM $table m
            LEFT JOIN {$wpdb->terms} t ON m.target_category = t.term_id
            ORDER BY m.source_category
        ");
        
        return $results;
    }

    /**
     * Obtener categorías sin mapear (del JSON principal)
     */
    public static function get_unmapped_categories() {
        $filePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';
        
        if (!file_exists($filePath)) {
            return array();
        }
        
        $jsonContent = file_get_contents($filePath);
        $data = json_decode($jsonContent, true);
        
        if (!$data || !isset($data['data'])) {
            return array();
        }
        
        $all_categories = array();
        foreach ($data['data'] as $product) {
            if (isset($product['categorias']) && is_array($product['categorias'])) {
                foreach ($product['categorias'] as $categoria) {
                    $all_categories[$categoria] = true;
                }
            }
        }
        
        $mapped = self::get_all_mappings();
        $mapped_keys = array();
        foreach ($mapped as $map) {
            $mapped_keys[$map->source_category] = true;
        }
        
        $unmapped = array();
        foreach (array_keys($all_categories) as $cat) {
            if (!isset($mapped_keys[$cat])) {
                $unmapped[] = $cat;
            }
        }
        
        return $unmapped;
    }

    /**
     * Crear o actualizar una redirección de categoría
     */
    public static function save_mapping($source_category, $target_category_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_category_mapping';
        
        // Verificar si ya existe exactamente este mismo mapeo
        $exact_match = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE source_category = %s AND target_category = %d",
            $source_category,
            $target_category_id
        ));
        
        if ($exact_match) {
            // Ya existe exactamente este mapeo, no hacer nada
            return 'exists';
        }
        
        // Verificar si existe la misma source_category con diferente target
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE source_category = %s",
            $source_category
        ));
        
        if ($existing) {
            // Como ahora permitimos múltiples sources iguales, insertamos uno nuevo
            // en lugar de actualizar el existente
            $result = $wpdb->insert(
                $table,
                array(
                    'source_category' => $source_category,
                    'target_category' => $target_category_id,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%d', '%s', '%s')
            );
            
            return $result !== false ? 'inserted' : false;
        } else {
            // No existe ninguna entrada con esta source_category, insertar nueva
            $result = $wpdb->insert(
                $table,
                array(
                    'source_category' => $source_category,
                    'target_category' => $target_category_id,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%d', '%s', '%s')
            );
            
            return $result !== false ? 'inserted' : false;
        }
    }

    /**
     * Eliminar una redirección
     */
    public static function delete_mapping($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_category_mapping';
        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    /**
     * Aplicar mapeo inteligente a un array de categorías
     * 
     * @param array $categorias Array de nombres de categorías originales
     * @return array Array con categorías finales (mapeadas cuando corresponda)
     */
    public static function apply_category_mapping($categorias) {
        if (empty($categorias) || !is_array($categorias)) {
            return array();
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_category_mapping';
        
        // Obtener todos los mapeos
        $mappings = array();
        $results = $wpdb->get_results("
            SELECT m.source_category, t.name as target_name, t.term_id
            FROM $table m
            LEFT JOIN {$wpdb->terms} t ON m.target_category = t.term_id
            ORDER BY m.source_category, m.id
        ", ARRAY_A);
        
        // Organizar mapeos por source_category (ahora cada source puede tener múltiples targets)
        $mappings_by_source = array();
        foreach ($results as $row) {
            if (!isset($mappings_by_source[$row['source_category']])) {
                $mappings_by_source[$row['source_category']] = array();
            }
            $mappings_by_source[$row['source_category']][] = $row['target_name'];
        }
        
        $categorias_finales = array();
        foreach ($categorias as $categoria) {
            if (isset($mappings_by_source[$categoria])) {
                // Existe mapeo: agregar TODAS las categorías destino
                foreach ($mappings_by_source[$categoria] as $target) {
                    if (!empty($target)) { // Asegurarse que el target no esté vacío
                        $categorias_finales[] = $target;
                    }
                }
            } else {
                // No existe mapeo: usar categoría original
                $categorias_finales[] = $categoria;
            }
        }
        
        // Eliminar duplicados y retornar
        return array_values(array_unique($categorias_finales));
    }

    /**
     * Obtener categorías no mapeadas de un producto
     * 
     * @param array $categorias Array de categorías originales
     * @return array Array de categorías que no tienen mapeo
     */
    public static function get_unmapped_from_list($categorias) {
        if (empty($categorias) || !is_array($categorias)) {
            return array();
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_category_mapping';
        
        $mapped_categories = array();
        $results = $wpdb->get_results("SELECT source_category FROM $table", ARRAY_A);
        
        foreach ($results as $row) {
            $mapped_categories[$row['source_category']] = true;
        }
        
        $unmapped = array();
        foreach ($categorias as $categoria) {
            if (!isset($mapped_categories[$categoria])) {
                $unmapped[] = $categoria;
            }
        }
        
        return $unmapped;
    }

    /**
     * AJAX: Guardar mapeo de categoría
     */
    public static function ajax_save_mapping() {
        check_ajax_referer('multicatalogo_category_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes.'));
        }

        $source_category = isset($_POST['source_category']) ? sanitize_text_field($_POST['source_category']) : '';
        $target_category = isset($_POST['target_category']) ? intval($_POST['target_category']) : 0;

        if (empty($source_category) || empty($target_category)) {
            wp_send_json_error(array('message' => 'Datos incompletos.'));
        }

        $result = self::save_mapping($source_category, $target_category);

        if ($result === 'exists') {
            wp_send_json_success(array('message' => 'El mapeo ya existe exactamente igual. No se realizaron cambios.'));
        } elseif ($result === 'inserted') {
            wp_send_json_success(array('message' => 'Redirección guardada exitosamente.'));
        } elseif ($result === 'updated') {
            wp_send_json_success(array('message' => 'Redirección actualizada exitosamente.'));
        } else {
            wp_send_json_error(array('message' => 'Error al guardar la redirección.'));
        }
    }

    /**
     * AJAX: Eliminar mapeo
     */
    public static function ajax_delete_mapping() {
        check_ajax_referer('multicatalogo_category_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes.'));
        }

        $id = isset($_POST['mapping_id']) ? intval($_POST['mapping_id']) : 0;
        
        if (empty($id)) {
            wp_send_json_error(array('message' => 'ID inválido.'));
        }

        $result = self::delete_mapping($id);

        if ($result) {
            wp_send_json_success(array('message' => 'Redirección eliminada exitosamente.'));
        } else {
            wp_send_json_error(array('message' => 'Error al eliminar la redirección.'));
        }
    }

    /**
     * AJAX: Actualizar Categorías
     */
    public static function update_product_categories() {

        check_ajax_referer('multicatalogo_category_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $tamano_lote = isset($_POST['tamano_lote']) ? intval($_POST['tamano_lote']) : 20;

        $filePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/dataMerchan.json';

        if (!file_exists($filePath)) {
            wp_send_json_error('JSON no encontrado');
        }

        $json = file_get_contents($filePath);
        $data = json_decode($json, true);

        if (!$data) {
            wp_send_json_error('JSON inválido');
        }

        /*
        Detectar dónde están los productos
        */
        if (isset($data['productos'])) {
            $productos = $data['productos'];
        } elseif (isset($data['data'])) {
            $productos = $data['data'];
        } else {
            $productos = $data;
        }

        $total = count($productos);
        $batch = array_slice($productos, $offset, $tamano_lote);

        foreach ($batch as $productData) {

            if (empty($productData['ID'])) {
                continue;
            }

            $sku = trim($productData['ID']);

            $product_id = wc_get_product_id_by_sku($sku);

            if (!$product_id) {
                continue;
            }

            $product = wc_get_product($product_id);

            if (!$product || $product->is_type('variation')) {
                continue;
            }

            if (empty($productData['categorias'])) {
                continue;
            }

            $category_ids = [];

            foreach ($productData['categorias'] as $categoria_nombre) {

                $term = get_term_by('name', trim($categoria_nombre), 'product_cat');

                if ($term && !is_wp_error($term)) {
                    $category_ids[] = intval($term->term_id);
                }

            }

            // reemplazar sólo si existe al menos una válida
            if (!empty($category_ids)) {
                wp_set_object_terms($product_id, $category_ids, 'product_cat', false);
            }

        }

        wp_send_json_success([
            'total'  => $total,
            'offset' => min($offset + $tamano_lote, $total)
        ]);
    }
}
