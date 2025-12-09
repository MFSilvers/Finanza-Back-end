<?php

function validateOrigin() {
    // Allow health check endpoints without ALLOWED_ORIGINS
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
    if ($uri === '/' || $uri === '/health' || strpos($uri, '/health') === 0) {
        return true;
    }
    
    $allowedOrigins = [];
    
    // Get allowed origins from environment variable (comma-separated)
    $envOrigins = getenv('ALLOWED_ORIGINS');
    if (empty($envOrigins)) {
        error_log("Security: ALLOWED_ORIGINS not configured. Blocking all requests.");
        return false;
    }
    $allowedOrigins = array_map('trim', explode(',', $envOrigins));
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Allow health check endpoint without origin validation
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
    if ($uri === '/' || $uri === '/health' || strpos($uri, '/health') === 0) {
        return true;
    }
    
    // For API requests, require valid origin
    if (empty($origin)) {
        // No origin header - could be direct API call or server-to-server
        // Check referer as fallback
        if (!empty($referer)) {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $allowedHosts = array_map(function($url) {
                return parse_url($url, PHP_URL_HOST);
            }, $allowedOrigins);
            
            if (!in_array($refererHost, $allowedHosts)) {
                error_log("Security: Blocked request - Invalid referer: {$refererHost}");
                return false;
            }
        } else {
            // No origin and no referer - block direct API calls
            error_log("Security: Blocked request - No origin or referer header");
            return false;
        }
    } else {
        // Check if origin is in allowed list
        if (!in_array($origin, $allowedOrigins)) {
            error_log("Security: Blocked request - Invalid origin: {$origin}");
            return false;
        }
    }
    
    return true;
}

function setCorsHeaders() {
    // Get allowed origins from environment variable (comma-separated)
    $envOrigins = getenv('ALLOWED_ORIGINS');
    
    // Allow health check endpoints without ALLOWED_ORIGINS
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
    $isHealthCheck = ($uri === '/' || $uri === '/health' || strpos($uri, '/health') === 0);
    
    if (empty($envOrigins) && !$isHealthCheck) {
        error_log("Security: ALLOWED_ORIGINS not configured in setCorsHeaders.");
        http_response_code(500);
        header("Content-Type: application/json");
        echo json_encode(['error' => 'Server configuration error: ALLOWED_ORIGINS not set']);
        exit();
    }
    
    $allowedOrigins = $envOrigins ? array_map('trim', explode(',', $envOrigins)) : [];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $isOptions = $method === 'OPTIONS';
    
    // For health check, allow all origins
    if ($isHealthCheck) {
        header("Access-Control-Allow-Origin: *");
    } else {
        // Validate origin first for non-health-check endpoints
        if (!validateOrigin()) {
            http_response_code(403);
            header("Content-Type: application/json");
            echo json_encode(['error' => 'Access denied. Invalid origin.']);
            exit();
        }
        
        // If origin is in allowed list, use it
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: " . $origin);
            header("Access-Control-Allow-Credentials: true");
        } elseif (!empty($origin)) {
            // This shouldn't happen if validateOrigin() works correctly, but as fallback
            header("Access-Control-Allow-Origin: " . $origin);
        }
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Max-Age: 3600");
    
    // Handle preflight OPTIONS request
    if ($isOptions) {
        error_log("setCorsHeaders: Handling OPTIONS preflight request");
        http_response_code(200);
        // Clean any output buffers if they exist
        if (function_exists('ob_get_level')) {
            $level = @ob_get_level();
            while ($level > 0) {
                @ob_end_clean();
                $level = @ob_get_level();
            }
        }
        exit();
    }
    
    // Set Content-Type for non-OPTIONS requests
    header("Content-Type: application/json; charset=UTF-8");
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit();
}

function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendError("Field '$field' is required", 400);
        }
    }
}

