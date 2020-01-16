<?php
// src/Controller/Home.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RedirectResponse;

class Home extends AbstractController
{
    private $client;
    private $api = 'https://api-sandbox.fintecture.com';
    private $providersEndpoint = '/res/v1/providers/';
    private $redirectUrl = 'https://62ecaf1d.ngrok.io/access';
    private $appId = '1683e2e3-dad4-4027-969e-3948457423b8';
    private $appSecret = '5e82adf3-02af-47e2-b4cd-aa542b43f2cd';
    private $basicToken;

    public function __construct()
    {
        $this->client = HttpClient::createForBaseUri($this->api);
        $this->basicToken = base64_encode($this->appId . ':' . $this->appSecret);
    }

    public function landing()
    {
        $response = $this->client->request('GET', $this->providersEndpoint, 
            ['headers' => [
                'app_id' => $this->appId,
                'Accept' => 'application/json'
                ]
            ]
        );

        $providers = json_decode($response->getContent())->data;

        return $this->render('base.html.twig', ['banks' => $providers]);
    }

    public function authorize(Request $request)
    {
        $provider = $request->request->get('provider');
        $response = $this->client->request('GET', '/ais/v1/provider/' . $provider . '/authorize?response_type=code&redirect_uri=' . $this->redirectUrl, 
            ['headers' => [
                'app_id' => $this->appId,
                'Accept' => 'application/json'
                ]
            ]
        );

        $url = json_decode($response->getContent())->url;
        return new RedirectResponse($url);
    }

    public function access(Request $request)
    {
        $customerId = $request->query->get('customer_id');
        $code = $request->query->get('code');

        $body = [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'scope' => 'AIS'
                ];

        $response = $this->client->request('POST', '/oauth/accesstoken', 
            [
                'headers' => [
                    'Authorization' => 'Basic ' . $this->basicToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'body' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'scope' => 'AIS'
                ]
            ]
        );

        $accessToken = json_decode($response->getContent())->access_token;
        $data = $this->getAccount($accessToken, $customerId);
        
        $mainAccount = $data[0];
        $balance = $mainAccount->attributes->balance;
        $currency = $mainAccount->attributes->currency;
        
        return $this->render('details.html.twig', ['balance' => $balance, 'currency' => $currency]);
    }

    public function getAccount($accessToken, $customerId)
    {
        $response = $this->client->request('GET', '/ais/v1/customer/' . $customerId . '/accounts', 
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ]
            ]
        );

        return json_decode($response->getContent())->data;
    }
}