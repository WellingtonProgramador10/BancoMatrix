<?php
/**
 * Classe auxiliar para interagir com a API ASAAS
 */
class AsaasAPI {
    private $apiUrl;
    private $apiToken;
    
    /**
     * Construtor
     * 
     * @param string $apiUrl URL base da API ASAAS
     * @param string $apiToken Token de acesso à API
     */
    public function __construct($apiUrl, $apiToken) {
        $this->apiUrl = $apiUrl;
        $this->apiToken = $apiToken;
    }
    
    /**
     * Obtém o status dos documentos do cliente
     * 
     * @return array Informações sobre os documentos
     */
    public function getDocumentsStatus() {
        $documentsData = [];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->apiUrl}/myAccount/documents");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'access_token: ' . $this->apiToken
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            $responseData = json_decode($response, true);
            if (isset($responseData['data']) && is_array($responseData['data'])) {
                $documentsData = $responseData['data'];
            }
        }
        
        return $documentsData;
    }
    
    /**
     * Cria um novo cliente na ASAAS
     * 
     * @param array $clientData Dados do cliente
     * @return array|bool Dados do cliente criado ou falso em caso de erro
     */
    public function createCustomer($clientData) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->apiUrl}/customers");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($clientData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'access_token: ' . $this->apiToken,
            'content-type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 || $httpCode == 201) {
            return json_decode($response, true);
        }
        
        return false;
    }
    
    /**
     * Envia um documento para a ASAAS
     * 
     * @param string $documentId ID do documento na ASAAS
     * @param string $filePath Caminho do arquivo a ser enviado
     * @param string $documentType Tipo do documento
     * @return array|bool Resultado do envio ou falso em caso de erro
     */
    public function sendDocument($documentId, $filePath, $fileType, $fileName, $documentType) {
        $cFile = curl_file_create($filePath, $fileType, $fileName);
        
        $postData = [
            'documentFile' => $cFile,
            'type' => $documentType
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->apiUrl}/myAccount/documents/{$documentId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'access_token: ' . $this->apiToken,
            'content-type: multipart/form-data'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            return json_decode($response, true);
        }
        
        return false;
    }
    
    /**
     * Verifica o status de um documento específico
     * 
     * @param string $documentId ID do documento
     * @return array|bool Informações do documento ou falso em caso de erro
     */
    public function getDocumentStatus($documentId) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->apiUrl}/myAccount/documents/files/{$documentId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'access_token: ' . $this->apiToken
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            return json_decode($response, true);
        }
        
        return false;
    }
    
    /**
     * Atualiza um documento já enviado
     * 
     * @param string $documentId ID do documento
     * @param string $filePath Caminho do arquivo a ser enviado
     * @param string $documentType Tipo do documento
     * @return array|bool Resultado da atualização ou falso em caso de erro
     */
    public function updateDocument($documentId, $filePath, $fileType, $fileName, $documentType) {
        return $this->sendDocument($documentId, $filePath, $fileType, $fileName, $documentType);
    }
    
    /**
     * Remove um documento enviado
     * 
     * @param string $documentId ID do documento
     * @return bool Resultado da remoção
     */
    public function deleteDocument($documentId) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->apiUrl}/myAccount/documents/files/{$documentId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'access_token: ' . $this->apiToken
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            $result = json_decode($response, true);
            return isset($result['deleted']) && $result['deleted'] === true;
        }
        
        return false;
    }
    
    /**
     * Traduz o status da ASAAS para português
     * 
     * @param string $status Status original
     * @return string Status traduzido
     */
    public static function translateStatus($status) {
        switch ($status) {
            case 'APPROVED': return 'Aprovado';
            case 'PENDING': return 'Pendente';
            case 'REJECTED': return 'Rejeitado';
            case 'NOT_SENT': return 'Não enviado';
            default: return $status;
        }
    }
    
    /**
     * Traduz o tipo de documento da ASAAS para português
     * 
     * @param string $type Tipo original
     * @return string Tipo traduzido
     */
    public static function translateDocumentType($type) {
        switch ($type) {
            case 'IDENTIFICATION': return 'Documento de Identificação';
            case 'IDENTIFICATION_SELFIE': return 'Selfie com Documento';
            case 'PROOF_OF_RESIDENCE': return 'Comprovante de Residência';
            case 'ENTREPRENEUR_REQUIREMENT': return 'Requerimento de Empresário';
            case 'SOCIAL_CONTRACT': return 'Contrato Social';
            case 'MINUTES_OF_ELECTION': return 'Ata de Eleição';
            case 'CUSTOM': return 'Personalizado';
            default: return $type;
        }
    }
}