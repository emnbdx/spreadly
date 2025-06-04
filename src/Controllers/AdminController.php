<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\CampaignAdmin;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

class AdminController
{
    private User $userModel;
    private CampaignAdmin $campaignAdminModel;
    private Twig $view;

    public function __construct(User $userModel, CampaignAdmin $campaignAdminModel, Twig $view)
    {
        $this->userModel = $userModel;
        $this->campaignAdminModel = $campaignAdminModel;
        $this->view = $view;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $users = $this->userModel->findAll($_SESSION['campaign_id']);
        $currentUser = $this->userModel->findById($_SESSION['user_id']);

        // Ajouter le statut admin pour chaque utilisateur
        foreach ($users as &$user) {
            $user['is_admin'] = $this->campaignAdminModel->isAdmin($user['id'], $_SESSION['campaign_id']);
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

        $existingUser = $this->userModel->findByEmail($email, $_SESSION['campaign_id']);
        if ($existingUser) {
            unset($_SESSION['success']);
            $_SESSION['error'] = 'Un utilisateur avec cet email existe déjà dans ce Spreadly';
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }

        try {
            $userId = $this->userModel->create($_SESSION['campaign_id'], $name, $email, $receiver);

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
        $userId = (int) $request->getAttribute('id');
        $user = $this->userModel->findById($userId);

        if (!$user) {
            $_SESSION['error'] = 'Utilisateur non trouvé';
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }

        // Vérifier si l'utilisateur est admin de cette Spreadly
        $isAdmin = $this->campaignAdminModel->isAdmin($userId, $_SESSION['campaign_id']);

        $data = [
            'user' => $user,
            'is_admin' => $isAdmin,
            'error' => $_SESSION['error'] ?? null
        ];

        unset($_SESSION['error']);

        return $this->view->render($response, 'admin-edit.twig', $data);
    }

    public function updateUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (int) $request->getAttribute('id');
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $receiver = isset($data['receiver']);
        $admin = isset($data['admin']);

        if (empty($name) || empty($email)) {
            $_SESSION['error'] = 'Le nom et l\'email sont requis';
            return $response
                ->withHeader('Location', "/admin/edit/{$userId}")
                ->withStatus(302);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Format d\'email invalide';
            return $response
                ->withHeader('Location', "/admin/edit/{$userId}")
                ->withStatus(302);
        }

        $existingUser = $this->userModel->findByEmail($email, $_SESSION['campaign_id']);
        if ($existingUser && $existingUser['id'] != $userId) {
            $_SESSION['error'] = 'Un autre utilisateur avec cet email existe déjà dans ce Spreadly';
            return $response
                ->withHeader('Location', "/admin/edit/{$userId}")
                ->withStatus(302);
        }

        try {
            $this->userModel->update($userId, $name, $email, $receiver);

            // Gérer le statut admin
            $currentlyAdmin = $this->campaignAdminModel->isAdmin($userId, $_SESSION['campaign_id']);
            if ($admin && !$currentlyAdmin) {
                $this->campaignAdminModel->addAdmin($userId, $_SESSION['campaign_id']);
            } elseif (!$admin && $currentlyAdmin) {
                $this->campaignAdminModel->removeAdmin($userId, $_SESSION['campaign_id']);
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
        $userId = (int) $request->getAttribute('id');

        if ($userId == $_SESSION['user_id']) {
            $_SESSION['error'] = 'Vous ne pouvez pas supprimer votre propre compte';
            return $response
                ->withHeader('Location', '/admin')
                ->withStatus(302);
        }

        try {
            $this->userModel->delete($userId);
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
                    'receiver' => isset($data[2]) ? filter_var($data[2], FILTER_VALIDATE_BOOLEAN) : true
                ];
            }

            if (empty($users)) {
                $_SESSION['error'] = 'Aucun utilisateur valide trouvé dans le fichier CSV';
                return $response
                    ->withHeader('Location', '/admin')
                    ->withStatus(302);
            }

            $results = $this->userModel->createBatch($_SESSION['campaign_id'], $users);

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
}
