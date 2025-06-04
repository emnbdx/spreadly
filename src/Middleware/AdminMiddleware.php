<?php

namespace App\Middleware;

use App\Models\CampaignAdmin;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AdminMiddleware implements MiddlewareInterface
{
    private CampaignAdmin $campaignAdminModel;

    public function __construct(CampaignAdmin $campaignAdminModel)
    {
        $this->campaignAdminModel = $campaignAdminModel;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['campaign_id'])) {
            $response = new Response();
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        if (!$this->campaignAdminModel->isAdmin($_SESSION['user_id'], $_SESSION['campaign_id'])) {
            $response = new Response();
            return $response
                ->withHeader('Location', '/')
                ->withStatus(302);
        }

        return $handler->handle($request);
    }
}
