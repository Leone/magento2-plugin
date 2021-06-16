<?php
namespace Sendinblue\Sendinblue\Model;
class SendinblueSibClient
{
    const API_BASE_URL = 'https://api.sendinblue.com/v3';
    const HTTP_METHOD_GET = 'GET';
    const HTTP_METHOD_POST = 'POST';
    const HTTP_METHOD_PUT = 'PUT';
    const HTTP_METHOD_DELETE = 'DELETE';
    const RESPONSE_CODE_OK = 200;
    const RESPONSE_CODE_CREATED = 201;
    const RESPONSE_CODE_ACCEPTED = 202;
    const RESPONSE_CODE_UPDATED = 204;
    const USER_AGENT = "sendinblue_plugins/magento_2";

    private $apiKey;
    private $lastResponseCode;
    private $pluginVersion;

    /**
     * SibApiClient constructor.
     */
    public function __construct($key = "")
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $model = $objectManager->create('Sendinblue\Sendinblue\Model\SendinblueSib');
        if( empty($key) ) {
            $this->apiKey = trim($model->_getValueDefault->getValue('sendinblue/api_key_v3', $model->_scopeTypeDefault));            
        }
        else {
            $this->apiKey = trim($key);
        }
        $this->pluginVersion = $model->_getValueDefault->getValue('sendinblue/current_version', $model->_scopeTypeDefault);
        if (empty($this->pluginVersion)) {
            $this->pluginVersion = $model->checkPluginVersion();
        }
    }

    public function getPluginVersion()
    {
        return $this->pluginVersion;
    }

    /**
     * @return mixed
     */
    public function getAccount()
    {
        return $this->get('/account');
    }

    /**
     * @param $data
     * @return mixed
     */
    public function getLists($data)
    {
        return $this->get("/contacts/lists",$data);
    }


    /**
     * @param $data
     * @return mixed
     */
    public function getListsInFolder($folder, $data)
    {
        return $this->get("/contacts/folders/".$folder."/lists", $data);
    }

    /**
     * @param $data
     * @return mixed
     */
    public function importUsers($data)
    {
        return $this->post("/contacts/import",$data);
    }

    /**
     * @param $data
     * @return mixed
     */
    public function getAllLists($folder = 0)
    {
        $lists = array("lists" => array(), "count" => 0);
        $offset = 0;
        $limit = 50;
        do {
            if ($folder > 0) {
                $list_data = $this->getListsInFolder($folder, array('limit' => $limit, 'offset' => $offset));
            }
            else {
                $list_data = $this->getLists(array('limit' => $limit, 'offset' => $offset));    
            }
            if ( !isset($list_data["lists"]) ) {
                $list_data = array("lists" => array(), "count" => 0);
            }
            $lists["lists"] = array_merge($lists["lists"], $list_data["lists"]) ;
            $offset += 50;
        }
        while ( count($lists["lists"]) < $list_data["count"] );
        $lists["count"] = $list_data["count"];
        return $lists;
    }

    /*
     * @return mixed
     */
    public function getAttributes()
    {
        return $this->get("/contacts/attributes");
    }

    /**
     * @param $type,$name,$data
     * @return mixed
     */
    public function createAttribute($type, $name, $data)
    {
        return $this->post("/contacts/attributes/".$type."/".$name,$data);
    }

    /**
     * @return mixed
     */
    public function getFolders($data)
    {
        return $this->get("/contacts/folders", $data);
    }

    public function getFoldersAll()
    {
        $folders = array("folders" => array(), "count" => 0);
        $offset = 0;
        $limit = 50;
        do {
            $folder_data = $this->getFolders(array('limit' => $limit, 'offset' => $offset));
            $folders["folders"] = array_merge($folders["folders"],$folder_data["folders"]) ;
            $offset += 50;
        }
        while ( count($folders["folders"]) < $folder_data["count"] );
        $folders["count"] = $folder_data["count"];
        return $folders;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function createFolder($data)
    {
        return $this->post("/contacts/folders", $data);
    }

    /**
     * @param $data
     * @return mixed
     */
    public function createList($data)
    {
        return $this->post("/contacts/lists", $data);
    }

    /**
     * @param $email
     * @return mixed
     */
    public function getUser($email)
    {
        return $this->get("/contacts/". urlencode($email));
    }

    /**
     * @param $data
     * @return mixed
     */
    public function createUser($data)
    {
        return $this->post("/contacts",$data);
    }

    /**
     * @param $email,$data
     * @return mixed
     */
    public function updateUser($email, $data)
    {
        return $this->put("/contacts/".urlencode($email), $data);
    }
  
    public function sendSms($data)
    {
        return $this->post('/transactionalSMS/sms',$data);
    }

    /**
     * @param $data
     * @return mixed
     */
    public function createInstallationInfo($data)
    {
        return $this->post("/account/partner/information", $data);
    }

    /**
     * @param $installationId ,$data
     * @return mixed
     */
    public function updateInstallationInfo($installationId, $data)
    {
        return $this->put("/account/partner/information/" . $installationId, $data);
    }

    /**
     * @param $data
     * @return mixed
     */
    public function sendTransactionalTemplate($data)
    {
        return $this->post("/smtp/email",$data);
    }

    public function createSmsCampaign($data)
    {
        return $this->post('/smsCampaigns',$data);
    }


    /**
     * @param $data
     * @return mixed
     */
    public function getEmailTemplates($data)
    {
        return $this->get("/smtp/templates",$data);
    }

    /**
     * @param $data
     * @return mixed
     */
    public function getAllEmailTemplates()
    {
        $templates = array("templates" => array(), "count" => 0);
        $offset = 0;
        $limit = 50;
        do {
            $template_data = $this->getEmailTemplates(array('templateStatus' => 'true', 'limit' => $limit, 'offset' => $offset));
            if ( !isset($template_data["templates"]) ) {
                $template_data = array("templates" => array(), "count" => 0);
            }
            $templates["templates"] = array_merge($templates["templates"],$template_data["templates"]) ;
            $offset += 50;
        }
        while ( count($templates["templates"]) < $template_data["count"] );
        $templates["count"] = count($templates["templates"]);
        return $templates;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getTemplateById($id)
    {
        return $this->get("/smtp/templates/". $id);
    }

    /**
     * @param $data
     * @return mixed
     */
    public function sendEmail($data)
    {
        return $this->post("/smtp/email",$data);
    }

    /**
     * @param $data
     * @return mixed
     */
    public function getSenders()
    {
        return $this->get("/senders");
    }
  
    /**
     * @param $endpoint
     * @param array $parameters
     * @return mixed
     */
    public function get($endpoint, $parameters = [])
    {
        if ($parameters) {
            $endpoint .= '?' . http_build_query($parameters);
        }
        return $this->makeHttpRequest(self::HTTP_METHOD_GET, $endpoint);
    }

    /**
     * @param $endpoint
     * @param array $data
     * @return mixed
     */
    public function post($endpoint, $data = [])
    {
        return $this->makeHttpRequest(self::HTTP_METHOD_POST, $endpoint, $data);
    }

    /**
     * @param $endpoint
     * @param array $data
     * @return mixed
     */
    public function put($endpoint, $data = [])
    {
        return $this->makeHttpRequest(self::HTTP_METHOD_PUT, $endpoint, $data);
    }

    /**
     * @param $method
     * @param $endpoint
     * @param array $body
     * @return mixed
     */
    private function makeHttpRequest($method, $endpoint, $body = [])
    {
        $url = self::API_BASE_URL . $endpoint;
        $this->lastResponseCode = "";

        $curl = curl_init();

        curl_setopt_array($curl, [
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => $method,
          CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "User-Agent: " . self::USER_AGENT,
            "sib-plugin: magento-". $this->pluginVersion,
            "api-key: ".$this->apiKey
          ],
        ]);

        if( !empty($body) ) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        }
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $this->lastResponseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($err) {
            return $err;
        }
        
        return json_decode($response, true);
    }


    public function curlPostAbandonedEvents($data, $ma_key)
    {
        $url = "https://in-automate.sendinblue.com/api/v2/trackEvent";
        $headers = array(
            'Content-Type: application/json',
            'ma-key: ' . $ma_key,
            'api-key: ' . $ma_key,
            'User-Agent: ' . self::USER_AGENT
        );

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
        ));
        curl_exec($curl);
        curl_close($curl);
    }


    /**
     * @return int
     */
    public function getLastResponseCode()
    {
        return $this->lastResponseCode;
    }
}
