<?php
/**
 * MultiCatalogo API Conexión Clases plugin
 * @link        https://josecortesia.cl
 * @since       1.0.0
 * 
 * @package     base
 * @subpackage  base/include
 */


class cMultiCatalogoGNUApiRequest {

    public static function fgetProductsZecat(): array {
        $baseUrl = 'https://api.zecat.cl/v1/generic_product?order[price]=asc&only_products=true&limit=100';
        $allProducts = [];
        $totalPages = 1; // Asumir al menos una página para empezar.
        $bearerToken = 'Y29udGFjdG9AZ2xvYm9tYXJrZXRpbmcuY2w6ZXlKMGVYQWlPaUpLVjFRaUxDSmhiR2NpT2lKSVV6STFOaUo5LkltTjJhblEyWnpGb1kyTnJlbXd4ZVhNaS40Z3JsenM4NkdaRUdyRXlmcVIxR3VfalpjSWQtN0VsODhGRnlsUS1PWk5n'; // Reemplazar con tu token real
    
        for ($page = 1; $page <= $totalPages; $page++) {
            $url = $baseUrl . '&page=' . $page;
    
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
            // Agregar el encabezado de autorización con el token Bearer
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $bearerToken
            ]);
    
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
            curl_close($ch);
    
            if ($httpCode == 200) {
                $data = json_decode($response, true);
                if (!empty($data['generic_products'])) {
                    $allProducts = array_merge($allProducts, $data['generic_products']);
    
                    // Actualizar el total de páginas después de la primera solicitud
                    if ($page === 1) {
                        $totalPages = $data['total_pages'] ?? $totalPages;
                    }
                } else {
                    break; // Salir si no hay productos
                }
            } else {
                break; // Salir en caso de error de HTTP
            }
        }
    
        // Convertir el array de todos los productos a JSON
        $jsonData = json_encode($allProducts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
        // Asegúrate de que el directorio exista o de manejar adecuadamente la posibilidad de que no exista.
        $filePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/zecat_products.json';
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true); // Crear el directorio si no existe
        }
    
        // Guardar los datos en un archivo JSON
        file_put_contents($filePath, $jsonData);
    
        return $allProducts;
    }
    

    public static function fgetProductsCdo(): array {
        $authToken = '8eOxwH1qW7m83nSY6WmAwg'; // Asegúrate de usar tu token de autenticación real
        $baseUrl = 'https://api.chile.cdopromocionales.com/v2/products?auth_token=' . $authToken . '&page_size=100';
        $allProducts = [];
        $currentPage = 1;
        $totalPages = 1; // Asumir al menos una página para empezar
    
        do {
            $url = $baseUrl . '&page_number=' . $currentPage;
    
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
            curl_close($ch);
    
            if ($httpCode == 200) {
                $data = json_decode($response, true);
                if (!empty($data['products'])) {
                    $allProducts = array_merge($allProducts, $data['products']);
                    $currentPage++;
                    $totalPages = $data['meta']['pagination']['total_pages'];
                } else {
                    break; // Salir si no hay productos
                }
            } else {
                break; // Salir en caso de error de HTTP
            }
        } while ($currentPage <= $totalPages);
    

            // Convertir el array de todos los productos a JSON
            $jsonData = json_encode($allProducts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
            // Asegúrate de que el directorio exista o de manejar adecuadamente la posibilidad de que no exista.
            $filePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/cdo_products.json';
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true); // Crear el directorio si no existe
            }
    
            // Guardar los datos en un archivo JSON
            file_put_contents($filePath, $jsonData);
    
        return $allProducts;
    }

    public static function fgetProductsPromoImport(): array {
        $url = 'https://api.promoimport.cl/productos?type=json&token=frAz5mwAzDPT6di1VkLvyzwnMKBp4rci9ak';
        $allProducts = [];
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (is_array($data)) {
                $allProducts = $data;
            }
        }
    
        // Convertir el array de todos los productos a JSON
        $jsonData = json_encode($allProducts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
        // Asegurarse de que el directorio exista
        $filePath = MUTICATALOGOGNU__PLUGIN_DIR . '/admin/dataMulticatalogoGNU/promoimport_products.json';
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
    
        // Guardar en archivo
        file_put_contents($filePath, $jsonData);
    
        return $allProducts;
    }
    
    

}