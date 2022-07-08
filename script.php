#!/usr/bin/php8.1
<?php

include_once(__DIR__.'/config.php');

class getLastWeight {

    private $timestamp = 0;
    private $nonce = "";
    private $access_token = "";


    public function __construct() {
        $this->curl = curl_init();
        $this->setTimestamp();
        $this->setNonce();
        $this->setAccessToken();
    }

    private function setTimestamp(): void {
        $date = new DateTime();
        $this->timestamp = $date->getTimestamp();
    }

    private function getTimestamp(): int {
        return $this->timestamp;
    }

    private function encrypt($data, $key): string {
        return hash_hmac("sha256", $data, $key);
    }

    private function setNonce(): void {
        $data = "getnonce".','.ClientID.','.$this->timestamp;
        $signature = $this->encrypt($data, ClientSecret);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, Endpoint."/v2/signature");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([ 
            'action'    => 'getnonce',
            'client_id' => ClientID,
            'timestamp' => $this->getTimestamp(),
            'signature' => $signature
        ]));
        $rsp = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $this->nonce = $rsp["body"]["nonce"];
    }

    private function getNonce(): string {
        return $this->nonce;
    }

    public function setAccessToken(): void {
        $data = "getdemoaccess".','.ClientID.','.$this->nonce;
        $signature = $this->encrypt($data, ClientSecret);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, Endpoint."/v2/oauth2");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([ 
            'action'        => 'getdemoaccess',
            'client_id'     => ClientID,
            'nonce'         => $this->getNonce(),
            'signature'     => $signature,
            'scope_oauth2'  => 'user.metrics'
        ]));
        $rsp = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $this->access_token = $rsp["body"]["access_token"];
    }

    private function getAccessToken(): string {
        return $this->access_token;
    }

    public function getWeight(): int {
        $ondeDaySec = 24*60*60;
        $nowMinusOneDay = $this->timestamp - $ondeDaySec;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, Endpoint."/measure");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$this->getAccessToken()
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([ 
            'action'        => 'getmeas',
            'category'      => 1,
            'lastupdate'    => $nowMinusOneDay,
            'meastypes'     => 1
        ]));
        $rsp = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $value = $rsp["body"]["measuregrps"][0]["measures"][0]["value"];
        $unit = $rsp["body"]["measuregrps"][0]["measures"][0]["unit"];
        return $value * pow(10, $unit);
    }

}

$lastWeight = new getLastWeight();
$res = $lastWeight->getWeight();
echo "Last weight measurment = ".$res."Kg", "\n";

exit();

?>