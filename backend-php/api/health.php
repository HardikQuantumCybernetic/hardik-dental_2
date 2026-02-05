 <?php
 /**
  * ============================================================================
  * Health Check API Endpoint
  * ============================================================================
  * Used by frontend to verify backend connectivity
  * ============================================================================
  */
 
 header('Content-Type: application/json');
 header('Access-Control-Allow-Origin: *');
 header('Access-Control-Allow-Methods: GET, OPTIONS');
 header('Access-Control-Allow-Headers: Content-Type');
 
 // Handle preflight
 if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
     http_response_code(200);
     exit;
 }
 
 // Only allow GET
 if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
     http_response_code(405);
     echo json_encode(['error' => 'Method not allowed']);
     exit;
 }
 
 // Check database connection
 $databaseConnected = false;
 try {
     // Try to load Database class and test connection
     define('DENTAL_APP', true);
     require_once __DIR__ . '/../config/Database.class.php';
     
     $db = Database::getInstance();
     $conn = $db->getConnection();
     
     if ($conn) {
         // Test with a simple query
         $result = oci_parse($conn, 'SELECT 1 FROM DUAL');
         if (oci_execute($result)) {
             $databaseConnected = true;
         }
         oci_free_statement($result);
     }
 } catch (Exception $e) {
     // Database not connected
     $databaseConnected = false;
 }
 
 echo json_encode([
     'success' => true,
     'status' => $databaseConnected ? 'healthy' : 'degraded',
     'database' => $databaseConnected,
     'timestamp' => date('c'),
     'version' => '1.0.0',
     'environment' => getenv('APP_ENV') ?: 'production'
 ]);