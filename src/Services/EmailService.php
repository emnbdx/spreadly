<?php

namespace App\Services;

use \Brevo\Client\Configuration;
use \Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Model\SendSmtpEmail;
use Brevo\Client\Model\SendSmtpEmailSender;
use Brevo\Client\Model\SendSmtpEmailTo;
use GuzzleHttp\Client;

class EmailService
{
    private TransactionalEmailsApi $apiInstance;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;

        $brevoConfig = Configuration::getDefaultConfiguration()->setApiKey('api-key', $config['brevo_api_key']);
        $this->apiInstance = new TransactionalEmailsApi(
            new Client(),
            $brevoConfig
        );
    }

    public function sendLoginCode(string $email, string $code): bool
    {
        $sendSmtpEmail = new SendSmtpEmail();
        $sendSmtpEmail->setSender(new SendSmtpEmailSender([
            'name' => $this->config['mail_from_name'],
            'email' => $this->config['mail_from_email']
        ]));
        $sendSmtpEmail->setTo([
            new SendSmtpEmailTo(['email' => $email])
        ]);
        $sendSmtpEmail->setSubject('Votre code de connexion pour Spreadly');
        $sendSmtpEmail->setTextContent("Votre code de connexion est : $code\n\nCe code expirera dans 1 heure.");
        $sendSmtpEmail->setHtmlContent("<h3>Votre code de connexion</h3><p>Votre code de connexion est : <strong>$code</strong></p><p>Ce code expirera dans 1 heure.</p>");

        try {
            $result = $this->apiInstance->sendTransacEmail($sendSmtpEmail);
            return true;
        } catch (\Exception $e) {
            error_log('Erreur envoi email: ' . $e->getMessage());
            return false;
        }
    }

    public function sendLoveMessages(array $user, array $loves, string $theme = 'christmas'): bool
    {
        if (empty($loves)) {
            return true;
        }

        $content = $this->generateEmailContent($loves, $theme);

        $sendSmtpEmail = new SendSmtpEmail();
        $sendSmtpEmail->setSender(new SendSmtpEmailSender([
            'name' => $this->config['mail_from_name'],
            'email' => $this->config['mail_from_email']
        ]));
        $sendSmtpEmail->setTo([
            new SendSmtpEmailTo(['email' => $user['email']])
        ]);
        $sendSmtpEmail->setSubject($this->config['mail_subject']);
        $sendSmtpEmail->setHtmlContent($content);

        try {
            $result = $this->apiInstance->sendTransacEmail($sendSmtpEmail);
            return true;
        } catch (\Exception $e) {
            error_log('Erreur envoi email: ' . $e->getMessage());
            return false;
        }
    }

    private function generateEmailContent(array $loves, string $theme): string
    {
        $themeConfig = $this->getThemeConfig($theme);

        $content = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">';
        $content .= '<div style="text-align: center; margin-bottom: 30px;">';
        $content .= '<h1 style="color: ' . $themeConfig['primary_color'] . ';">' . $themeConfig['title'] . '</h1>';
        $content .= '</div>';

        foreach ($loves as $love) {
            $senderDisplay = $love['sender_name'] ?: $love['sender'];

            $content .= '<div style="background: ' . $themeConfig['message_bg'] . '; border-radius: 10px; padding: 20px; margin: 20px 0; border-left: 4px solid ' . $themeConfig['accent_color'] . ';">';
            $content .= '<p style="font-size: 30px; text-align: left; margin: 0; padding: 0; color: ' . $themeConfig['quote_color'] . ';">&ldquo;</p>';
            $content .= '<p style="font-size: 16px; text-align: center; margin: 10px 0; padding: 0; font-style: italic; color: ' . $themeConfig['text_color'] . ';">' . nl2br(htmlspecialchars($love['content'])) . '</p>';
            $content .= '<p style="font-size: 30px; text-align: right; margin: 0; padding: 0; color: ' . $themeConfig['quote_color'] . ';">&rdquo;</p>';
            $content .= '<p style="font-size: 14px; font-weight: bold; text-align: right; color: ' . $themeConfig['accent_color'] . ';">— ' . htmlspecialchars($senderDisplay) . '</p>';
            $content .= '</div>';
        }

        $content .= '<div style="text-align: center; margin-top: 30px; padding: 20px; background: ' . $themeConfig['footer_bg'] . '; border-radius: 10px;">';
        $content .= '<p style="color: ' . $themeConfig['footer_text'] . '; margin: 0;">' . $themeConfig['footer_text'] . '</p>';
        $content .= '</div>';
        $content .= '</div>';

        return $content;
    }

    private function getThemeConfig(string $theme): array
    {
        $themes = [
            'christmas' => [
                'title' => '🎄 Messages de Noël',
                'primary_color' => '#d32f2f',
                'accent_color' => '#388e3c',
                'text_color' => '#333333',
                'quote_color' => '#d32f2f',
                'message_bg' => '#f8f9fa',
                'footer_bg' => '#d32f2f',
                'footer_text' => 'Joyeux Noël ! 🎅'
            ],
            'valentine' => [
                'title' => '💕 Messages d\'amour',
                'primary_color' => '#e91e63',
                'accent_color' => '#f50057',
                'text_color' => '#333333',
                'quote_color' => '#e91e63',
                'message_bg' => '#fff5f8',
                'footer_bg' => '#e91e63',
                'footer_text' => 'Avec tout mon amour 💕'
            ],
            'birthday' => [
                'title' => '🎂 Messages d\'anniversaire',
                'primary_color' => '#ff9800',
                'accent_color' => '#ff5722',
                'text_color' => '#333333',
                'quote_color' => '#ff9800',
                'message_bg' => '#fff8e1',
                'footer_bg' => '#ff9800',
                'footer_text' => 'Joyeux anniversaire ! 🎉'
            ],
            'nature' => [
                'title' => '🌿 Messages nature',
                'primary_color' => '#4caf50',
                'accent_color' => '#8bc34a',
                'text_color' => '#333333',
                'quote_color' => '#4caf50',
                'message_bg' => '#f1f8e9',
                'footer_bg' => '#4caf50',
                'footer_text' => 'Naturellement vôtre 🌿'
            ],
            'party' => [
                'title' => '🎉 Messages de fête',
                'primary_color' => '#9c27b0',
                'accent_color' => '#e91e63',
                'text_color' => '#333333',
                'quote_color' => '#9c27b0',
                'message_bg' => '#f3e5f5',
                'footer_bg' => '#9c27b0',
                'footer_text' => 'Célébrons ensemble ! 🎊'
            ],
            'general' => [
                'title' => '💌 Messages d\'amour',
                'primary_color' => '#2196f3',
                'accent_color' => '#03a9f4',
                'text_color' => '#333333',
                'quote_color' => '#2196f3',
                'message_bg' => '#f5f5f5',
                'footer_bg' => '#2196f3',
                'footer_text' => 'Avec affection 💌'
            ]
        ];

        return $themes[$theme] ?? $themes['general'];
    }
}
