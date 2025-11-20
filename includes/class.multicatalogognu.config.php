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
     * Obtener el tipo de moneda (clp o usd)
     */
    public static function get_currency_type() {
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_config';
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT config_value FROM $table WHERE config_key = %s",
                'currency_type'
            )
        );
        return $result ? $result : 'usd';
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
     * Actualizar el tipo de moneda
     */
    public static function update_currency_type($currency) {
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_config';
        
        $wpdb->replace(
            $table,
            array(
                'config_key' => 'currency_type',
                'config_value' => $currency
            ),
            array('%s', '%s')
        );
        
        return true;
    }

    /**
     * Calcular precio final con margen de ganancia (considerando moneda base)
     */
    public static function calculate_final_price($base_price) {
        $currency_type = self::get_currency_type();
        $profit_margin = self::get_profit_margin();
        
        if ($currency_type === 'clp') {
            // Si el precio base ya está en CLP, solo aplicar ganancia
            $final_price = $base_price * (1 + ($profit_margin / 100));
        } else {
            // Si el precio está en USD, convertir a CLP y aplicar ganancia
            $clp_rate = self::get_usd_to_clp_rate();
            $price_clp = $base_price * $clp_rate;
            $final_price = $price_clp * (1 + ($profit_margin / 100));
        }
        
        // Redondear al entero más cercano
        return round($final_price, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Get auto updates enabled status
     */
    public static function get_auto_updates_enabled() {
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_config';
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT config_value FROM $table WHERE config_key = %s",
                'auto_updates_enabled'
            )
        );
        return $result ? $result : '0';
    }

    /**
     * Update auto updates enabled status
     */
    public static function update_auto_updates_enabled($enabled) {
        global $wpdb;
        $table = $wpdb->prefix . 'multicatalogo_config';
        
        $wpdb->replace(
            $table,
            array(
                'config_key' => 'auto_updates_enabled',
                'config_value' => $enabled
            ),
            array('%s', '%s')
        );
        
        return true;
    }

    /**
     * AJAX: Toggle auto updates
     */
    public static function ajax_toggle_auto_updates() {
        check_ajax_referer('multicatalogo_auto_updates_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes.'));
            return;
        }

        $enabled = isset($_POST['enabled']) ? sanitize_text_field($_POST['enabled']) : '0';
        
        if ($enabled === '1') {
            // Activar los cron jobs
            if (!wp_next_scheduled('multicatalogo_hourly_update_json')) {
                wp_schedule_event(time(), 'hourly', 'multicatalogo_hourly_update_json');
            }
            if (!wp_next_scheduled('multicatalogo_hourly_upload_products')) {
                wp_schedule_event(time(), 'hourly', 'multicatalogo_hourly_upload_products');
            }
            if (!wp_next_scheduled('multicatalogo_hourly_update_prices_stock')) {
                wp_schedule_event(time(), 'hourly', 'multicatalogo_hourly_update_prices_stock');
            }
        } else {
            // Desactivar los cron jobs
            wp_clear_scheduled_hook('multicatalogo_hourly_update_json');
            wp_clear_scheduled_hook('multicatalogo_hourly_upload_products');
            wp_clear_scheduled_hook('multicatalogo_hourly_update_prices_stock');
        }
        
        self::update_auto_updates_enabled($enabled);
        
        $status = $enabled === '1' ? 'Actualizaciones automáticas activadas' : 'Actualizaciones automáticas desactivadas';
        wp_send_json_success(array('message' => $status));
    }

    /**
     * AJAX: Guardar configuración
     */
    public static function ajax_save_config() {
        check_ajax_referer('multicatalogo_config_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes.'));
            return;
        }

        $profit_margin = isset($_POST['profit_margin']) ? floatval($_POST['profit_margin']) : 50;
        $usd_to_clp = isset($_POST['usd_to_clp']) ? floatval($_POST['usd_to_clp']) : 950;
        $currency_type = isset($_POST['currency_type']) ? sanitize_text_field($_POST['currency_type']) : 'usd';

        self::update_profit_margin($profit_margin);
        self::update_usd_to_clp_rate($usd_to_clp);
        self::update_currency_type($currency_type);

        wp_send_json_success(array('message' => 'Configuración guardada exitosamente.'));
    }
}
