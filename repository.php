<?php

class Repository
{
    private $db = null;

    public function __construct()
    {
        $options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        );
        
        $this->db = new PDO(
            'mysql:host=' . $_SERVER['DbUrl'] . ';dbname=' . $_SERVER['DbName'] . ';charset=utf8mb4',
            $_SERVER['DbUser'],
            $_SERVER['DbPassword'],
            $options
        );
    }

    public function getReceivers() {
        $stmt = $this->db->prepare('
            SELECT *
            FROM ' . $_SERVER['DbPrefix'] . 'receiver
            ORDER BY name
        ');
            
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getLoves($id) {
        $stmt = $this->db->prepare('
            SELECT *
            FROM ' . $_SERVER['DbPrefix'] . 'love
            WHERE id_receiver = ?
        ');
            
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public function insertLove($to, $from, $message) {
        $stmt = $this->db->prepare('
            INSERT INTO ' . $_SERVER['DbPrefix'] . 'love (id_receiver, sender, content)
            VALUES (?, ?, ?)
        ');
        $stmt->execute([$to, $from, $message]);
    }
}
?>