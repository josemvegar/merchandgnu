<?php


class cMulticatalogoGNUCategories {

    
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

    
    public static function save_mapping($source_category, $target_category_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_category_mapping';
        

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE source_category = %s",
            $source_category
        ));
        
        if ($existing) {

            $result = $wpdb->update(
                $table,
                array(
                    'target_category' => $target_category_id,
                    'updated_at' => current_time('mysql')
                ),
                array('source_category' => $source_category),
                array('%d', '%s'),
                array('%s')
            );
        } else {

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
        }
        
        return $result !== false;
    }

    
    public static function delete_mapping($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_category_mapping';
        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    
    public static function apply_category_mapping($categorias) {
        if (empty($categorias) || !is_array($categorias)) {
            return array();
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_category_mapping';
        
        $mappings = array();
        $results = $wpdb->get_results("
            SELECT m.source_category, t.name as target_name, t.term_id
            FROM $table m
            LEFT JOIN {$wpdb->terms} t ON m.target_category = t.term_id
        ", ARRAY_A);
        
        foreach ($results as $row) {
            $mappings[$row['source_category']] = $row['target_name'];
        }
        
        $categorias_finales = array();
        foreach ($categorias as $categoria) {
            if (isset($mappings[$categoria])) {

                $categorias_finales[] = $mappings[$categoria];
            } else {

                $categorias_finales[] = $categoria;
            }
        }
        

        return array_values(array_unique($categorias_finales));
    }

    
    public static 

    
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

        if ($result) {
            wp_send_json_success(array('message' => 'Redirección guardada exitosamente.'));
        } else {
            wp_send_json_error(array('message' => 'Error al guardar la redirección.'));
        }
    }

    
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
}
