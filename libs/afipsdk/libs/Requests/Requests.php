<?php
/**
 * Simple Mock for rmccue/requests to avoid full library dependency
 * Supports only the POST method used by AfipSDK
 */

class Requests {
    public static function post($url, $headers = [], $data = []) {
        $ch = curl_init($url);
        
        $curlHeaders = [];
        foreach ($headers as $key => $val) {
            $curlHeaders[] = "$key: $val";
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Permitir en homologación / entornos locales
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $result = new stdClass();
        $result->success = ($httpCode >= 200 && $httpCode < 300);
        $result->body = $response;
        $result->error = $error;
        
        return $result;
    }
    
    public static function register_autoloader() {
        // No-op for this simple mock
    }
}
