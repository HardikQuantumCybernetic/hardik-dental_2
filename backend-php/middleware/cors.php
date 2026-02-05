 <?php
 /**
  * CORS Middleware
  * Handles Cross-Origin Resource Sharing for API requests
  */
 
 function handleCORS() {
     // Get allowed origins from config or environment
     $allowedOrigins = [
         'http://localhost:8080',
         'http://localhost:3000',
         'http://localhost:5173',
         'https://hardik306-72.lovable.app',
         'https://yourdomain.com' // Update with your production domain
     ];
     
     $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
     
     // Check if origin is allowed
     if (in_array($origin, $allowedOrigins)) {
         header("Access-Control-Allow-Origin: $origin");
     } else {
         // For development, allow all origins
         // Remove this in production!
         header("Access-Control-Allow-Origin: *");
     }
     
     header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
     header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
     header("Access-Control-Max-Age: 3600");
     header("Access-Control-Allow-Credentials: true");
     
     // Handle preflight OPTIONS request
     if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
         http_response_code(200);
         exit(0);
     }
 }
 
 /**
  * Request validation middleware
  */
 function validateRequest() {
     // Set JSON content type for responses
     header('Content-Type: application/json');
     
     // Validate Content-Type for POST/PUT requests
     if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'])) {
         $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
         
         if (strpos($contentType, 'application/json') === false) {
             // Allow form data too
             if (strpos($contentType, 'multipart/form-data') === false &&
                 strpos($contentType, 'application/x-www-form-urlencoded') === false) {
                 http_response_code(415);
                 echo json_encode(['error' => 'Content-Type must be application/json']);
                 exit;
             }
         }
     }
 }
 
 /**
  * Rate limiting middleware (basic implementation)
  */
 function rateLimit($maxRequests = 100, $timeWindow = 60) {
     $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
     $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($ip) . '.json';
     
     $data = ['count' => 0, 'start' => time()];
     
     if (file_exists($cacheFile)) {
         $data = json_decode(file_get_contents($cacheFile), true);
         
         // Reset if time window passed
         if (time() - $data['start'] > $timeWindow) {
             $data = ['count' => 0, 'start' => time()];
         }
     }
     
     $data['count']++;
     file_put_contents($cacheFile, json_encode($data));
     
     // Set rate limit headers
     header("X-RateLimit-Limit: $maxRequests");
     header("X-RateLimit-Remaining: " . max(0, $maxRequests - $data['count']));
     header("X-RateLimit-Reset: " . ($data['start'] + $timeWindow));
     
     if ($data['count'] > $maxRequests) {
         http_response_code(429);
         echo json_encode(['error' => 'Too many requests. Please try again later.']);
         exit;
     }
 }
 
 /**
  * Logging middleware
  */
 function logRequest() {
     $logDir = __DIR__ . '/../logs';
     if (!is_dir($logDir)) {
         mkdir($logDir, 0755, true);
     }
     
     $logEntry = [
         'timestamp' => date('Y-m-d H:i:s'),
         'method' => $_SERVER['REQUEST_METHOD'],
         'uri' => $_SERVER['REQUEST_URI'],
         'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
         'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
     ];
     
     $logFile = $logDir . '/access_' . date('Y-m-d') . '.log';
     file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND);
 }