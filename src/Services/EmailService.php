<?php

namespace App\Services;

use Mailjet\Client;
use Mailjet\Resources;

class EmailService
{
    private Client $mailjet;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->mailjet = new Client(
            $config['mailjet_public_key'],
            $config['mailjet_private_key'],
            true,
            ['version' => 'v3.1']
        );
    }

    public function sendLoginCode(string $email, string $code): bool
    {
        $body = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => $this->config['mail_from_email'],
                        'Name' => $this->config['mail_from_name']
                    ],
                    'To' => [
                        [
                            'Email' => $email,
                        ],
                    ],
                    'Subject' => 'Votre code de connexion pour Spreadly',
                    'TextPart' => "Votre code de connexion est : $code\n\nCe code expirera dans 1 heure.",
                    'HTMLPart' => "<h3>Votre code de connexion</h3><p>Votre code de connexion est : <strong>$code</strong></p><p>Ce code expirera dans 1 heure.</p>",
                ]
            ]
        ];

        $response = $this->mailjet->post(Resources::$Email, ['body' => $body]);
        return $response->success();
    }

    public function sendLoveMessages(array $user, array $loves): bool
    {
        if (empty($loves)) {
            return true;
        }

        $content = '';
        foreach ($loves as $love) {
            $senderDisplay = $love['sender_name'] ?: $love['sender'];

            $content .= '<p style="font-size:30px;text-align:left;margin:0;padding:0">&ldquo;</p>' .
                '<p style="font-size:16px;text-align:center;margin:0;padding:0"><i>' . nl2br($love['content']) . '</i></p>' .
                '<p style="font-size:30px;text-align:right;margin:0;padding:0">&rdquo;</p>' .
                '<p style="font-size:16px;font-weight:bold;text-align:right;">' . $senderDisplay . '</p>' .
                '<br/><img src="http://ageheureux.a.g.pic.centerblog.net/guirlandes-0048_1.gif" width="100%"/><br/>';
        }

        $body = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => $this->config['mail_from_email'],
                        'Name' => $this->config['mail_from_name']
                    ],
                    'To' => [
                        [
                            'Email' => $user['email'],
                        ],
                    ],
                    'Subject' => $this->config['mail_subject'],
                    'Variables' => [
                        'content' => $content
                    ],
                    'TemplateID' => (int) $this->config['mail_template_id'],
                    'TemplateLanguage' => true,
                ]
            ]
        ];

        $response = $this->mailjet->post(Resources::$Email, ['body' => $body]);
        return $response->success();
    }
}
