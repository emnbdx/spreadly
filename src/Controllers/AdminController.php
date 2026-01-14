<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\CampaignUser;
use App\Models\Love;
use App\Models\Campaign;
use App\Models\CampaignAdmin;
use App\Services\EmailService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

class AdminController
{
    private User $userModel;
    private CampaignUser $campaignUserModel;
    private Love $loveModel;
    private Campaign $campaignModel;
    private CampaignAdmin $campaignAdminModel;
    private EmailService $emailService;
    private Twig $view;

    public function __construct(User $userModel, CampaignUser $campaignUserModel, Love $loveModel, Campaign $campaignModel, CampaignAdmin $campaignAdminModel, EmailService $emailService, Twig $view)
    {
        $this->userModel = $userModel;
        $this->campaignUserModel = $campaignUserModel;
        $this->loveModel = $loveModel;
        $this->campaignModel = $campaignModel;
        $this->campaignAdminModel = $campaignAdminModel;
        $this->emailService = $emailService;
        $this->view = $view;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $users = $this->campaignUserModel->findByCampaign($_SESSION['campaign_id']);
        $currentUser = $this->userModel->findById($_SESSION['user_id']);

        // Ajouter le statut admin pour chaque utilisateur
        foreach ($users as &$user) {
            $user['is_admin'] = $this->campaignAdminModel->isAdmin($user['user_id'], $_SESSION['campaign_id']);
        }

        $data = [
            'users' => $users,
            'current_user' => $currentUser,
            'success' => $_SESSION['success'] ?? null,
            'error' => $_SESSION['error'] ?? null
        ];

        // Clean up session variables after reading them
        unset($_SESSION['success'], $_SESSION['error']);

        return $this->view->render($response, 'admin.twig', $data);
    }

    public function createUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $receiver = isset($data['receiver']);
        $admin = isset($data['admin']);

        if (empty($name) || empty($email)) {
            unset($_SESSION['success']);
            $_SESSION['error'] = 'Le nom et l\'email sont requis';
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            unset($_SESSION['success']);
            $_SESSION['error'] = 'Format d\'email invalide';
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }

        $existingUser = $this->campaignUserModel->findByEmailAndCampaign($email, $_SESSION['campaign_id']);
        if ($existingUser) {
            unset($_SESSION['success']);
            $_SESSION['error'] = 'Un utilisateur avec cet email existe déjà dans ce Spreadly';
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }

        try {
            $user = $this->userModel->findByEmail($email);
            if (!$user) {
                $userId = $this->userModel->create($name, $email);
            } else {
                $userId = $user['id'];
            }

            $campaignUserId = $this->campaignUserModel->create($_SESSION['campaign_id'], $userId, $receiver);

            // Ajouter comme admin si demandé
            if ($admin) {
                $this->campaignAdminModel->addAdmin($userId, $_SESSION['campaign_id']);
            }

            unset($_SESSION['error']);
            $_SESSION['success'] = 'Utilisateur créé avec succès';
        } catch (\Exception $e) {
            unset($_SESSION['success']);
            $_SESSION['error'] = 'Échec de la création de l\'utilisateur : ' . $e->getMessage();
        }