function sendVerificationCodeEmail($recipientEmail, $recipientName, $code) {
    @require_once __DIR__ . '/../load_env.php';
    
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        error_log("Email: vendor/autoload.php not found. Skipping verification email.");
        return false;
    }
    
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $brevoApiKey = $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
    $senderEmail = $_ENV['BREVO_SENDER_EMAIL'] ?? getenv('BREVO_SENDER_EMAIL') ?? '';
    $senderName = $_ENV['BREVO_SENDER_NAME'] ?? getenv('BREVO_SENDER_NAME') ?? 'Finanza App';
    
    if (empty($brevoApiKey) || empty($senderEmail)) {
        error_log("Email: BREVO_API_KEY or BREVO_SENDER_EMAIL not configured. Skipping verification email.");
        return false;
    }
    
    try {
        $config = \Brevo\Client\Configuration::getDefaultConfiguration();
        $config->setApiKey('api-key', $brevoApiKey);
        
        $apiInstance = new \Brevo\Client\Api\TransactionalEmailsApi(null, $config);
        
        $oldErrorReporting = error_reporting(E_ALL & ~E_DEPRECATED);
        $sendSmtpEmail = new \Brevo\Client\Model\SendSmtpEmail();
        error_reporting($oldErrorReporting);
        
        $subject = 'Codice di verifica email - Finanza';
        $htmlMessage = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #4F46E5;'>Verifica la tua email</h2>
            <p>Ciao <strong>{$recipientName}</strong>,</p>
            <p>Grazie per esserti registrato! Per completare la registrazione, inserisci il seguente codice di verifica:</p>
            <div style='background: #F3F4F6; border: 2px solid #4F46E5; border-radius: 8px; padding: 20px; text-align: center; margin: 30px 0;'>
                <h1 style='color: #4F46E5; font-size: 36px; letter-spacing: 8px; margin: 0;'>{$code}</h1>
            </div>
            <p>Questo codice scade tra <strong>10 minuti</strong>.</p>
            <p style='color: #6B7280; font-size: 14px; margin-top: 30px;'>Se non hai richiesto questo codice, ignora questa email.</p>
            <p style='color: #6B7280; font-size: 14px;'>Il team di Finanza</p>
        </div>
        ";
        
        $textMessage = "Verifica la tua email\n\nCiao {$recipientName},\n\nGrazie per esserti registrato! Per completare la registrazione, inserisci il seguente codice di verifica:\n\n{$code}\n\nQuesto codice scade tra 10 minuti.\n\nSe non hai richiesto questo codice, ignora questa email.\n\nIl team di Finanza";
        
        $sendSmtpEmail->setSender(['name' => $senderName, 'email' => $senderEmail]);
        $sendSmtpEmail->setTo([['email' => $recipientEmail, 'name' => $recipientName]]);
        $sendSmtpEmail->setSubject($subject);
        $sendSmtpEmail->setTextContent($textMessage);
        $sendSmtpEmail->setHtmlContent($htmlMessage);
        
        $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
        
        error_log("Email: Verification code sent successfully to {$recipientEmail}");
        return true;
    } catch (\Exception $e) {
        error_log("Email: Failed to send verification code to {$recipientEmail} - " . $e->getMessage());
        return false;
    }
}

function sendWelcomeEmail($recipientEmail, $recipientName) {
    @require_once __DIR__ . '/../load_env.php';
    
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        error_log("Email: vendor/autoload.php not found. Skipping welcome email.");
        return false;
    }
    
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $brevoApiKey = $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
    $senderEmail = $_ENV['BREVO_SENDER_EMAIL'] ?? getenv('BREVO_SENDER_EMAIL') ?? '';
    $senderName = $_ENV['BREVO_SENDER_NAME'] ?? getenv('BREVO_SENDER_NAME') ?? 'Finanza App';
    
    if (empty($brevoApiKey) || empty($senderEmail)) {
        error_log("Email: BREVO_API_KEY or BREVO_SENDER_EMAIL not configured. Skipping welcome email.");
        return false;
    }
    
    try {
        $config = \Brevo\Client\Configuration::getDefaultConfiguration();
        $config->setApiKey('api-key', $brevoApiKey);
        
        $apiInstance = new \Brevo\Client\Api\TransactionalEmailsApi(null, $config);
        
        $oldErrorReporting = error_reporting(E_ALL & ~E_DEPRECATED);
        $sendSmtpEmail = new \Brevo\Client\Model\SendSmtpEmail();
        error_reporting($oldErrorReporting);
        
        $subject = 'Benvenuto in Finanza!';
        $htmlMessage = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #4F46E5;'>Grazie per esserti iscritto!</h2>
            <p>Ciao <strong>{$recipientName}</strong>,</p>
            <p>Siamo felici di averti nella nostra community! Ora puoi iniziare a gestire le tue finanze in modo semplice e intuitivo.</p>
            <p>Puoi iniziare aggiungendo le tue prime transazioni e visualizzare le statistiche sulla tua dashboard.</p>
            <p>Se hai domande o bisogno di assistenza, non esitare a contattarci.</p>
            <p style='margin-top: 30px;'>Buona gestione delle finanze!</p>
            <p style='color: #6B7280; font-size: 14px;'>Il team di Finanza</p>
        </div>
        ";
        
        $textMessage = "Grazie per esserti iscritto!\n\nCiao {$recipientName},\n\nSiamo felici di averti nella nostra community! Ora puoi iniziare a gestire le tue finanze in modo semplice e intuitivo.\n\nPuoi iniziare aggiungendo le tue prime transazioni e visualizzare le statistiche sulla tua dashboard.\n\nBuona gestione delle finanze!\n\nIl team di Finanza";
        
        $sendSmtpEmail->setSender(['name' => $senderName, 'email' => $senderEmail]);
        $sendSmtpEmail->setTo([['email' => $recipientEmail, 'name' => $recipientName]]);
        $sendSmtpEmail->setSubject($subject);
        $sendSmtpEmail->setTextContent($textMessage);
        $sendSmtpEmail->setHtmlContent($htmlMessage);
        
        $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
        
        error_log("Email: Welcome email sent successfully to {$recipientEmail}");
        return true;
    } catch (\Exception $e) {
        error_log("Email: Failed to send welcome email to {$recipientEmail} - " . $e->getMessage());
        return false;
    }
}
