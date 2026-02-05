 <?php
 /**
  * Authentication API Endpoint
  * Replaces Supabase Auth SDK calls
  */
 
 define('DENTAL_APP', true);
 require_once __DIR__ . '/../config/Database.class.php';
 require_once __DIR__ . '/../services/AuthService.php';
 
 header('Content-Type: application/json');
 header('Access-Control-Allow-Origin: *');
 header('Access-Control-Allow-Methods: POST, OPTIONS');
 header('Access-Control-Allow-Headers: Content-Type, Authorization');
 
 if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
     exit(0);
 }
 
 $action = $_GET['action'] ?? '';
 $auth = new AuthService();
 
 try {
     switch ($action) {
         case 'signup':
             // ORIGINAL: supabase.auth.signUp({ email, password })
             $data = json_decode(file_get_contents('php://input'), true);
             $result = $auth->signUp(
                 $data['email'],
                 $data['password'],
                 $data['role'] ?? 'patient'
             );
             
             if ($result['error']) {
                 http_response_code(400);
             }
             echo json_encode($result);
             break;
 
         case 'signin':
             // ORIGINAL: supabase.auth.signInWithPassword({ email, password })
             $data = json_decode(file_get_contents('php://input'), true);
             $result = $auth->signIn($data['email'], $data['password']);
             
             if ($result['error']) {
                 http_response_code(401);
             }
             echo json_encode($result);
             break;
 
         case 'signout':
             // ORIGINAL: supabase.auth.signOut()
             $headers = getallheaders();
             $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
             
             if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                 $token = $matches[1];
                 $result = $auth->signOut($token);
             } else {
                 $result = ['success' => false, 'error' => 'No token provided'];
             }
             echo json_encode($result);
             break;
 
         case 'session':
             // ORIGINAL: supabase.auth.getSession()
             $headers = getallheaders();
             $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
             
             if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                 $token = $matches[1];
                 $result = $auth->getSession($token);
             } else {
                 $result = ['session' => null, 'user' => null, 'error' => 'No token provided'];
             }
             echo json_encode($result);
             break;
 
         case 'refresh':
             // ORIGINAL: Auto-handled by Supabase client
             $headers = getallheaders();
             $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
             
             if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                 $token = $matches[1];
                 $result = $auth->refreshSession($token);
             } else {
                 $result = ['session' => null, 'error' => 'No token provided'];
             }
             echo json_encode($result);
             break;
 
         case 'reset-password':
             $data = json_decode(file_get_contents('php://input'), true);
             $result = $auth->resetPasswordRequest($data['email']);
             echo json_encode($result);
             break;
 
         default:
             http_response_code(400);
             echo json_encode(['error' => 'Invalid action. Use: signup, signin, signout, session, refresh']);
     }
 } catch (Exception $e) {
     http_response_code(500);
     echo json_encode(['error' => $e->getMessage()]);
 }