<?php

namespace App\Controllers;

use App\Models\Campaign;
use App\Models\User;
use App\Models\CampaignAdmin;
use App\Services\EmailService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

class OnboardingController
{
    private Campaign $campaignModel;
    private User $userModel;
    private CampaignAdmin $campaignAdminModel;
    private EmailService $emailService;
    private Twig $view;

    public function __construct(
        Campaign $campaignModel,
        User $userModel,
        CampaignAdmin $campaignAdminModel,
        EmailService $emailService,
        Twig $view
    ) {
        $this->campaignModel = $campaignModel;
        $this->userModel = $userModel;
        $this->campaignAdminModel = $campaignAdminModel;
        $this->emailService = $emailService;
        $this->view = $view;
    }

    public function showCreate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $isLoggedIn = isset($_SESSION['user_id']);

        $data = [
            'error' => $_SESSION['error'] ?? null,
            'success' => $_SESSION['success'] ?? null,
            'campaign_name' => $_SESSION['onboarding_campaign_name'] ?? '',
            'start_date' => $_SESSION['onboarding_start_date'] ?? '',
            'end_date' => $_SESSION['onboarding_end_date'] ?? '',
            'user_name' => $_SESSION['onboarding_user_name'] ?? ($_SESSION['user_name'] ?? ''),
            'user_email' => $_SESSION['onboarding_user_email'] ?? ($_SESSION['user_email'] ?? ''),
            'is_logged_in' => $isLoggedIn
        ];

        unset(
            $_SESSION['error'],
            $_SESSION['success'],
            $_SESSION['onboarding_campaign_name'],
            $_SESSION['onboarding_start_date'],
            $_SESSION['onboarding_end_date'],
            $_SESSION['onboarding_user_name'],
            $_SESSION['onboarding_user_email']
        );

        return $this->view->render($response, 'onboarding.twig', $data);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $isLoggedIn = isset($_SESSION['user_id']);

        // Données de la Spreadly
        $campaignName = trim($data['campaign_name'] ?? '');
        $startDate = trim($data['start_date'] ?? '');
        $endDate = trim($data['end_date'] ?? '');
        $theme = trim($data['theme'] ?? 'christmas');

        // Données de l'utilisateur
        if ($isLoggedIn) {
            // Utilisateur connecté : utiliser ses infos existantes
            $userName = $_SESSION['user_name'];
            $userEmail = $_SESSION['user_email'];
        } else {
            // Nouvel utilisateur : récupérer depuis le formulaire
            $userName = trim($data['user_name'] ?? '');
            $userEmail = trim($data['user_email'] ?? '');
        }

        // Validation
        if (empty($campaignName) || empty($startDate) || empty($endDate)) {
            $_SESSION['error'] = 'Le nom du Spreadly, la date de début et la date de fin sont requis';
            $this->saveFormData($data);
            return $response->withHeader('Location', '/create-spreadly')->withStatus(302);
        }

        if (!$isLoggedIn && (empty($userName) || empty($userEmail))) {
            $_SESSION['error'] = 'Votre nom et email sont requis';
            $this->saveFormData($data);
            return $response->withHeader('Location', '/create-spreadly')->withStatus(302);
        }

        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Format d\'email invalide';
            $this->saveFormData($data);
            return $response->withHeader('Location', '/create-spreadly')->withStatus(302);
        }

        if (strtotime($startDate) >= strtotime($endDate)) {
            $_SESSION['error'] = 'La date de fin doit être postérieure à la date de début';
            $this->saveFormData($data);
            return $response->withHeader('Location', '/create-spreadly')->withStatus(302);
        }

        // Vérifier si l'email existe déjà (seulement pour les nouveaux utilisateurs)
        if (!$isLoggedIn) {
            $existingUsers = $this->userModel->findAllCampaignsByEmail($userEmail);
            if (!empty($existingUsers)) {
                $_SESSION['error'] = 'Un compte avec cet email existe déjà. Utilisez la connexion.';
                $this->saveFormData($data);
                return $response->withHeader('Location', '/create-spreadly')->withStatus(302);
            }
        }

        try {
            // Créer la Spreadly
            $slug = $this->campaignModel->generateSlug($campaignName);
            $campaignId = $this->campaignModel->create($campaignName, $slug, $startDate, $endDate, [
                'theme' => $theme,
                'mail_subject' => "Messages d'amour - $campaignName",
                'is_active' => true
            ]);

            if ($isLoggedIn) {
                // Utilisateur connecté : créer son profil dans cette nouvelle Spreadly
                $userId = $this->userModel->create($campaignId, $userName, $userEmail, false);

                // Ajouter comme admin de cette Spreadly
                $this->campaignAdminModel->addAdmin($userId, $campaignId);

                // Mettre à jour la session pour basculer sur cette nouvelle Spreadly
                $_SESSION['user_id'] = $userId;
                $_SESSION['campaign_id'] = $campaignId;
                $_SESSION['campaign_name'] = $campaignName;
                $_SESSION['campaign_slug'] = $slug;

                $_SESSION['success'] = 'Votre nouveau Spreadly a été créé avec succès !';
                return $response->withHeader('Location', '/')->withStatus(302);
            } else {
                // Nouvel utilisateur : créer le compte et envoyer le code
                $userId = $this->userModel->create($campaignId, $userName, $userEmail, false);

                // Ajouter comme admin de cette Spreadly
                $this->campaignAdminModel->addAdmin($userId, $campaignId);

                // Générer et envoyer le code de connexion
                $code = $this->userModel->generateLoginCode($userId);

                if ($this->emailService->sendLoginCode($userEmail, $code)) {
                    $_SESSION['success'] = 'Votre Spreadly a été créé ! Un code de connexion vous a été envoyé par email.';
                    $_SESSION['login_email'] = $userEmail;
                    $_SESSION['code_sent'] = true;

                    return $response->withHeader('Location', '/login')->withStatus(302);
                } else {
                    $_SESSION['error'] = 'Spreadly crée mais échec de l\'envoi de l\'email. Utilisez la connexion.';
                    return $response->withHeader('Location', '/login')->withStatus(302);
                }
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erreur lors de la création : ' . $e->getMessage();
            $this->saveFormData($data);
            return $response->withHeader('Location', '/create-spreadly')->withStatus(302);
        }
    }

    private function saveFormData(array $data): void
    {
        $_SESSION['onboarding_campaign_name'] = $data['campaign_name'] ?? '';
        $_SESSION['onboarding_start_date'] = $data['start_date'] ?? '';
        $_SESSION['onboarding_end_date'] = $data['end_date'] ?? '';
        $_SESSION['onboarding_user_name'] = $data['user_name'] ?? '';
        $_SESSION['onboarding_user_email'] = $data['user_email'] ?? '';
    }
}
