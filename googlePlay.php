<?php
// google play 退單 api 範例
/**
* server to server
* https://developers.google.com/android-publisher/voided-purchases
* https://zespia.tw/blog/2014/07/28/use-google-analytics-api-on-server/
* http://stackoverflow.com/questions/11116143/sha256withrsa-sign-from-php-verify-from-java
* http://stackoverflow.com/questions/38079638/php-google-api-oauth2-jwt-request-for-new-acces-token-says-invalid-grant
* https://github.com/firebase/php-jwt/blob/master/src/JWT.php
* https://developers.google.com/identity/protocols/OAuth2ServiceAccount#handlingresponse
* https://developers.google.com/oauthplayground/
* http://stackoverflow.com/questions/11475101/when-is-access-type-online-appropriate-oauth2-google-api
* https://developers.google.com/android-publisher/api-ref/purchases/voidedpurchases/list
*/
class GooglePlay {

    private $_accessTokenConfig = [
        'url' => 'https://accounts.google.com/o/oauth2/token',
        'params' => [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            // 'assertion' => $this->_createSign($config)
        ],
        'method' => 'POST',
        'header' => ['Content-Type'=>'application/x-www-form-urlencoded']
    ];

    public function __construct() {}
    
    /** 
     * 主程式入口
     * 參數範例:
     * $config = [
            'packageName' => 'com.xxx.qq', 
            'iss' => 'voidedpurchases@api.com', 
            'keyFile' => 'Project.json'
        ];
     */
    public function getVoidedPurchases($config) {
        $this->_checkParams($config);
        
        $_accessToken = $this->_getAccessToken($config);
        
        if($_accessToken === False) {
            exit('not get access_token...');
        }
        
        $result = $this->_sendPostRequest(
            "https://www.googleapis.com/androidpublisher/v2/applications/{$config['packageName']}/purchases/voidedpurchases?access_token={$_accessToken}",
            [],
            'GET'
        );
        
        // 排序
        function _sort($a, $b) {
            $_colName = isset($_GET['colName']) ? $_GET['colName'] : 'voidedTimeMillis';
            if($a[$_colName] == $b[$_colName]) return 0;
            return ($a[$_colName] > $b[$_colName]) ? 1 : -1;
        }
    
        usort($result['voidedPurchases'], '_sort');
        
        $time = time();
        $this->_setLog($config['packageName'].'/'.date('ymdHis', $time).'_'.$time, json_encode($result));
        
        if(isset($_GET['format']) && $_GET['format'] == 'json') {
            echo json_encode($result);
            exit;
        }
        $this->_createTable($result);
    }
    
    // 參數驗證
    private function _checkParams($config) {
        if(!array_key_exists('packageName', $config)) {
            exit('miss packageName...');
        }
        
        if(!array_key_exists('iss', $config)) {
            exit('miss iss...');
        }
        
        if(!array_key_exists('keyFile', $config)) {
            exit('miss keyFile...');
        }
    }
    
    // 取得 access token
    private function _getAccessToken($config) {
        $result = $this->_sendPostRequest(
            $this->_accessTokenConfig['url'],
            [
                'grant_type' =>  $this->_accessTokenConfig['params']['grant_type'],
                'assertion' => $this->_createSign($config)
            ],
            $this->_accessTokenConfig['method'],
            $this->_accessTokenConfig['header']
        );
        
        return $result['access_token'] ? $result['access_token'] : FALSE;
    }
    
    // 建立簽章
    private function _createSign($config) {
        $info = json_decode(file_get_contents($config['keyFile']), TRUE);  // 取得密鑰

        $_key = $info['private_key'];

        $segments = [];
        $segments[] = $this->_base64url_encode(json_encode(['alg'=>'RS256', 'typ'=>'JWT']));
        $segments[] = $this->_base64url_encode(json_encode([
            "iss" => $config['iss'],   // 帳號
            "scope" => "https://www.googleapis.com/auth/androidpublisher",      // 取得授權
            "aud" => "https://accounts.google.com/o/oauth2/token",              // token url
            "exp" => time() + 3600,
            "iat" => time()]
        ));
        $signing_input = implode('.', $segments);

        $binary_signature = "";
        openssl_sign($signing_input, $binary_signature, $_key, "SHA256");
        $segments[] = $this->_base64url_encode($binary_signature);
        
        return implode('.', $segments);
    }
    
    // base64 encode
    private function _base64url_encode($data) {
        return str_replace('=', '', strtr(base64_encode($data), '+/', '-_'));
    }

    // 發個 post
    private function _sendPostRequest($url, $data = array(), $type = "POST", $header = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        if($type == "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        return json_decode(curl_exec($ch), TRUE);
    }

    // 畫表格
    private function _createTable($data) {
        $_html = <<<HTML
        <table border="1" style="font-size:4px">
        <tr>
            <td>id</td>
            <td>產品識別碼</td>
            <td>下單時間</td>
            <td>退單時間</td>
        </tr>
HTML;

        $i = 1;
        foreach($data['voidedPurchases'] as $row) {
        
            $ptm = date("y-m-d H:i:s", intval($row['purchaseTimeMillis']/1000));
            $vtm = date("y-m-d H:i:s", intval($row['voidedTimeMillis']/1000));
        
            $_html .= <<<HTML
            <tr>
                <td>{$i}</td>
                <td>{$row['purchaseToken']}</td>
                <td data-timestemp="{$row['purchaseTimeMillis']}">{$ptm}</td>
                <td data-timestemp="{$row['voidedTimeMillis']}">{$vtm}</td>
            </tr>
HTML;
            $i++;
        }

        $_html .=  <<<HTML
        </table>
HTML;
        echo $_html;
    }
    
    // 寫log
    private function _setLog($fileName, $fileMsg) {
        $file = fopen("logs/$fileName.log","a");
        fwrite($file, $fileMsg);
        fclose($file);
    }
    
}