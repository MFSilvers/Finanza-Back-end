<?php

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $conn;

    public function __construct() {
        $this->host = getenv('DB_HOST');
        $this->db_name = getenv('DB_NAME');
        $this->username = getenv('DB_USER');
        $this->password = getenv('DB_PASSWORD');
        
        if (empty($this->host) || empty($this->db_name) || empty($this->username)) {
            throw new Exception('Database configuration incomplete. Please set DB_HOST, DB_NAME, and DB_USER environment variables.');
        }
        
        // Set default port based on DB_TYPE
        $db_type = getenv('DB_TYPE') ?: 'mysql';
        $this->port = getenv('DB_PORT') ?: ($db_type === 'pgsql' || $db_type === 'postgres' ? '5432' : '3306');
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $db_type = getenv('DB_TYPE') ?: 'mysql';
            
            // Normalize PostgreSQL type names
            if ($db_type === 'postgres' || $db_type === 'postgresql') {
                $db_type = 'pgsql';
            }
            
            // Log connection attempt (without sensitive data)
            error_log("Database: Attempting connection - Type: {$db_type}, Host: {$this->host}, Port: {$this->port}, DB: {$this->db_name}, User: {$this->username}");
            
            if ($db_type === 'pgsql') {
               
                $sslmode = ($this->port == '6543') ? 'prefer' : 'require';
                
                // Try to resolve host to IPv4 if possible (helps with Railway IPv6 issues)
                $resolvedHost = $this->host;
                if (filter_var($this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                    
                    $ip = gethostbyname($this->host);
                    if ($ip !== $this->host && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $resolvedHost = $ip;
                        error_log("Database: Resolved host to IPv4: {$resolvedHost}");
                    }
                }
                
                $dsn = "pgsql:host=" . $resolvedHost . 
                       ";port=" . $this->port . 
                       ";dbname=" . $this->db_name . 
                       ";sslmode=" . $sslmode;
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
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 5
                ]
            );
            
            error_log("Database: Connection successful");
        } catch(PDOException $e) {
            $errorMsg = "Database Connection Error: " . $e->getMessage();
            error_log($errorMsg);
            error_log("Database: Connection details - Host: {$this->host}, Port: {$this->port}, DB: {$this->db_name}, User: {$this->username}");
            
            // In production, don't expose full error details
            if (getenv('APP_ENV') === 'development') {
                throw new Exception("Errore di connessione al database: " . $e->getMessage());
            } else {
                throw new Exception("Errore di connessione al database");
            }
        }

        return $this->conn;
    }
}
