<?php
/**
 * MultiCatalogo Configuration Management
 * @link        https://josecortesia.cl
 * @since       2.0.0
 * 
 * @package     base
 * @subpackage  base/include
 */

class cMulticatalogoGNUConfig {

    /**
     * Obtener el porcentaje de ganancia configurado
     */
    public static function get_profit_margin() {
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_config';
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT config_value FROM $table WHERE config_key = %s",
                'profit_margin_percentage'
            )
        );
        return $result ? floatval($result) : 50.0;
    }

    /**
     * Obtener la tasa de conversión USD a CLP
     */
    public static function get_usd_to_clp_rate() {
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_config';
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT config_value FROM $table WHERE config_key = %s",
                'usd_to_clp_rate'
            )
        );
        return $result ? floatval($result) : 950.0;
    }

    /**
     * Actualizar el porcentaje de ganancia
     */
    public static function update_profit_margin($percentage) {
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_config';
        
        $wpdb->replace(
            $table,
            array(
                'config_key' => 'profit_margin_percentage',
                'config_value' => $percentage
            ),
            array('%s', '%s')
        );
        
        return true;
    }

    /**
     * Actualizar la tasa de conversión USD a CLP
     */
    public static function update_usd_to_clp_rate($rate) {
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_config';
        
        $wpdb->replace(
            $table,
            array(
                'config_key' => 'usd_to_clp_rate',
                'config_value' => $rate
            ),
            array('%s', '%s')
        );
        
        return true;
    }

    /**
     * Calcular precio final desde USD a CLP con margen de ganancia
     */
    public static function calculate_final_price($usd_price) {
        $clp_rate = self::get_usd_to_clp_rate();
        $profit_margin = self::get_profit_margin();
        
        // Convertir USD a CLP
        $price_clp = $usd_price * $clp_rate;
        
        // Aplicar margen de ganancia (ej: 50% = 1.5)
        $final_price = $price_clp * (1 + ($profit_margin / 100));
        
        // Redondear al entero más cercano
        return round($final_price, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * AJAX: Guardar configuración
     */
    public static function ajax_save_config() {
        check_ajax_referer('config_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes.');
        }

        $profit_margin = isset($_POST['profit_margin']) ? floatval($_POST['profit_margin']) : 50;
        $usd_to_clp = isset($_POST['usd_to_clp']) ? floatval($_POST['usd_to_clp']) : 950;

        self::update_profit_margin($profit_margin);
        self::update_usd_to_clp_rate($usd_to_clp);

        wp_send_json_success('Configuración guardada exitosamente.');
    }
}
