<?php

namespace App\Controllers;

use App\Models\Campaign;
use App\Models\User;
use App\Models\CampaignUser;
use App\Models\Love;
use App\Models\CampaignAdmin;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

class CampaignController
{
    private Campaign $campaignModel;
    private User $userModel;
    private CampaignUser $campaignUserModel;
    private Love $loveModel;
    private CampaignAdmin $campaignAdminModel;
    private Twig $view;

    public function __construct(Campaign $campaignModel, User $userModel, CampaignUser $campaignUserModel, Love $loveModel, CampaignAdmin $campaignAdminModel, Twig $view)
    {
        $this->campaignModel = $campaignModel;
        $this->userModel = $userModel;
        $this->campaignUserModel = $campaignUserModel;
        $this->loveModel = $loveModel;
        $this->campaignAdminModel = $campaignAdminModel;
        $this->view = $view;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Récupérer toutes les Spreadly de l'utilisateur connecté
        $userCampaigns = $this->campaignUserModel->findByEmail($_SESSION['user_email']);

        $data = [
            'campaigns' => $userCampaigns,
            'current_campaign_id' => $_SESSION['campaign_id'] ?? null,
            'success' => $_SESSION['success'] ?? null,
            'error' => $_SESSION['error'] ?? null
        ];

        unset($_SESSION['success'], $_SESSION['error']);

        return $this->view->render($response, 'campaigns.twig', $data);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUser = $this->userModel->findById($_SESSION['user_id']);

        $data = [
            'current_user' => $currentUser,
            'error' => $_SESSION['error'] ?? null
        ];

        unset($_SESSION['error']);

        return $this->view->render($response, 'campaign-create.twig', $data);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $startDate = trim($data['start_date'] ?? '');
        $endDate = trim($data['end_date'] ?? '');
        $theme = trim($data['theme'] ?? 'christmas');
        $mailSubject = trim($data['mail_subject'] ?? '');
        $isActive = isset($data['is_active']);

        if (empty($name) || empty($startDate) || empty($endDate)) {
            $_SESSION['error'] = 'Le nom, la date de début et la date de fin sont requis';
            return $response
                ->withHeader('Location', '/campaigns/create')
                ->withStatus(302);
        }

        if (strtotime($startDate) >= strtotime($endDate)) {
            $_SESSION['error'] = 'La date de fin doit être postérieure à la date de début';
            return $response
                ->withHeader('Location', '/campaigns/create')
                ->withStatus(302);
        }

        try {
            $slug = $this->campaignModel->generateSlug($name);

            $campaignId = $this->campaignModel->create($name, $slug, $startDate, $endDate, [
                'theme' => $theme,
                'mail_subject' => $mailSubject ?: "Messages d'amour - $name",
                'is_active' => $isActive
            ]);

            // Créer l'utilisateur admin pour cette Spreadly
            $userEmail = $_SESSION['user_email'];
            $userName = $_SESSION['user_name'];

            // Vérifier si l'utilisateur existe déjà dans cette Spreadly
            $existingUser = $this->userModel->findByEmail($userEmail, $campaignId);

            if (!$existingUser) {
                // Créer l'utilisateur dans cette Spreadly
                $userId = $this->userModel->create($campaignId, $userName, $userEmail, false);

                // Ajouter comme admin de cette Spreadly
                $this->campaignAdminModel->addAdmin($userId, $campaignId);

                // Mettre à jour la session avec les infos complètes
                $_SESSION['user_id'] = $userId;
                $_SESSION['campaign_id'] = $campaignId;
                $_SESSION['campaign_name'] = $name;
                $_SESSION['campaign_slug'] = $slug;
            } else {
                // L'utilisateur existe déjà, juste mettre à jour la session
                $_SESSION['user_id'] = $existingUser['id'];
                $_SESSION['campaign_id'] = $campaignId;
                $_SESSION['campaign_name'] = $name;
                $_SESSION['campaign_slug'] = $slug;
            }

            $_SESSION['success'] = 'Spreadly crée avec succès';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erreur lors de la création : ' . $e->getMessage();
        }

        return $response
            ->withHeader('Location', '/')
            ->withStatus(302);
    }

