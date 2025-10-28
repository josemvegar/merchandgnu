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
        return $wpdb->get_results("SELECT * FROM $table ORDER BY provider, original_category");
    }

    /**
     * Obtener categorías sin mapear por proveedor
     */
    public static function get_unmapped_categories($provider = null) {
        $all_categories = array();
        
        // Obtener categorías de los JSON
        if (!$provider || $provider === 'zecat') {
            $zecat_cats = self::get_categories_from_json('zecat');
            $all_categories = array_merge($all_categories, $zecat_cats);
        }
        
        if (!$provider || $provider === 'cdo') {
            $cdo_cats = self::get_categories_from_json('cdo');
            $all_categories = array_merge($all_categories, $cdo_cats);
        }
        
        if (!$provider || $provider === 'promoimport') {
            $promo_cats = self::get_categories_from_json('promoimport');
            $all_categories = array_merge($all_categories, $promo_cats);
        }
        
        // Filtrar las que ya tienen mapeo
        $mapped = self::get_all_mappings();
        $mapped_keys = array();
        foreach ($mapped as $map) {
            $mapped_keys[] = $map->provider . '|' . $map->original_category;
        }
        
        $unmapped = array();
        foreach ($all_categories as $cat) {
            $key = $cat['provider'] . '|' . $cat['category'];
            if (!in_array($key, $mapped_keys)) {
                $unmapped[] = $cat;
            }
        }
        
        return $unmapped;
    }

    /**
     * Extraer categorías de los JSON
     */
    private static function get_categories_from_json($provider) {
        $categories = array();
        $file_path = '';
        
        switch($provider) {
            case 'zecat':
                $file_path = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/zecat_products.json';
                break;
            case 'cdo':
                $file_path = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/cdo_products.json';
                break;
            case 'promoimport':
                $file_path = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/promoimport_products.json';
                break;
        }
        
        if (!file_exists($file_path)) {
            return $categories;
        }
        
        $json = file_get_contents($file_path);
        $data = json_decode($json, true);
        
        if (!$data) {
            return $categories;
        }
        
        $unique_cats = array();
        
        foreach ($data as $product) {
            if ($provider === 'zecat' && !empty($product['families'])) {
                foreach ($product['families'] as $family) {
                    $cat_name = isset($family['description']) ? ucwords(strtolower($family['description'])) : '';
                    if ($cat_name && !isset($unique_cats[$cat_name])) {
                        $unique_cats[$cat_name] = true;
                        $categories[] = array(
                            'provider' => 'zecat',
                            'category' => $cat_name
                        );
                    }
                }
            } elseif ($provider === 'cdo' && !empty($product['categories'])) {
                foreach ($product['categories'] as $category) {
                    $cat_name = isset($category['name']) ? ucwords(strtolower(trim($category['name']))) : '';
                    if ($cat_name && !isset($unique_cats[$cat_name])) {
                        $unique_cats[$cat_name] = true;
                        $categories[] = array(
                            'provider' => 'cdo',
                            'category' => $cat_name
                        );
                    }
                }
            } elseif ($provider === 'promoimport' && !empty($product['categorias'])) {
                foreach ($product['categorias'] as $categoria) {
                    $cat_name = isset($categoria['value']) ? ucwords(strtolower($categoria['value'])) : '';
                    if ($cat_name && !isset($unique_cats[$cat_name])) {
                        $unique_cats[$cat_name] = true;
                        $categories[] = array(
                            'provider' => 'promoimport',
                            'category' => $cat_name
                        );
                    }
                }
            }
        }
        
        return $categories;
    }

    /**
     * Crear o actualizar una redirección de categoría
     */
    public static function save_mapping($provider, $original_category, $target_category) {
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_category_mapping';
        
        $wpdb->replace(
            $table,
            array(
                'provider' => $provider,
                'original_category' => $original_category,
                'target_category' => $target_category,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        // Actualizar productos existentes con la categoría original
        self::reassign_products_category($provider, $original_category, $target_category);
        
        return true;
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
     * Obtener la categoría objetivo para una categoría original
     */
    public static function get_target_category($provider, $original_category) {
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_category_mapping';
        
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT target_category FROM $table WHERE provider = %s AND original_category = %s",
                $provider,
                $original_category
            )
        );
        
        return $result ? $result : $original_category;
    }

    /**
     * Reasignar categoría de productos existentes
     */
    private static function reassign_products_category($provider, $old_category, $new_category) {
        // Obtener todos los productos con el SKU del proveedor
        $prefix = '';
        switch($provider) {
            case 'zecat':
                $prefix = 'ZT0';
                break;
            case 'cdo':
                $prefix = 'SS0';
                break;
            case 'promoimport':
                $prefix = 'PI0';
                break;
        }
        
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_sku',
                    'value' => $prefix,
                    'compare' => 'LIKE'
                )
            ),
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => sanitize_title($old_category)
                )
            )
        );
        
        $products = get_posts($args);
        
        foreach ($products as $product) {
            // Remover categoría antigua
            wp_remove_object_terms($product->ID, sanitize_title($old_category), 'product_cat');
            
            // Asignar nueva categoría
            $term = term_exists(sanitize_title($new_category), 'product_cat');
            if ($term === 0 || $term === null) {
                $term = wp_insert_term($new_category, 'product_cat', array(
                    'slug' => sanitize_title($new_category)
                ));
            }
            wp_set_object_terms($product->ID, array(sanitize_title($new_category)), 'product_cat', true);
        }
        
        return count($products);
    }

    /**
     * AJAX: Guardar mapeo de categoría
     */
    public static function ajax_save_mapping() {
        check_ajax_referer('category_mapping_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes.');
        }

        $provider = sanitize_text_field($_POST['provider']);
        $original = sanitize_text_field($_POST['original_category']);
        $target = sanitize_text_field($_POST['target_category']);

        self::save_mapping($provider, $original, $target);

        wp_send_json_success('Redirección guardada exitosamente.');
    }

    /**
     * AJAX: Eliminar mapeo
     */
    public static function ajax_delete_mapping() {
        check_ajax_referer('category_mapping_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes.');
        }

        $id = intval($_POST['mapping_id']);
        self::delete_mapping($id);

        wp_send_json_success('Redirección eliminada exitosamente.');
    }
}
