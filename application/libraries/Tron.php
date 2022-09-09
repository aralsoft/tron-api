<?php

use vendor\kornrunner\Secp256k1;
use vendor\kornrunner\Signature\Signature;
use vendor\Mdanter\Ecc\Crypto\Signature\SignatureInterface;
use vendor\Mdanter\Ecc\Curves\CurveFactory;
use vendor\Mdanter\Ecc\Curves\SecgCurve;
use vendor\Mdanter\Ecc\EccFactory;
use vendor\Mdanter\Ecc\Primitives\PointInterface;
use vendor\Mdanter\Ecc\Random\RandomGeneratorFactory;

class Tron
{
    protected string $tron_live_url = '';

    // CodeIgniter super-object
    protected $CI;
    
    // Constructor function
    public function __construct($params = [])
    {   
        $this->CI =& get_instance();

        $this->CI->load->helper(array('address'));

        $this->CI->config->load('tron');

        $this->tron_live_url = $this->CI->config->item('tron_live_url');
    }

    public function getBalance($address)
    {
        if ($account = $this->getAccount($address)) {
            if (isset($account['balance']) && $account['balance']) {
                return floor($account['balance'] / 1000000);
            }
        }

        return 0;
    }

    // Create a new Tron transaction
    public function getAccount($address)
    {
        $url = "/wallet/getaccount";

        $postFields = array(
            'address' => base58check2HexString($address)
        );

        return $this->callAPI($url, $postFields);
    }

    // Create a new Tron transaction
    public function createTransaction($from_address, $to_address, $amount)
    {
        $url = "/wallet/createtransaction";

        $postFields = array(
            'to_address' => base58check2HexString($to_address),
            'owner_address' => base58check2HexString($from_address),
            'amount' => $amount,
            'extra_data' => bin2hex('Transaction notes.')
        );

        return $this->callAPI($url, $postFields);
    }

    // Sign a Tron transaction
    public function signTransaction($transaction, $from_private_key)
    {
        $secp = new \kornrunner\Secp256k1();

        $sign = $secp->sign($transaction['txID'], $from_private_key, ['canonical' => false]);

        return $sign->toHex() . bin2hex(implode('', array_map('chr', [$sign->getRecoveryParam()])));
    }

    // Broadcast a Tron transaction
    public function broadcastTransaction($transaction, $signature)
    {
        $url = "/wallet/broadcasttransaction";
        $transaction['signature'] = $signature;

        return $this->callAPI($url, $transaction);
    }

    // Call API
    public function callAPI($url, $postFields)
    {
        $ch = curl_init($this->tron_live_url.$url);

        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept' => 'application/json', 'Content-Type' => 'application/json']);

        $result = curl_exec($ch);

        if (curl_error($ch)) {
            return 'Curl Error: '.curl_error($ch);
        }

        curl_close($ch);

        return json_decode($result, TRUE);
    }
}