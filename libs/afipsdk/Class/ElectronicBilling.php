<?php
/**
 * SDK for AFIP Electronic Billing (wsfe1)
 **/

class ElectronicBilling extends AfipWebService {

	var $soap_version 	= SOAP_1_2;
	var $WSDL 			= 'wsfe-production.wsdl';
	var $URL 			= 'https://servicios1.afip.gov.ar/wsfev1/service.asmx';
	var $WSDL_TEST 		= 'wsfe.wsdl';
	var $URL_TEST 		= 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';

	function __construct($afip) {
        parent::__construct($afip, array('service' => 'wsfe'));
    }

	public function GetLastVoucher($sales_point, $type) {
		$req = array('PtoVta' => $sales_point, 'CbteTipo' => $type);
		return $this->ExecuteRequest('FECompUltimoAutorizado', $req)->CbteNro;
	}

	public function CreateVoucher($data, $return_response = FALSE) {
		$req = array(
			'FeCAEReq' => array(
				'FeCabReq' => array(
					'CantReg' 	=> $data['CbteHasta']-$data['CbteDesde']+1,
					'PtoVta' 	=> $data['PtoVta'],
					'CbteTipo' 	=> $data['CbteTipo']
					),
				'FeDetReq' => array('FECAEDetRequest' => &$data)
			)
		);
		
		$results = $this->ExecuteRequest('FECAESolicitar', $req);
		return $return_response ? $results : [
			'CAE' => $results->FeDetResp->FECAEDetResponse->CAE,
			'CAEFchVto' => $results->FeDetResp->FECAEDetResponse->CAEFchVto
		];
	}

	public function ExecuteRequest($operation, $params = array()) {
		$this->options = array('service' => 'wsfe');
		$params = array_replace($this->GetWSInitialRequest($operation), $params); 
		$results = parent::ExecuteRequest($operation, $params);
		return $results->{$operation.'Result'};
	}

	private function GetWSInitialRequest($operation) {
		if ($operation == 'FEDummy') return array();
		$ta = $this->afip->GetServiceTA('wsfe');
		return array(
			'Auth' => array('Token' => $ta->token, 'Sign' => $ta->sign, 'Cuit' => $this->afip->CUIT)
		);
	}
}
