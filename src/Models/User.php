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

    public function findAll(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->tablePrefix}user 
            ORDER BY name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->tablePrefix}user 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }



    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->tablePrefix}user 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(string $name, string $email): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->tablePrefix}user (name, email, created_at) 
            VALUES (?, ?, NOW())
        ");

        $stmt->execute([$name, $email]);
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
            SELECT * FROM {$this->tablePrefix}user 
            WHERE email = ? AND login_code = ? AND login_code_expires > NOW()
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

    public function update(int $id, string $name, string $email): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->tablePrefix}user 
            SET name = ?, email = ?
            WHERE id = ?
        ");

        return $stmt->execute([$name, $email, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->tablePrefix}user 
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }
}
