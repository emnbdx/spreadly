<?php

namespace App\Models;

use PDO;

class Love
{
    private PDO $db;
    private string $tablePrefix;

    public function __construct(PDO $db, string $tablePrefix = '')
    {
        $this->db = $db;
        $this->tablePrefix = $tablePrefix;
    }

    public function findByReceiver(int $receiverId, ?int $campaignId = null): array
    {
        if ($campaignId !== null) {
            $stmt = $this->db->prepare("
                SELECT l.*, u.name as sender_name
                FROM {$this->tablePrefix}love l
                LEFT JOIN {$this->tablePrefix}user u ON l.id_sender = u.id
                WHERE l.id_receiver = ? AND l.campaign_id = ?
                ORDER BY l.created_at DESC
            ");
            $stmt->execute([$receiverId, $campaignId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT l.*, u.name as sender_name
                FROM {$this->tablePrefix}love l
                LEFT JOIN {$this->tablePrefix}user u ON l.id_sender = u.id
                WHERE l.id_receiver = ?
                ORDER BY l.created_at DESC
            ");
            $stmt->execute([$receiverId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findBySender(int $senderId, ?int $campaignId = null): array
    {
        if ($campaignId !== null) {
            $stmt = $this->db->prepare("
                SELECT l.*, u.name as receiver_name 
                FROM {$this->tablePrefix}love l
                JOIN {$this->tablePrefix}user u ON l.id_receiver = u.id
                WHERE l.id_sender = ? AND l.campaign_id = ?
                ORDER BY l.created_at DESC
            ");
            $stmt->execute([$senderId, $campaignId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT l.*, u.name as receiver_name 
                FROM {$this->tablePrefix}love l
                JOIN {$this->tablePrefix}user u ON l.id_receiver = u.id
                WHERE l.id_sender = ?
                ORDER BY l.created_at DESC
            ");
            $stmt->execute([$senderId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByCampaign(int $campaignId): array
    {
        $stmt = $this->db->prepare("
            SELECT l.*, 
                   ur.name as receiver_name, ur.email as receiver_email,
                   us.name as sender_name, us.email as sender_email
            FROM {$this->tablePrefix}love l
            JOIN {$this->tablePrefix}user ur ON l.id_receiver = ur.id
            LEFT JOIN {$this->tablePrefix}user us ON l.id_sender = us.id
            WHERE l.campaign_id = ?
            ORDER BY l.created_at DESC
        ");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByReceiverAndSender(int $receiverId, int $senderId, ?int $campaignId = null): ?array
    {
        if ($campaignId !== null) {
            $stmt = $this->db->prepare("
                SELECT * FROM {$this->tablePrefix}love 
                WHERE id_receiver = ? AND id_sender = ? AND campaign_id = ?
            ");
            $stmt->execute([$receiverId, $senderId, $campaignId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT * FROM {$this->tablePrefix}love 
                WHERE id_receiver = ? AND id_sender = ?
            ");
            $stmt->execute([$receiverId, $senderId]);
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(int $campaignId, int $receiverId, string $senderName, string $content, ?int $senderId = null): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->tablePrefix}love (campaign_id, id_receiver, sender, content, id_sender) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$campaignId, $receiverId, $senderName, $content, $senderId]);
    }

    public function update(int $id, string $content): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->tablePrefix}love 
            SET content = ? 
            WHERE id = ?
        ");
        return $stmt->execute([$content, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->tablePrefix}love 
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    public function getStatsByCampaign(int $campaignId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_messages,
                COUNT(DISTINCT id_receiver) as unique_receivers,
                COUNT(DISTINCT id_sender) as unique_senders,
                DATE(created_at) as date,
                COUNT(*) as daily_count
            FROM {$this->tablePrefix}love 
            WHERE campaign_id = ?
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalMessagesByCampaign(int $campaignId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM {$this->tablePrefix}love 
            WHERE campaign_id = ?
        ");
        $stmt->execute([$campaignId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['total'] ?? 0);
    }

    public function getMessagesByDay(int $campaignId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as count
            FROM {$this->tablePrefix}love 
            WHERE campaign_id = ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopSenders(int $campaignId, int $limit = 5): array
    {
        $limit = (int) $limit;
        $stmt = $this->db->prepare("
            SELECT 
                u.name,
                u.email,
                COUNT(l.id) as message_count
            FROM {$this->tablePrefix}love l
            LEFT JOIN {$this->tablePrefix}user u ON l.id_sender = u.id
            WHERE l.campaign_id = ? AND l.id_sender IS NOT NULL
            GROUP BY l.id_sender, u.name, u.email
            ORDER BY message_count DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeastActiveSenders(int $campaignId, int $limit = 5): array
    {
        $limit = (int) $limit;
        $stmt = $this->db->prepare("
            SELECT 
                u.name,
                u.email,
                COUNT(l.id) as message_count
            FROM {$this->tablePrefix}love l
            LEFT JOIN {$this->tablePrefix}user u ON l.id_sender = u.id
            WHERE l.campaign_id = ? AND l.id_sender IS NOT NULL
            GROUP BY l.id_sender, u.name, u.email
            ORDER BY message_count ASC
            LIMIT {$limit}
        ");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopReceivers(int $campaignId, int $limit = 1): array
    {
        $limit = (int) $limit;
        $stmt = $this->db->prepare("
            SELECT 
                u.name,
                u.email,
                COUNT(l.id) as message_count
            FROM {$this->tablePrefix}love l
            LEFT JOIN {$this->tablePrefix}user u ON l.id_receiver = u.id
            WHERE l.campaign_id = ?
            GROUP BY l.id_receiver, u.name, u.email
            ORDER BY message_count DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
