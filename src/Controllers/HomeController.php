<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\CampaignUser;
use App\Models\Love;
use App\Models\Campaign;
use App\Models\CampaignAdmin;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;
use DateTime;

class HomeController
{
    private User $userModel;
    private CampaignUser $campaignUserModel;
    private Love $loveModel;
    private Campaign $campaignModel;
    private CampaignAdmin $campaignAdminModel;
    private Twig $view;
    private array $config;

    public function __construct(User $userModel, CampaignUser $campaignUserModel, Love $loveModel, Campaign $campaignModel, CampaignAdmin $campaignAdminModel, Twig $view, array $config)
    {
        $this->userModel = $userModel;
        $this->campaignUserModel = $campaignUserModel;
        $this->loveModel = $loveModel;
        $this->campaignModel = $campaignModel;
        $this->campaignAdminModel = $campaignAdminModel;
        $this->view = $view;
        $this->config = $config;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $campaign = $this->campaignModel->findById($_SESSION['campaign_id']);
        if (!$campaign) {
            $_SESSION['error'] = 'Spreadly non trouvé';
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        $campaignEndDate = $this->campaignModel->getEndDate($_SESSION['campaign_id']);
        if (!$campaignEndDate) {
            $_SESSION['error'] = 'Spreadly terminé';
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        $endDate = new DateTime($campaignEndDate);
        $isEnded = $endDate < new DateTime('NOW');

        $receivers = [];
        $senderMessages = [];
        if (!$isEnded) {
            $receivers = $this->campaignUserModel->findReceivers($_SESSION['campaign_id']);
            $senderMessages = $this->loveModel->findBySender($_SESSION['user_id'], $_SESSION['campaign_id']);
        }

        $user = $this->userModel->findById($_SESSION['user_id']);
        $isAdmin = $this->campaignAdminModel->isAdmin($_SESSION['user_id'], $_SESSION['campaign_id']);

        // Créer un tableau associatif des messages par destinataire
        $messagesByReceiver = [];
        foreach ($senderMessages as $message) {
            $messagesByReceiver[$message['id_receiver']] = $message;
        }

        $data = [
            'user' => $user,
            'campaign' => $campaign,
            'receivers' => $receivers,
            'is_ended' => $isEnded,
            'is_admin' => $isAdmin,
            'success' => $_SESSION['success'] ?? null,
            'error' => $_SESSION['error'] ?? null,
            'messages_by_receiver' => $messagesByReceiver
        ];

        // Clean up session variables after reading them
        unset($_SESSION['success'], $_SESSION['error']);

        return $this->view->render($response, 'home.twig', $data);
    }

    public function sendLove(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $campaignEndDate = $this->campaignModel->getEndDate($_SESSION['campaign_id']);
        if (!$campaignEndDate) {
            $_SESSION['error'] = 'Spreadly non trouvé';
            return $response
                ->withHeader('Location', '/home')
                ->withStatus(302);
        }

        $endDate = new DateTime($campaignEndDate);
        if ($endDate < new DateTime('NOW')) {
            $_SESSION['error'] = 'La période de soumission est terminée';
            return $response
                ->withHeader('Location', '/home')
                ->withStatus(302);
        }

        $data = $request->getParsedBody();
        $receiverId = (int) ($data['to'] ?? 0);
        $message = trim($data['message'] ?? '');
        $messageId = (int) ($data['message_id'] ?? 0);

        if (empty($receiverId) || empty($message)) {
            $_SESSION['error'] = 'Tous les champs sont requis';
            return $response
                ->withHeader('Location', '/home')
                ->withStatus(302);
        }

        $receiver = $this->userModel->findById($receiverId);
        $campaignUser = $this->campaignUserModel->findByCampaignAndUser($_SESSION['campaign_id'], $receiverId);
        if (!$receiver || !$campaignUser || !$campaignUser['receiver']) {
            $_SESSION['error'] = 'Destinataire invalide';
            return $response
                ->withHeader('Location', '/home')
                ->withStatus(302);
        }

        $senderId = $_SESSION['user_id'];
        $senderName = $_SESSION['user_name'];

        try {
            if ($messageId > 0) {
                // Modification d'un message existant
                $existingMessage = $this->loveModel->findByReceiverAndSender($receiverId, $senderId, $_SESSION['campaign_id']);
                if ($existingMessage && $existingMessage['id'] == $messageId) {
                    $this->loveModel->update($messageId, $message);
                    unset($_SESSION['error']);
                    $_SESSION['success'] = 'Message modifié avec succès';
                } else {
                    $_SESSION['error'] = 'Message non trouvé ou non autorisé';
                }
            } else {
                // Vérifier s'il existe déjà un message pour ce destinataire
                $existingMessage = $this->loveModel->findByReceiverAndSender($receiverId, $senderId, $_SESSION['campaign_id']);
                if ($existingMessage) {
                    $_SESSION['error'] = 'Vous avez déjà envoyé un message à cette personne';
                } else {
                    // Création d'un nouveau message
                    $this->loveModel->create($_SESSION['campaign_id'], $receiverId, $senderName, $message, $senderId);
                    unset($_SESSION['error']);
                    $_SESSION['success'] = 'Merci à toi d\'envoyer du love';
                }
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erreur lors de l\'envoi : ' . $e->getMessage();
        }

        return $response
            ->withHeader('Location', '/home')
            ->withStatus(302);
    }

    public function deleteMessage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $messageId = (int) $request->getAttribute('id');

        try {
            // Vérifier que le message appartient bien à l'utilisateur connecté
            $senderMessages = $this->loveModel->findBySender($_SESSION['user_id'], $_SESSION['campaign_id']);
            $messageExists = false;
            foreach ($senderMessages as $msg) {
                if ($msg['id'] == $messageId) {
                    $messageExists = true;
                    break;
                }
            }

            if ($messageExists) {
                $this->loveModel->delete($messageId);
                $_SESSION['success'] = 'Message supprimé avec succès';
            } else {
                $_SESSION['error'] = 'Message non trouvé ou non autorisé';
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erreur lors de la suppression : ' . $e->getMessage();
        }

        return $response
            ->withHeader('Location', '/home')
            ->withStatus(302);
    }
}
