<?php

namespace App\Models;

use PDO;
use DateTime;

class Campaign
{
    private PDO $db;
    private string $tablePrefix;

    public function __construct(PDO $db, string $tablePrefix = '')
    {
        $this->db = $db;
        $this->tablePrefix = $tablePrefix;
    }

    public function findAll(): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   COUNT(DISTINCT u.id) as user_count,
                   COUNT(DISTINCT l.id) as message_count
            FROM {$this->tablePrefix}campaign c
            LEFT JOIN {$this->tablePrefix}user u ON c.id = u.campaign_id
            LEFT JOIN {$this->tablePrefix}love l ON c.id = l.campaign_id
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->tablePrefix}campaign 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->tablePrefix}campaign 
            WHERE slug = ?
        ");
        $stmt->execute([$slug]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findActive(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->tablePrefix}campaign 
            WHERE is_active = 1 
            ORDER BY name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(string $name, string $slug, string $startDate, string $endDate, array $options = []): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->tablePrefix}campaign 
            (name, slug, start_date, end_date, theme, mail_subject, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $name,
            $slug,
            $startDate,
            $endDate,
            $options['theme'] ?? 'christmas',
            $options['mail_subject'] ?? 'Your love messages',
            $options['is_active'] ?? true ? 1 : 0
        ]);

        return $this->db->lastInsertId();
    }

    public function update(int $id, string $name, string $slug, string $startDate, string $endDate, array $options = []): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->tablePrefix}campaign 
            SET name = ?, slug = ?, start_date = ?, end_date = ?, 
                theme = ?, mail_subject = ?, is_active = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $name,
            $slug,
            $startDate,
            $endDate,
            $options['theme'] ?? 'christmas',
            $options['mail_subject'] ?? 'Your love messages',
            $options['is_active'] ?? true ? 1 : 0,
            $id
        ]);
    }

    public function delete(int $id): bool
    {
        try {
            $this->db->beginTransaction();

            // Supprimer les messages d'amour associés
            $stmt = $this->db->prepare("DELETE FROM {$this->tablePrefix}love WHERE campaign_id = ?");
            $stmt->execute([$id]);

            // Supprimer les administrateurs de la campagne
            $stmt = $this->db->prepare("DELETE FROM {$this->tablePrefix}campaign_admin WHERE campaign_id = ?");
            $stmt->execute([$id]);

            // Supprimer les utilisateurs de la campagne
            $stmt = $this->db->prepare("DELETE FROM {$this->tablePrefix}campaign_user WHERE campaign_id = ?");
            $stmt->execute([$id]);

            // Supprimer la campagne
            $stmt = $this->db->prepare("DELETE FROM {$this->tablePrefix}campaign WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function isActive(int $campaignId): bool
    {
        $campaign = $this->findById($campaignId);
        if (!$campaign) return false;

        $now = new DateTime();
        $startDate = new DateTime($campaign['start_date']);
        $endDate = new DateTime($campaign['end_date']);

        return $campaign['is_active'] && $now >= $startDate && $now <= $endDate;
    }

    public function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        // Vérifier l'unicité
        $originalSlug = $slug;
        $counter = 1;

        while ($this->findBySlug($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function findActiveBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->tablePrefix}campaign WHERE slug = ? AND is_active = 1");
        $stmt->execute([$slug]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getEndDate(int $campaignId): ?string
    {
        $stmt = $this->db->prepare("SELECT end_date FROM {$this->tablePrefix}campaign WHERE id = ?");
        $stmt->execute([$campaignId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['end_date'] : null;
    }

    public function updateEndDate(int $campaignId, string $endDate): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->tablePrefix}campaign SET end_date = ? WHERE id = ?");

        return $stmt->execute([$endDate, $campaignId]);
    }
}
