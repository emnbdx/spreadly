<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Love;
use App\Services\EmailService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SenderController
{
    private User $userModel;
    private Love $loveModel;
    private EmailService $emailService;

    public function __construct(User $userModel, Love $loveModel, EmailService $emailService)
    {
        $this->userModel = $userModel;
        $this->loveModel = $loveModel;
        $this->emailService = $emailService;
    }

    public function sendEmails(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $output = "Ready to send emails<br/><br/>";

        $receivers = $this->userModel->findReceivers($_SESSION['campaign_id']);

        foreach ($receivers as $receiver) {
            $output .= "Send email to {$receiver['name']} ({$receiver['email']})<br/>";

            $loves = $this->loveModel->findByReceiver($receiver['id']);

            if (empty($loves)) {
                $output .= "Nothing to send :(<br/><br/>";
                continue;
            }

            if ($this->emailService->sendLoveMessages($receiver, $loves)) {
                $output .= "✅ Email sent successfully<br/><br/>";
            } else {
                $output .= "❌ Failed to send email<br/><br/>";
            }
        }

        $response->getBody()->write($output);
        return $response->withHeader('Content-Type', 'text/html');
    }
}
