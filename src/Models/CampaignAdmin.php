<?php

namespace App\Models;

use PDO;

class CampaignAdmin
{
    private PDO $db;
    private string $tablePrefix;

    public function __construct(PDO $db, string $tablePrefix = '')
    {
        $this->db = $db;
        $this->tablePrefix = $tablePrefix;
    }

    public function isAdmin(int $userId, int $campaignId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->tablePrefix}campaign_admin 
            WHERE user_id = ? AND campaign_id = ?
        ");
        $stmt->execute([$userId, $campaignId]);
        return $stmt->fetchColumn() > 0;
    }

    public function addAdmin(int $userId, int $campaignId): bool
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO {$this->tablePrefix}campaign_admin (user_id, campaign_id) 
            VALUES (?, ?)
        ");
        return $stmt->execute([$userId, $campaignId]);
    }

    public function removeAdmin(int $userId, int $campaignId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->tablePrefix}campaign_admin 
            WHERE user_id = ? AND campaign_id = ?
        ");
        return $stmt->execute([$userId, $campaignId]);
    }

    public function getCampaignAdmins(int $campaignId): array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, ca.created_at as admin_since
            FROM {$this->tablePrefix}campaign_admin ca
            JOIN {$this->tablePrefix}user u ON ca.user_id = u.id
            WHERE ca.campaign_id = ?
            ORDER BY u.name
        ");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserAdminCampaigns(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, ca.created_at as admin_since
            FROM {$this->tablePrefix}campaign_admin ca
            JOIN {$this->tablePrefix}campaign c ON ca.campaign_id = c.id
            WHERE ca.user_id = ?
            ORDER BY c.name
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