        return $response
            ->withHeader('Location', '/admin')
            ->withStatus(302);
    }

    public function editUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $campaignUserId = (int) $request->getAttribute('id');
        $campaignUser = $this->campaignUserModel->findById($campaignUserId);

        if (!$campaignUser || $campaignUser['campaign_id'] != $_SESSION['campaign_id']) {
            $_SESSION['error'] = 'Utilisateur non trouvé';
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }

        // Vérifier si l'utilisateur est admin de cette Spreadly
        $isAdmin = $this->campaignAdminModel->isAdmin($campaignUser['user_id'], $_SESSION['campaign_id']);

        $data = [
            'user' => $campaignUser,
            'is_admin' => $isAdmin,
            'error' => $_SESSION['error'] ?? null
        ];

        unset($_SESSION['error']);

        return $this->view->render($response, 'admin-edit.twig', $data);
    }

    public function updateUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $campaignUserId = (int) $request->getAttribute('id');
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $receiver = isset($data['receiver']);
        $admin = isset($data['admin']);

        if (empty($name) || empty($email)) {
            $_SESSION['error'] = 'Le nom et l\'email sont requis';
            return $response
                ->withHeader('Location', "/admin/edit/{$campaignUserId}")
                ->withStatus(302);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Format d\'email invalide';
            return $response
                ->withHeader('Location', "/admin/edit/{$campaignUserId}")
                ->withStatus(302);
        }

        $campaignUser = $this->campaignUserModel->findById($campaignUserId);
        if (!$campaignUser || $campaignUser['campaign_id'] != $_SESSION['campaign_id']) {
            $_SESSION['error'] = 'Utilisateur non trouvé';
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }

        $existingUser = $this->campaignUserModel->findByEmailAndCampaign($email, $_SESSION['campaign_id']);
        if ($existingUser && $existingUser['user_id'] != $campaignUser['user_id']) {
            $_SESSION['error'] = 'Un autre utilisateur avec cet email existe déjà dans ce Spreadly';
            return $response
                ->withHeader('Location', "/admin/edit/{$campaignUserId}")
                ->withStatus(302);
        }

        try {
            $this->userModel->update($campaignUser['user_id'], $name, $email);
            $this->campaignUserModel->update($campaignUser['id'], $receiver);

            // Gérer le statut admin
            $currentlyAdmin = $this->campaignAdminModel->isAdmin($campaignUser['user_id'], $_SESSION['campaign_id']);
            if ($admin && !$currentlyAdmin) {
                $this->campaignAdminModel->addAdmin($campaignUser['user_id'], $_SESSION['campaign_id']);
            } elseif (!$admin && $currentlyAdmin) {
                $this->campaignAdminModel->removeAdmin($campaignUser['user_id'], $_SESSION['campaign_id']);
            }

            $_SESSION['success'] = 'Utilisateur modifié avec succès';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Échec de la modification : ' . $e->getMessage();
        }

        return $response
            ->withHeader('Location', '/admin')
            ->withStatus(302);
    }

    public function deleteUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $campaignUserId = (int) $request->getAttribute('id');
        $campaignUser = $this->campaignUserModel->findById($campaignUserId);

        if (!$campaignUser || $campaignUser['campaign_id'] != $_SESSION['campaign_id']) {
            $_SESSION['error'] = 'Utilisateur non trouvé';
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }

        if ($campaignUser['user_id'] == $_SESSION['user_id']) {
            $_SESSION['error'] = 'Vous ne pouvez pas supprimer votre propre compte';
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }

        try {
            $this->campaignUserModel->delete($campaignUser['id']);
            $_SESSION['success'] = 'Utilisateur supprimé avec succès';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Échec de la suppression : ' . $e->getMessage();
        }

        return $response
            ->withHeader('Location', '/admin')
            ->withStatus(302);
    }

    public function importUsers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $uploadedFiles = $request->getUploadedFiles();

        if (!isset($uploadedFiles['csv_file']) || $uploadedFiles['csv_file']->getError() !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Erreur lors du téléchargement du fichier CSV';
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }

        $csvFile = $uploadedFiles['csv_file'];

        if (
            $csvFile->getClientMediaType() !== 'text/csv' &&
            !str_ends_with($csvFile->getClientFilename(), '.csv')
        ) {
            $_SESSION['error'] = 'Le fichier doit être au format CSV';
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }

        try {
            $csvContent = $csvFile->getStream()->getContents();
            $lines = str_getcsv($csvContent, "\n");
            $users = [];

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;

                $data = str_getcsv($line);
                if (count($data) < 2) continue;

                $users[] = [
                    'name' => trim($data[0] ?? ''),
                    'email' => trim($data[1] ?? ''),
                    'receiver' => isset($data[2]) ? filter_var($data[2], FILTER_VALIDATE_BOOLEAN) : true,
                    'admin' => isset($data[3]) ? filter_var($data[3], FILTER_VALIDATE_BOOLEAN) : false
                ];
            }

            if (empty($users)) {
                $_SESSION['error'] = 'Aucun utilisateur valide trouvé dans le fichier CSV';
                return $response
                    ->withHeader('Location', '/admin')
                    ->withStatus(302);
            }

            $results = $this->campaignUserModel->createBatch($_SESSION['campaign_id'], $users);

            foreach ($results as $index => $result) {
                if ($result['status'] === 'created' && !empty($users[$index]['admin']) && $users[$index]['admin']) {
                    $userRecord = $this->userModel->findByEmail($users[$index]['email']);
                    if ($userRecord) {
                        $this->campaignAdminModel->addAdmin($userRecord['id'], $_SESSION['campaign_id']);
                    }
                }
            }

            $created = count(array_filter($results, fn($r) => $r['status'] === 'created'));
            $skipped = count(array_filter($results, fn($r) => $r['status'] === 'skipped'));
            $errors = count(array_filter($results, fn($r) => $r['status'] === 'error'));

            $_SESSION['success'] = "Import terminé : {$created} créés, {$skipped} ignorés, {$errors} erreurs";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erreur lors de l\'import : ' . $e->getMessage();
        }

        return $response
            ->withHeader('Location', '/admin')
            ->withStatus(302);
    }

    public function sendEmails(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $campaignId = $_SESSION['campaign_id'];
        $campaign = $this->campaignModel->findById($campaignId);

        if (!$campaign) {
            $_SESSION['error'] = 'Spreadly non trouvé';
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }

        try {
            $receivers = $this->campaignUserModel->findReceivers($campaignId);
            $sentCount = 0;
            $errorCount = 0;

            foreach ($receivers as $receiver) {
                $loves = $this->loveModel->findByReceiver($receiver['user_id'], $campaignId);

                if (empty($loves)) {
                    continue;
                }

                $emailService = new \App\Services\EmailService($this->getEmailConfig($campaign));

                if ($emailService->sendLoveMessages($receiver, $loves, $campaign['theme'])) {
                    $sentCount++;
                } else {
                    $errorCount++;
                }
            }

            if ($sentCount > 0) {
                $_SESSION['success'] = "$sentCount email(s) envoyé(s) avec succès" . ($errorCount > 0 ? " ($errorCount erreur(s))" : "");
            } else {
                $_SESSION['error'] = $errorCount > 0 ? "Échec de l'envoi des emails" : "Aucun message à envoyer";
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erreur lors de l\'envoi : ' . $e->getMessage();
        }

        return $response
            ->withHeader('Location', '/admin')
            ->withStatus(302);
    }

    public function printEmail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $campaignId = $_SESSION['campaign_id'];
        $campaign = $this->campaignModel->findById($campaignId);

        if (!$campaign) {
            $_SESSION['error'] = 'Spreadly non trouvé';
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }

        try {
            $allLoves = $this->loveModel->findByCampaign($campaignId);

            if (empty($allLoves)) {
                $_SESSION['error'] = 'Aucun message trouvé pour la prévisualisation';
                return $response
                    ->withHeader('Location', '/admin')
                    ->withStatus(302);
            }

            $firstReceiverId = $allLoves[0]['id_receiver'];
            $loves = array_filter($allLoves, function ($love) use ($firstReceiverId) {
                return $love['id_receiver'] == $firstReceiverId;
            });

            $emailService = new \App\Services\EmailService($this->getEmailConfig($campaign));
            $htmlContent = $emailService->generateEmailContent(array_reverse($loves), $campaign['theme']);

            $data = [
                'html_content' => $htmlContent
            ];

            return $this->view->render($response, 'admin-print.twig', $data);
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erreur lors de la génération de la prévisualisation : ' . $e->getMessage();
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }
    }

    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $campaignId = $_SESSION['campaign_id'];
        $campaign = $this->campaignModel->findById($campaignId);
        $currentUser = $this->userModel->findById($_SESSION['user_id']);

        if (!$campaign) {
            $_SESSION['error'] = 'Spreadly non trouvé';
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }

        $totalMessages = $this->loveModel->getTotalMessagesByCampaign($campaignId);
        $messagesByDay = $this->loveModel->getMessagesByDay($campaignId);
        $topSenders = $this->loveModel->getTopSenders($campaignId, 1);
        $topReceivers = $this->loveModel->getTopReceivers($campaignId, 1);

        $topContribCount = !empty($topSenders) ? $topSenders[0]['message_count'] : 0;
        $topReceiverCount = !empty($topReceivers) ? $topReceivers[0]['message_count'] : 0;

        $data = [
            'campaign' => $campaign,
            'current_user' => $currentUser,
            'total_messages' => $totalMessages,
            'messages_by_day' => $messagesByDay,
            'top_contrib_count' => $topContribCount,
            'top_receiver_count' => $topReceiverCount
        ];

        return $this->view->render($response, 'admin-stats.twig', $data);
    }

    private function getEmailConfig(array $campaign): array
    {
        $config = require __DIR__ . '/../../config/config.php';
        $emailConfig = $config['email'];

        $emailConfig['mail_subject'] = $campaign['mail_subject'] ?: $emailConfig['mail_subject'];

        return $emailConfig;
    }
}
