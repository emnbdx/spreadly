<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\CampaignUser;
use App\Services\EmailService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

class AuthController
{
    private User $userModel;
    private CampaignUser $campaignUserModel;
    private EmailService $emailService;
    private Twig $view;

    public function __construct(User $userModel, CampaignUser $campaignUserModel, EmailService $emailService, Twig $view)
    {
        $this->userModel = $userModel;
        $this->campaignUserModel = $campaignUserModel;
        $this->emailService = $emailService;
        $this->view = $view;
    }

    public function showLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (isset($_SESSION['user_id'])) {
            if (isset($_SESSION['campaign_id'])) {
                return $response
                    ->withHeader('Location', '/home')
                    ->withStatus(302);
            }
            return $response
                ->withHeader('Location', '/campaigns')
                ->withStatus(302);
        }

        $data = [
            'error' => $_SESSION['error'] ?? null,
            'success' => $_SESSION['code_sent'] ?? false,
            'email' => $_SESSION['login_email'] ?? null
        ];

        unset($_SESSION['error'], $_SESSION['info']);

        return $this->view->render($response, 'landing.twig', $data);
    }

    public function requestCode(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $email = trim($data['email'] ?? '');

        if (empty($email)) {
            unset($_SESSION['success'], $_SESSION['code_sent'], $_SESSION['login_email']);
            $_SESSION['error'] = 'L\'email est requis';
            return $response
                ->withHeader('Location', '/')
                ->withStatus(302);
        }

        // Vérifier si c'est un utilisateur existant
        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            // Email non trouvé
            unset($_SESSION['success'], $_SESSION['code_sent'], $_SESSION['login_email']);
            $_SESSION['error'] = 'Email non trouvé. Créez d\'abord un Spreadly.';
        } else {
            // Utilisateur existant - utiliser la logique normale
            $code = $this->userModel->generateLoginCode($user['id']);

            if ($this->emailService->sendLoginCode($email, $code)) {
                unset($_SESSION['error']);
                $_SESSION['code_sent'] = true;
                $_SESSION['login_email'] = $email;
            } else {
                unset($_SESSION['success'], $_SESSION['code_sent'], $_SESSION['login_email']);
                $_SESSION['error'] = 'Échec de l\'envoi de l\'email';
            }
        }

        return $response
            ->withHeader('Location', '/')
            ->withStatus(302);
    }

    public function verifyCode(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $email = trim($data['email'] ?? '');
        $code = trim($data['code'] ?? '');

        if (empty($email) || empty($code)) {
            unset($_SESSION['success'], $_SESSION['code_sent'], $_SESSION['login_email']);
            $_SESSION['error'] = 'L\'email et le code sont requis';
            return $response
                ->withHeader('Location', '/')
                ->withStatus(302);
        }

        // Utilisateur existant - utiliser la logique normale
        $user = $this->userModel->validateLoginCode($email, $code);

        if ($user) {
            // Vérifier s'il y a une Spreadly cible (accès via lien public)
            if (isset($_SESSION['target_campaign_id'])) {
                $targetCampaignId = $_SESSION['target_campaign_id'];
                $targetCampaignName = $_SESSION['target_campaign_name'];
                $targetCampaignSlug = $_SESSION['target_campaign_slug'];

                // Vérifier si l'utilisateur a accès à cette Spreadly
                $userInTargetCampaign = $this->campaignUserModel->findByEmailAndCampaign($email, $targetCampaignId);

                if ($userInTargetCampaign) {
                    // L'utilisateur a accès à la Spreadly cible
                    $_SESSION['user_id'] = $userInTargetCampaign['user_id'];
                    $_SESSION['user_name'] = $userInTargetCampaign['name'];
                    $_SESSION['user_email'] = $userInTargetCampaign['email'];
                    $_SESSION['campaign_id'] = $targetCampaignId;
                    $_SESSION['campaign_name'] = $targetCampaignName;
                    $_SESSION['campaign_slug'] = $targetCampaignSlug;

                    // Nettoyer les variables temporaires
                    unset($_SESSION['target_campaign_id'], $_SESSION['target_campaign_name'], $_SESSION['target_campaign_slug']);
                    unset($_SESSION['error'], $_SESSION['code_sent'], $_SESSION['login_email']);

                    $_SESSION['success'] = "Bienvenue sur le Spreadly : {$targetCampaignName}";
                    return $response
                        ->withHeader('Location', '/home')
                        ->withStatus(302);
                } else {
                    // L'utilisateur n'a pas accès à la Spreadly cible
                    unset($_SESSION['target_campaign_id'], $_SESSION['target_campaign_name'], $_SESSION['target_campaign_slug']);
                    $_SESSION['error'] = "Vous n'avez pas accès à ce Spreadly \"{$targetCampaignName}\". Demandez l'accès à l'administrateur.";

                    // Continuer avec la logique normale de connexion
                }
            }

            // Logique normale de connexion (pas de Spreadly cible ou pas d'accès)
            // Trouver toutes les Spreadlys de cet utilisateur
            $userCampaigns = $this->campaignUserModel->findByEmail($email);

            if (count($userCampaigns) === 1) {
                // L'utilisateur n'a qu'une seule Spreadly, se connecter directement
                $campaign = $userCampaigns[0];
                $_SESSION['user_id'] = $campaign['user_id'];
                $_SESSION['user_name'] = $campaign['name'];
                $_SESSION['user_email'] = $campaign['email'];
                $_SESSION['campaign_id'] = $campaign['campaign_id'];
                $_SESSION['campaign_name'] = $campaign['campaign_name'];
                $_SESSION['campaign_slug'] = $campaign['campaign_slug'];

                unset($_SESSION['error'], $_SESSION['code_sent'], $_SESSION['login_email']);

                return $response
                    ->withHeader('Location', '/home')
                    ->withStatus(302);
            } elseif (count($userCampaigns) > 1) {
                // L'utilisateur a plusieurs Spreadlys, stocker les infos de base et rediriger vers la sélection
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $user['name'];

                unset($_SESSION['error'], $_SESSION['code_sent'], $_SESSION['login_email']);

                return $response
                    ->withHeader('Location', '/campaigns')
                    ->withStatus(302);
            } else {
                // L'utilisateur n'a aucune Spreadly, le rediriger vers la création de Spreadly
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['success'] = 'Bienvenue ! Créez votre premier Spreadly pour commencer.';

                unset($_SESSION['error'], $_SESSION['code_sent'], $_SESSION['login_email']);

                return $response
                    ->withHeader('Location', '/create-spreadly')
                    ->withStatus(302);
            }
        }

        unset($_SESSION['success'], $_SESSION['code_sent'], $_SESSION['login_email']);
        $_SESSION['error'] = 'Code invalide ou expiré';
        return $response
            ->withHeader('Location', '/')
            ->withStatus(302);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        session_destroy();

        return $response
            ->withHeader('Location', '/')
            ->withStatus(302);
    }
}
