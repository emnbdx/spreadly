<?php

namespace App\Services;

use Postal\Client;
use Postal\Send\Message;

class EmailService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function sendLoginCode(string $email, string $code): bool
    {
        return $this->send(
            $email,
            'Votre code de connexion pour Spreadly',
            "Votre code de connexion est : $code\n\nCe code expirera dans 1 heure.",
            '<h3>Votre code de connexion</h3><p>Votre code de connexion est : <strong>'
                . htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
                . '</strong></p><p>Ce code expirera dans 1 heure.</p>'
        );
    }

    public function sendLoveMessages(array $user, array $loves, string $theme = 'christmas'): bool
    {
        if (empty($loves)) {
            return true;
        }

        return $this->send(
            $user['email'],
            $this->config['mail_subject'],
            null,
            $this->generateEmailContent($loves, $theme)
        );
    }

    private function send(string $to, string $subject, ?string $plainBody, string $htmlBody): bool
    {
        $baseUrl = rtrim((string) ($this->config['postal_api_url'] ?? ''), '/');
        $apiKey = (string) ($this->config['postal_api_key'] ?? '');

        if ($baseUrl === '' || $apiKey === '') {
            error_log('Postal non configuré (PostalApiUrl / PostalApiKey)');
            return false;
        }

        try {
            $client = new Client($baseUrl, $apiKey);
            $message = new Message();
            $message->to($to);
            $message->from($this->formatFrom());
            $message->subject($subject);
            $message->htmlBody($htmlBody);

            if ($plainBody !== null) {
                $message->plainBody($plainBody);
            }

            $client->send->message($message);
            return true;
        } catch (\Throwable $e) {
            error_log('Erreur envoi email Postal: ' . $e->getMessage());
            return false;
        }
    }

    private function formatFrom(): string
    {
        $email = (string) ($this->config['mail_from_email'] ?? '');
        $name = trim((string) ($this->config['mail_from_name'] ?? ''));

        if ($name === '') {
            return $email;
        }

        $quoted = '"' . addcslashes($name, '"\\') . '"';
        return $quoted . ' <' . $email . '>';
    }

    public function generateEmailContent(array $loves, string $theme): string
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

    public function getThemeConfig(string $theme): array
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
