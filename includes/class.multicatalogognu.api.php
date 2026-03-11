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
        
        // Configurar headers completos
        $headers = [
            'authority: api.promoimport.cl',
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'accept-encoding: gzip, deflate, br, zstd',
            'accept-language: en-US,en;q=0.9,es-419;q=0.8,es;q=0.7,pt;q=0.6',
            'cache-control: max-age=0',
            'cookie: _ga=GA1.1.2014012861.1761581723; sbjs_migrations=1418474375998%3D1; sbjs_current_add=fd%3D2026-02-19%2021%3A17%3A53%7C%7C%7Cep%3Dhttps%3A%2F%2Fpromoimport.cl%2Fproduct%2Fcooler-bag-iceland-8-5l%2F%7C%7C%7Crf%3D%28none%29; sbjs_first_add=fd%3D2026-02-19%2021%3A17%3A53%7C%7C%7Cep%3Dhttps%3A%2F%2Fpromoimport.cl%2Fproduct%2Fcooler-bag-iceland-8-5l%2F%7C%7C%7Crf%3D%28none%29; sbjs_current=typ%3Dtypein%7C%7C%7Csrc%3D%28direct%29%7C%7C%7Cmdm%3D%28none%29%7C%7C%7Ccmp%3D%28none%29%7C%7C%7Ccnt%3D%28none%29%7C%7C%7Ctrm%3D%28none%29%7C%7C%7Cid%3D%28none%29%7C%7C%7Cplt%3D%28none%29%7C%7C%7Cfmt%3D%28none%29%7C%7C%7Ctct%3D%28none%29; sbjs_first=typ%3Dtypein%7C%7C%7Csrc%3D%28direct%29%7C%7C%7Cmdm%3D%28none%29%7C%7C%7Ccmp%3D%28none%29%7C%7C%7Ccnt%3D%28none%29%7C%7C%7Ctrm%3D%28none%29%7C%7C%7Cid%3D%28none%29%7C%7C%7Cplt%3D%28none%29%7C%7C%7Cfmt%3D%28none%29%7C%7C%7Ctct%3D%28none%29; sbjs_udata=vst%3D1%7C%7C%7Cuip%3D%28none%29%7C%7C%7Cuag%3DMozilla%2F5.0%20%28Windows%20NT%2010.0%3B%20Win64%3B%20x64%29%20AppleWebKit%2F537.36%20%28KHTML%2C%20like%20Gecko%29%20Chrome%2F144.0.0.0%20Safari%2F537.36; cf_clearance=LwZJXi2pdZIXTcksUgwyI6ln8XL_TM4zKJsJYbt.Kfs-1771535878-1.2.1.1-_i5eCrCbg2dYCn0JbEWFeD98prsjG2YlE1M4mhvM6xGBWc.dHnZkgdctJ7McPphDcHNJg01AqQpYdH3dcbrUOE6XpqZhHPooZ6p688x2Gax3CFW9bVQuBbg52l2dXQR7dvuYgEzQ1UvHxCnKvdEcIbqz8_M9cg6nzbN4FgL7ypPokl52GP4dzu1xlMYuVIzEuk6eNMgbdjf7MnMAHge.DCXz8eYivJnv4pwqZnmXGK0; _ga_8FNSXZYM1N=GS2.1.s1771535878$o10$g0$t1771537448$j60$l0$h1147396844',
            'dnt: 1',
            'priority: u=0, i',
            'sec-ch-ua: "Not:A-Brand";v="99", "Google Chrome";v="145", "Chromium";v="145"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'sec-fetch-dest: document',
            'sec-fetch-mode: navigate',
            'sec-fetch-site: cross-site',
            'sec-fetch-user: ?1',
            'upgrade-insecure-requests: 1',
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'
        ];
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Automatically handle all encodings
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Manejar posibles errores de cURL
        if (curl_error($ch)) {
            error_log('cURL Error en fgetProductsPromoImport: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (is_array($data)) {
                $allProducts = $data;
            }
        } else {
            error_log("Error HTTP $httpCode al obtener productos de PromoImport");
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