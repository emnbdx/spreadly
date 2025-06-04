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

    public function findByReceiver(int $receiverId): array
    {
        $stmt = $this->db->prepare("
            SELECT l.*, u.name as sender_name
            FROM {$this->tablePrefix}love l
            LEFT JOIN {$this->tablePrefix}user u ON l.id_sender = u.id
            WHERE l.id_receiver = ?
            ORDER BY l.created_at DESC
        ");
        $stmt->execute([$receiverId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findBySender(int $senderId): array
    {
        $stmt = $this->db->prepare("
            SELECT l.*, u.name as receiver_name 
            FROM {$this->tablePrefix}love l
            JOIN {$this->tablePrefix}user u ON l.id_receiver = u.id
            WHERE l.id_sender = ?
            ORDER BY l.created_at DESC
        ");
        $stmt->execute([$senderId]);
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

    public function findByReceiverAndSender(int $receiverId, int $senderId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->tablePrefix}love 
            WHERE id_receiver = ? AND id_sender = ?
        ");
        $stmt->execute([$receiverId, $senderId]);
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
}