    public function edit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $campaignId = (int) $request->getAttribute('id');
        $campaign = $this->campaignModel->findById($campaignId);

        if (!$campaign) {
            $_SESSION['error'] = 'Spreadly non trouvé';
            return $response
                ->withHeader('Location', '/campaigns')
                ->withStatus(302);
        }

        // Vérifier que l'utilisateur a accès à cette campagne
        $currentUser = $this->userModel->findById($_SESSION['user_id']);
        $userInCampaign = $this->userModel->findByEmail($currentUser['email'], $campaignId);

        if (!$userInCampaign) {
            $_SESSION['error'] = 'Vous n\'avez pas accès à ce Spreadly';
            return $response
                ->withHeader('Location', '/campaigns')
                ->withStatus(302);
        }

        // Vérifier que l'utilisateur est admin de cette campagne
        $isAdmin = $this->campaignAdminModel->isAdmin($userInCampaign['id'], $campaignId);
        if (!$isAdmin) {
            $_SESSION['error'] = 'Vous devez être administrateur pour modifier un Spreadly';
            return $response
                ->withHeader('Location', '/campaigns')
                ->withStatus(302);
        }

        $data = [
            'campaign' => $campaign,
            'current_user' => $currentUser,
            'error' => $_SESSION['error'] ?? null
        ];

        unset($_SESSION['error']);

        return $this->view->render($response, 'campaign-edit.twig', $data);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $campaignId = (int) $request->getAttribute('id');
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $startDate = trim($data['start_date'] ?? '');
        $endDate = trim($data['end_date'] ?? '');
        $theme = trim($data['theme'] ?? 'christmas');
        $mailSubject = trim($data['mail_subject'] ?? '');
        $isActive = isset($data['is_active']);

        if (empty($name) || empty($startDate) || empty($endDate)) {
            $_SESSION['error'] = 'Le nom, la date de début et la date de fin sont requis';
            return $response
                ->withHeader('Location', "/campaigns/edit/{$campaignId}")
                ->withStatus(302);
        }

        if (strtotime($startDate) >= strtotime($endDate)) {
            $_SESSION['error'] = 'La date de fin doit être postérieure à la date de début';
            return $response
                ->withHeader('Location', "/campaigns/edit/{$campaignId}")
                ->withStatus(302);
        }

        try {
            $campaign = $this->campaignModel->findById($campaignId);
            $slug = $campaign['slug']; // Garder le même slug

            $this->campaignModel->update($campaignId, $name, $slug, $startDate, $endDate, [
                'theme' => $theme,
                'mail_subject' => $mailSubject ?: "Messages d'amour - $name",
                'is_active' => $isActive
            ]);

            $_SESSION['success'] = 'Spreadly modifié avec succès';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erreur lors de la modification : ' . $e->getMessage();
        }

        return $response
            ->withHeader('Location', '/campaigns')
            ->withStatus(302);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $campaignId = (int) $request->getAttribute('id');

        // Vérifier que la campagne existe
        $campaign = $this->campaignModel->findById($campaignId);
        if (!$campaign) {
            $_SESSION['error'] = 'Spreadly non trouvé';
            return $response
                ->withHeader('Location', '/campaigns')
                ->withStatus(302);
        }

        // Vérifier que l'utilisateur a accès à cette campagne
        $currentUser = $this->userModel->findById($_SESSION['user_id']);
        $userInCampaign = $this->userModel->findByEmail($currentUser['email'], $campaignId);

        if (!$userInCampaign) {
            $_SESSION['error'] = 'Vous n\'avez pas accès à ce Spreadly';
            return $response
                ->withHeader('Location', '/campaigns')
                ->withStatus(302);
        }

        // Vérifier que l'utilisateur est admin de cette campagne
        $isAdmin = $this->campaignAdminModel->isAdmin($userInCampaign['id'], $campaignId);
        if (!$isAdmin) {
            $_SESSION['error'] = 'Vous devez être administrateur pour supprimer un Spreadly';
            return $response
                ->withHeader('Location', '/campaigns')
                ->withStatus(302);
        }

