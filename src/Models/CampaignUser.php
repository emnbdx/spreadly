<?php

namespace App\Models;

use PDO;

class CampaignUser
{
    private PDO $db;
    private string $tablePrefix;

    public function __construct(PDO $db, string $tablePrefix = '')
    {
        $this->db = $db;
        $this->tablePrefix = $tablePrefix;
    }

    public function create(int $campaignId, int $userId, bool $receiver = true): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->tablePrefix}campaign_user (campaign_id, user_id, receiver, created_at) 
            VALUES (?, ?, ?, NOW())
        ");

        $stmt->execute([$campaignId, $userId, $receiver ? 1 : 0]);
        return $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT cu.*, u.name, u.email, c.name as campaign_name, c.slug as campaign_slug
            FROM {$this->tablePrefix}campaign_user cu
            JOIN {$this->tablePrefix}user u ON cu.user_id = u.id
            JOIN {$this->tablePrefix}campaign c ON cu.campaign_id = c.id
            WHERE cu.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByCampaignAndUser(int $campaignId, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT cu.*, u.name, u.email, c.name as campaign_name, c.slug as campaign_slug
            FROM {$this->tablePrefix}campaign_user cu
            JOIN {$this->tablePrefix}user u ON cu.user_id = u.id
            JOIN {$this->tablePrefix}campaign c ON cu.campaign_id = c.id
            WHERE cu.campaign_id = ? AND cu.user_id = ?
        ");
        $stmt->execute([$campaignId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByCampaign(int $campaignId): array
    {
        $stmt = $this->db->prepare("
            SELECT cu.*, u.name, u.email, c.name as campaign_name, c.slug as campaign_slug
            FROM {$this->tablePrefix}campaign_user cu
            JOIN {$this->tablePrefix}user u ON cu.user_id = u.id
            JOIN {$this->tablePrefix}campaign c ON cu.campaign_id = c.id
            WHERE cu.campaign_id = ?
            ORDER BY u.name
        ");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findReceivers(int $campaignId): array
    {
        $stmt = $this->db->prepare("
            SELECT cu.*, u.name, u.email, c.name as campaign_name, c.slug as campaign_slug
            FROM {$this->tablePrefix}campaign_user cu
            JOIN {$this->tablePrefix}user u ON cu.user_id = u.id
            JOIN {$this->tablePrefix}campaign c ON cu.campaign_id = c.id
            WHERE cu.campaign_id = ? AND cu.receiver = 1
            ORDER BY u.name
        ");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT cu.*, u.name, u.email, c.name as campaign_name, c.slug as campaign_slug, c.start_date, c.end_date
            FROM {$this->tablePrefix}campaign_user cu
            JOIN {$this->tablePrefix}user u ON cu.user_id = u.id
            JOIN {$this->tablePrefix}campaign c ON cu.campaign_id = c.id
            WHERE cu.user_id = ?
            ORDER BY c.name
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByEmailAndCampaign(string $email, int $campaignId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT cu.*, u.name, u.email, c.name as campaign_name, c.slug as campaign_slug
            FROM {$this->tablePrefix}campaign_user cu
            JOIN {$this->tablePrefix}user u ON cu.user_id = u.id
            JOIN {$this->tablePrefix}campaign c ON cu.campaign_id = c.id
            WHERE u.email = ? AND cu.campaign_id = ?
        ");
        $stmt->execute([$email, $campaignId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByEmail(string $email): array
    {
        $stmt = $this->db->prepare("
            SELECT cu.*, u.name, u.email, c.name as campaign_name, c.slug as campaign_slug, c.start_date, c.end_date
            FROM {$this->tablePrefix}campaign_user cu
            JOIN {$this->tablePrefix}user u ON cu.user_id = u.id
            JOIN {$this->tablePrefix}campaign c ON cu.campaign_id = c.id
            WHERE u.email = ?
            ORDER BY c.name
        ");
        $stmt->execute([$email]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(int $id, bool $receiver): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->tablePrefix}campaign_user 
            SET receiver = ?
            WHERE id = ?
        ");

        return $stmt->execute([$receiver ? 1 : 0, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->tablePrefix}campaign_user 
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }

    public function deleteByCampaignAndUser(int $campaignId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->tablePrefix}campaign_user 
            WHERE campaign_id = ? AND user_id = ?
        ");

        return $stmt->execute([$campaignId, $userId]);
    }

    public function createBatch(int $campaignId, array $users): array
    {
        $results = [];

        foreach ($users as $user) {
            try {
                $existingUser = $this->findByEmailAndCampaign($user['email'], $campaignId);
                if ($existingUser) {
                    $results[] = [
                        'email' => $user['email'],
                        'status' => 'skipped',
                        'message' => 'Email déjà existant dans ce Spreadly'
                    ];
                    continue;
                }

                $userModel = new User($this->db, $this->tablePrefix);
                $existingUserRecord = $userModel->findByEmail($user['email']);

                if ($existingUserRecord) {
                    $userId = $existingUserRecord['id'];
                } else {
                    $userId = $userModel->create($user['name'], $user['email']);
                }

                $id = $this->create($campaignId, $userId, $user['receiver'] ?? true);

                $results[] = [
                    'email' => $user['email'],
                    'status' => 'created',
                    'message' => 'Utilisateur créé avec succès',
                    'id' => $id
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'email' => $user['email'],
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}
