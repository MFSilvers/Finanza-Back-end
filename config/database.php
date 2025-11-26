<?php

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $conn;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'finanze_app';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: '';
        $this->port = getenv('DB_PORT') ?: '3306';
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $db_type = getenv('DB_TYPE') ?: 'mysql';
            
            if ($db_type === 'pgsql') {
                $dsn = "pgsql:host=" . $this->host . 
                       ";port=" . $this->port . 
                       ";dbname=" . $this->db_name . 
                       ";sslmode=require";
            } else {
                $dsn = "mysql:host=" . $this->host . 
                       ";port=" . $this->port . 
                       ";dbname=" . $this->db_name . 
                       ";charset=utf8mb4";
            }
            
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Errore di connessione al database");
        }

        return $this->conn;
    }
}
