<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay Signature Handler Class
 * 
 * Handles RSA signature generation and verification using MD5withRSA algorithm
 */
class OnePay_Signature {
    
    /**
     * Generate MD5withRSA signature
     * 
     * @param string $data The data to sign
     * @param string $private_key The RSA private key
     * @return string|false The signature or false on failure
     */
    public static function sign($data, $private_key) {
        try {
            if (empty($data) || empty($private_key)) {
                return false;
            }
            
            $private_key = self::format_private_key($private_key);
            
            $key_resource = openssl_pkey_get_private($private_key);
            if (!$key_resource) {
                error_log('OnePay Signature: Failed to load private key - ' . openssl_error_string());
                return false;
            }
            
            // MD5withRSA签名：直接对原始数据进行签名，使用MD5作为摘要算法
            $signature = '';
            $result = openssl_sign($data, $signature, $key_resource, OPENSSL_ALGO_MD5);
            
            openssl_pkey_free($key_resource);
            
            if (!$result) {
                error_log('OnePay Signature: Failed to generate signature - ' . openssl_error_string());
                return false;
            }
            
            return base64_encode($signature);
            
        } catch (Exception $e) {
            error_log('OnePay Signature Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify MD5withRSA signature
     * 
     * @param string $data The original data
     * @param string $signature The signature to verify
     * @param string $public_key The RSA public key
     * @return bool True if signature is valid, false otherwise
     */
    public static function verify($data, $signature, $public_key) {
        try {
            if (empty($data) || empty($signature) || empty($public_key)) {
                return false;
            }
            
            $public_key = self::format_public_key($public_key);
            
            $key_resource = openssl_pkey_get_public($public_key);
            if (!$key_resource) {
                error_log('OnePay Signature: Failed to load public key - ' . openssl_error_string());
                return false;
            }
            
            // MD5withRSA验证：直接对原始数据进行验证，使用MD5作为摘要算法
            $signature_decoded = base64_decode($signature);
            
            if ($signature_decoded === false) {
                error_log('OnePay Signature: Failed to decode signature');
                openssl_pkey_free($key_resource);
                return false;
            }
            
            $result = openssl_verify($data, $signature_decoded, $key_resource, OPENSSL_ALGO_MD5);
            
            openssl_pkey_free($key_resource);
            
            return $result === 1;
            
        } catch (Exception $e) {
            error_log('OnePay Signature Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Format private key with proper headers
     * 
     * @param string $key The private key
     * @return string Formatted private key
     */
    private static function format_private_key($key) {
        $key = trim($key);
        $key = str_replace(array("\r\n", "\r"), "\n", $key);
        
        if (strpos($key, '-----BEGIN') === false) {
            $key = "-----BEGIN PRIVATE KEY-----\n" . chunk_split($key, 64, "\n") . "-----END PRIVATE KEY-----\n";
        }
        
        if (strpos($key, 'RSA PRIVATE KEY') === false && strpos($key, 'PRIVATE KEY') === false) {
            $key = str_replace('-----BEGIN PRIVATE KEY-----', '-----BEGIN RSA PRIVATE KEY-----', $key);
            $key = str_replace('-----END PRIVATE KEY-----', '-----END RSA PRIVATE KEY-----', $key);
        }
        
        return $key;
    }
    
    /**
     * Format public key with proper headers
     * 
     * @param string $key The public key
     * @return string Formatted public key
     */
    private static function format_public_key($key) {
        $key = trim($key);
        $key = str_replace(array("\r\n", "\r"), "\n", $key);
        
        if (strpos($key, '-----BEGIN') === false) {
            $key = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($key, 64, "\n") . "-----END PUBLIC KEY-----\n";
        }
        
        if (strpos($key, 'RSA PUBLIC KEY') === false && strpos($key, 'PUBLIC KEY') === false) {
            $key = str_replace('-----BEGIN PUBLIC KEY-----', '-----BEGIN RSA PUBLIC KEY-----', $key);
            $key = str_replace('-----END PUBLIC KEY-----', '-----END RSA PUBLIC KEY-----', $key);
        }
        
        return $key;
    }
    
    /**
     * Generate RSA key pair
     * 
     * @param int $bits Key size in bits (default: 2048)
     * @return array|false Array with 'private' and 'public' keys or false on failure
     */
    public static function generate_key_pair($bits = 2048) {
        try {
            $config = array(
                "digest_alg" => "sha256",
                "private_key_bits" => $bits,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            );
            
            $key_resource = openssl_pkey_new($config);
            if (!$key_resource) {
                error_log('OnePay Signature: Failed to generate key pair - ' . openssl_error_string());
                return false;
            }
            
            openssl_pkey_export($key_resource, $private_key);
            $key_details = openssl_pkey_get_details($key_resource);
            $public_key = $key_details['key'];
            
            openssl_pkey_free($key_resource);
            
            return array(
                'private' => $private_key,
                'public' => $public_key
            );
            
        } catch (Exception $e) {
            error_log('OnePay Signature Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test signature functionality
     * 
     * @param string $private_key Private key for testing
     * @param string $public_key Public key for testing
     * @return bool True if test passes, false otherwise
     */
    public static function test_signature($private_key, $public_key) {
        $test_data = '{"merchantNo":"TEST123","orderAmount":"1000","currency":"RUB"}';
        
        $signature = self::sign($test_data, $private_key);
        if (!$signature) {
            return false;
        }
        
        return self::verify($test_data, $signature, $public_key);
    }
    
    /**
     * Extract public key from certificate
     * 
     * @param string $cert_data Certificate data
     * @return string|false Public key or false on failure
     */
    public static function extract_public_key_from_cert($cert_data) {
        try {
            $cert = openssl_x509_read($cert_data);
            if (!$cert) {
                return false;
            }
            
            $public_key = openssl_pkey_get_public($cert);
            if (!$public_key) {
                openssl_x509_free($cert);
                return false;
            }
            
            $key_details = openssl_pkey_get_details($public_key);
            
            openssl_pkey_free($public_key);
            openssl_x509_free($cert);
            
            return isset($key_details['key']) ? $key_details['key'] : false;
            
        } catch (Exception $e) {
            error_log('OnePay Signature Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate key format
     * 
     * @param string $key The key to validate
     * @param string $type 'private' or 'public'
     * @return bool True if key is valid, false otherwise
     */
    public static function validate_key($key, $type = 'private') {
        try {
            if ($type === 'private') {
                $key = self::format_private_key($key);
                $key_resource = openssl_pkey_get_private($key);
            } else {
                $key = self::format_public_key($key);
                $key_resource = openssl_pkey_get_public($key);
            }
            
            if (!$key_resource) {
                return false;
            }
            
            openssl_pkey_free($key_resource);
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
}