<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DonateController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function checkout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $isMonthly = ($body['mode'] ?? 'once') === 'monthly';
        $mode = $isMonthly ? 'subscription' : 'payment';
        $amount = max(1, min(100, (int) ($body['amount'] ?? 5)));

        \Stripe\Stripe::setApiKey($this->config['secret_key']);

        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST'];

        $priceData = [
            'unit_amount' => $amount * 100,
            'currency' => $this->config['currency'],
            'product_data' => ['name' => $isMonthly ? 'Don mensuel Spreadly' : 'Don Spreadly'],
        ];

        if ($isMonthly) {
            $priceData['recurring'] = ['interval' => 'month'];
        }

        $params = [
            'mode' => $mode,
            'line_items' => [[
                'price_data' => $priceData,
                'quantity' => 1,
            ]],
            'success_url' => $baseUrl . '/?donated=1',
            'cancel_url' => $baseUrl . '/',
        ];

        if (!$isMonthly) {
            $params['submit_type'] = 'donate';
        }

        $session = \Stripe\Checkout\Session::create($params);

        return $response
            ->withHeader('Location', $session->url)
            ->withStatus(303);
    }
}
