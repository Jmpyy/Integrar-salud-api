<?php
/**
 * Integrar Salud - AfipManager
 * Wrapper for AfipSDK handling database config and certificate paths.
 */

require_once __DIR__ . '/../libs/afipsdk/Afip.php';
require_once __DIR__ . '/Database.php';

class AfipManager {
    private static ?Afip $sdk = null;
    private static ?array $config = null;

    /**
     * Initializes the SDK fetching config from DB
     */
    public static function init() {
        if (self::$sdk !== null) return self::$sdk;

        $db = Database::connect();
        $stmt = $db->query('SELECT * FROM afip_config WHERE id = 1');
        $conf = $stmt->fetch();

        if (!$conf || !$conf['cuit']) {
            throw new Exception("AFIP no configurado. Falta CUIT.");
        }

        self::$config = $conf;

        // Rutas completas a certificados
        $baseDir = __DIR__ . '/../certificates/';
        $certPath = $conf['cert_file'] ? $baseDir . $conf['cert_file'] : null;
        $keyPath  = $conf['key_file']  ? $baseDir . $conf['key_file']  : null;

        self::$sdk = new Afip([
            'CUIT'       => (float)$conf['cuit'],
            'production' => ($conf['environment'] === 'prod'),
            'cert'       => $certPath ? file_get_contents($certPath) : null,
            'key'        => $keyPath  ? file_get_contents($keyPath)  : null
        ]);

        return self::$sdk;
    }

    /**
     * Get Server Status (Dummy check)
     */
    public static function getStatus() {
        $sdk = self::init();
        return $sdk->ElectronicBilling->ExecuteRequest('FEDummy');
    }

    /**
     * Get Next Voucher Number
     */
    public static function getNextNumber($type = 11) {
        $sdk = self::init();
        $ptovta = self::$config['punto_venta'] ?: 1;
        return $sdk->ElectronicBilling->GetLastVoucher($ptovta, $type) + 1;
    }

    /**
     * Emit a Voucher (Factura C by default)
     */
    public static function emitInvoice($data) {
        $sdk = self::init();
        
        $ptovta = self::$config['punto_venta'] ?: 1;
        $type   = (self::$config['tax_condition'] === 'ri') ? 1 : 11; // 1: Factura A, 11: Factura C

        $date = date('Ymd');
        
        $payload = [
            'PtoVta'      => $ptovta,
            'CbteTipo'    => $type,
            'Concepto'    => $data['concepto'] ?? 1, // 1: Productos, 2: Servicios, 3: Mixto
            'DocTipo'     => $data['doc_tipo'] ?? 99, // 99: Consumidor Final, 80: CUIT, 96: DNI
            'DocNro'      => (float)($data['doc_nro'] ?? 0),
            'CbteDesde'   => $data['cbte_nro'],
            'CbteHasta'   => $data['cbte_nro'],
            'CbteFch'     => $date,
            'ImpTotal'    => $data['monto'],
            'ImpTotConc'  => 0,
            'ImpNeto'     => $data['monto'],
            'ImpOpEx'     => 0,
            'ImpIVA'      => 0,
            'ImpTrib'     => 0,
            'MonId'       => 'PES',
            'MonCotiz'    => 1
        ];

        // Si son servicios (Concepto 2), hay que pasar fechas
        if ($payload['Concepto'] > 1) {
            $payload['FchServDesde'] = $date;
            $payload['FchServHasta'] = $date;
            $payload['FchVtoPago']   = $date;
        }

        return $sdk->ElectronicBilling->CreateVoucher($payload);
    }
}