        try {
            $this->campaignModel->delete($campaignId);
            $_SESSION['success'] = 'Spreadly supprimé avec succès';

            // Si c'était la campagne actuelle, nettoyer la session
            if ($_SESSION['campaign_id'] == $campaignId) {
                unset($_SESSION['campaign_id'], $_SESSION['campaign_name'], $_SESSION['campaign_slug']);
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erreur lors de la suppression : ' . $e->getMessage();
        }

        return $response
            ->withHeader('Location', '/campaigns')
            ->withStatus(302);
    }

    public function switchCampaign(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $campaignId = (int) $request->getAttribute('id');
        $campaign = $this->campaignModel->findById($campaignId);

        if (!$campaign) {
            $_SESSION['error'] = 'Spreadly non trouvé';
            return $response
                ->withHeader('Location', '/campaigns')
                ->withStatus(302);
        }

        // Vérifier que l'utilisateur existe dans cette Spreadly
        $currentUser = $this->userModel->findById($_SESSION['user_id']);
        $userInCampaign = $this->userModel->findByEmail($currentUser['email'], $campaignId);

        if (!$userInCampaign) {
            $_SESSION['error'] = 'Vous n\'avez pas accès à ce Spreadly';
            return $response
                ->withHeader('Location', '/campaigns')
                ->withStatus(302);
        }

        // Mettre à jour la session avec les infos de la nouvelle Spreadly
        $_SESSION['user_id'] = $userInCampaign['id'];
        $_SESSION['user_name'] = $userInCampaign['name'];
        $_SESSION['user_email'] = $userInCampaign['email'];
        $_SESSION['campaign_id'] = $campaignId;
        $_SESSION['campaign_name'] = $campaign['name'];
        $_SESSION['campaign_slug'] = $campaign['slug'];

        $_SESSION['success'] = "Vous êtes maintenant sur le Spreadly : {$campaign['name']}";

        return $response
            ->withHeader('Location', '/')
            ->withStatus(302);
    }

    public function publicAccess(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $slug = $request->getAttribute('slug');
        $campaign = $this->campaignModel->findBySlug($slug);

        if (!$campaign) {
            $_SESSION['error'] = 'Spreadly non trouvé';
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        // Stocker les infos de la Spreadly dans la session pour après connexion
        $_SESSION['target_campaign_id'] = $campaign['id'];
        $_SESSION['target_campaign_name'] = $campaign['name'];
        $_SESSION['target_campaign_slug'] = $campaign['slug'];

        // Si l'utilisateur est déjà connecté
        if (isset($_SESSION['user_id'])) {
            // Vérifier s'il a accès à cette Spreadly
            $currentUser = $this->userModel->findById($_SESSION['user_id']);
            $userInCampaign = $this->userModel->findByEmail($currentUser['email'], $campaign['id']);

            if ($userInCampaign) {
                // L'utilisateur a accès, basculer sur cette Spreadly
                $_SESSION['user_id'] = $userInCampaign['id'];
                $_SESSION['user_name'] = $userInCampaign['name'];
                $_SESSION['user_email'] = $userInCampaign['email'];
                $_SESSION['campaign_id'] = $campaign['id'];
                $_SESSION['campaign_name'] = $campaign['name'];
                $_SESSION['campaign_slug'] = $campaign['slug'];

                // Nettoyer les variables temporaires
                unset($_SESSION['target_campaign_id'], $_SESSION['target_campaign_name'], $_SESSION['target_campaign_slug']);

                $_SESSION['success'] = "Bienvenue sur le Spreadly : {$campaign['name']}";
                return $response
                    ->withHeader('Location', '/')
                    ->withStatus(302);
            } else {
                // L'utilisateur connecté n'a pas accès à cette Spreadly
                $_SESSION['error'] = "Vous n'avez pas accès à ce Spreadly \"{$campaign['name']}\". Connectez-vous avec un autre compte ou demandez l'accès à l'administrateur.";
                return $response
                    ->withHeader('Location', '/login')
                    ->withStatus(302);
            }
        }

        // Utilisateur non connecté, rediriger vers login avec message
        $_SESSION['info'] = "Pour accéder à ce Spreadly \"{$campaign['name']}\", veuillez vous connecter.";
        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }
}
