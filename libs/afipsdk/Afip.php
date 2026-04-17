<?php
/**
 * Software Development Kit for ARCA web services
 * 
 * @link https://afipsdk.com/
 * @author Afip SDK ayuda@afipsdk.com
 * @package Afip
 **/

if (!defined('SOAP_1_1')) { define('SOAP_1_1', 1); }
if (!defined('SOAP_1_2')) { define('SOAP_1_2', 2); }

// Nota: Requiere la librería Requests
include_once __DIR__.'/libs/Requests/Requests.php';
// Requests::register_autoloader(); // Lo manejaremos manualmente si es necesario

#[\AllowDynamicProperties]
class Afip {
	var $sdk_version_number = '1.2.0';
	var $CERT;
	var $PRIVATEKEY;
	var $CUIT;
	var $implemented_ws = array(
		'ElectronicBilling',
	);
	var $options;

	function __construct($options) {
		ini_set("soap.wsdl_cache_enabled", "0");
		$this->CUIT = $options['CUIT'] ?? NULL;
		$options['production'] = $options['production'] ?? FALSE;
		$this->options = $options;
		$this->CERT = $options['cert'] ?? NULL;
		$this->PRIVATEKEY = $options['key'] ?? NULL;
	}

	public function GetServiceTA($service, $force = FALSE) {
		$data = array(
			'environment' => $this->options['production'] === TRUE ? "prod" : "dev",
			'wsid' => $service,
			'tax_id' => $this->options['CUIT'],
			'force_create' => $force
		);

		if (isset($this->CERT)) { $data['cert'] = $this->CERT; }
		if ($this->PRIVATEKEY) { $data['key'] = $this->PRIVATEKEY; }

		$headers = array(
			'Content-Type' => 'application/json',
			'sdk-version-number' => $this->sdk_version_number,
			'sdk-library' => 'php',
			'sdk-environment' => $this->options['production'] === TRUE ? "prod" : "dev"
		);

		if (isset($this->options['access_token'])) {
			$headers['Authorization'] = 'Bearer '.$this->options['access_token'];
		}

		$request = Requests::post('https://app.afipsdk.com/api/v1/afip/auth', $headers, json_encode($data));

		if ($request->success) {
			$decoded_res = json_decode($request->body);
			return new TokenAuthorization($decoded_res->token, $decoded_res->sign);
		} else {
			throw new Exception($request->body);
		}
	}

	public function __get($property) {
		if (in_array($property, $this->implemented_ws)) {
			if (isset($this->{$property})) {
				return $this->{$property};
			} else {
				$file = __DIR__.'/Class/'.$property.'.php';
				if (!file_exists($file)) throw new Exception("Failed to open ".$file."\n", 1);
				include_once $file;
				return ($this->{$property} = new $property($this));
			}
		}
		return $this->{$property};
	}
}

class TokenAuthorization {
	var $token;
	var $sign;
	function __construct($token, $sign) {
		$this->token = $token;
		$this->sign = $sign;
	}
}

#[\AllowDynamicProperties]
class AfipWebService {
	var $soap_version;
	var $WSDL;
	var $URL;
	var $WSDL_TEST;
	var $URL_TEST;
	var $afip;
	var $options;

	function __construct($afip, $options = array()) {
		$this->afip = $afip;
		$this->options = $options;
		if (isset($options['generic']) && $options['generic'] === TRUE) {
			$this->soap_version = $options['soap_version'] ?? SOAP_1_2;
		}
	}

	public function ExecuteRequest($method, $params = array()) {
		$data = array(
			'method' => $method,
			'params' => $params,
			'environment' => $this->afip->options['production'] === TRUE ? "prod" : "dev",
			'wsid' => $this->options['service'],
			'url' => $this->afip->options['production'] === TRUE ? $this->URL : $this->URL_TEST,
			'wsdl' => $this->afip->options['production'] === TRUE ? $this->WSDL : $this->WSDL_TEST,
			'soap_v_1_2' => $this->soap_version === SOAP_1_2
		);

		$headers = array('Content-Type' => 'application/json');
		$request = Requests::post('https://app.afipsdk.com/api/v1/afip/requests', $headers, json_encode($data));

		if ($request->success) {
			return json_decode($request->body);
		} else {
			throw new Exception($request->body);
		}
	}
}
