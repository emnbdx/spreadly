<?php

namespace App\Models;

use PDO;

class User
{
    private PDO $db;
    private string $tablePrefix;

    public function __construct(PDO $db, string $tablePrefix = '')
    {
        $this->db = $db;
        $this->tablePrefix = $tablePrefix;
    }

    public function findAll(int $campaignId = null): array
    {
        if ($campaignId) {
            $stmt = $this->db->prepare("
                SELECT * FROM {$this->tablePrefix}user 
                WHERE campaign_id = ?
                ORDER BY name
            ");
            $stmt->execute([$campaignId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT u.*, c.name as campaign_name 
                FROM {$this->tablePrefix}user u
                JOIN {$this->tablePrefix}campaign c ON u.campaign_id = c.id
                ORDER BY c.name, u.name
            ");
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findReceivers(int $campaignId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->tablePrefix}user 
            WHERE campaign_id = ? AND receiver = 1 
            ORDER BY name
        ");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByEmail(string $email, int $campaignId = null): ?array
    {
        if ($campaignId) {
            $stmt = $this->db->prepare("
                SELECT * FROM {$this->tablePrefix}user 
                WHERE email = ? AND campaign_id = ?
            ");
            $stmt->execute([$email, $campaignId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT u.*, c.name as campaign_name, c.slug as campaign_slug 
                FROM {$this->tablePrefix}user u
                JOIN {$this->tablePrefix}campaign c ON u.campaign_id = c.id
                WHERE u.email = ?
                LIMIT 1
            ");
            $stmt->execute([$email]);
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findAllCampaignsByEmail(string $email): array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, c.name as campaign_name, c.slug as campaign_slug 
            FROM {$this->tablePrefix}user u
            JOIN {$this->tablePrefix}campaign c ON u.campaign_id = c.id
            WHERE u.email = ?
            ORDER BY c.name
        ");
        $stmt->execute([$email]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, c.name as campaign_name, c.slug as campaign_slug 
            FROM {$this->tablePrefix}user u
            JOIN {$this->tablePrefix}campaign c ON u.campaign_id = c.id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(int $campaignId, string $name, string $email, bool $receiver = true): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->tablePrefix}user (campaign_id, name, email, receiver, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");

        $stmt->execute([$campaignId, $name, $email, $receiver ? 1 : 0]);
        return $this->db->lastInsertId();
    }

    public function generateLoginCode(int $userId): string
    {
        $code = sprintf('%06d', random_int(100000, 999999));
        $expires = (new \DateTime('+1 hour'))->format('Y-m-d H:i:s');

        $stmt = $this->db->prepare("
            UPDATE {$this->tablePrefix}user 
            SET login_code = ?, login_code_expires = ? 
            WHERE id = ?
        ");
        $stmt->execute([$code, $expires, $userId]);

        return $code;
    }

    public function validateLoginCode(string $email, string $code): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, c.name as campaign_name, c.slug as campaign_slug 
            FROM {$this->tablePrefix}user u
            JOIN {$this->tablePrefix}campaign c ON u.campaign_id = c.id
            WHERE u.email = ? AND u.login_code = ? AND u.login_code_expires > NOW()
        ");
        $stmt->execute([$email, $code]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $this->clearLoginCode($user['id']);
        }

        return $user ?: null;
    }

    private function clearLoginCode(int $userId): void
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->tablePrefix}user 
            SET login_code = NULL, login_code_expires = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }

    public function update(int $id, string $name, string $email, bool $receiver = true): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->tablePrefix}user 
            SET name = ?, email = ?, receiver = ?
            WHERE id = ?
        ");

        return $stmt->execute([$name, $email, $receiver ? 1 : 0, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->tablePrefix}user 
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }

    public function createBatch(int $campaignId, array $users): array
    {
        $results = [];

        foreach ($users as $user) {
            try {
                $existingUser = $this->findByEmail($user['email'], $campaignId);
                if ($existingUser) {
                    $results[] = [
                        'email' => $user['email'],
                        'status' => 'skipped',
                        'message' => 'Email déjà existant dans ce Spreadly'
                    ];
                    continue;
                }

                $id = $this->create(
                    $campaignId,
                    $user['name'],
                    $user['email'],
                    $user['receiver'] ?? true
                );

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
