 <?php
 /**
  * Feedback API Endpoint
  * Replaces Supabase SDK calls for feedback operations
  */
 
 define('DENTAL_APP', true);
 require_once __DIR__ . '/../config/Database.class.php';
 require_once __DIR__ . '/../services/AuthService.php';
 
 header('Content-Type: application/json');
 header('Access-Control-Allow-Origin: *');
 header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
 header('Access-Control-Allow-Headers: Content-Type, Authorization');
 
 if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
     exit(0);
 }
 
 $method = $_SERVER['REQUEST_METHOD'];
 $db = Database::getInstance();
 
 try {
     switch ($method) {
         case 'GET':
             // ORIGINAL: supabase.from('feedback').select('*').order('created_at', { ascending: false })
             $id = $_GET['id'] ?? null;
             $status = $_GET['status'] ?? null;
             $category = $_GET['category'] ?? null;
             
             $query = QueryBuilder::table('feedback');
             
             if ($id) {
                 $result = $query->eq('id', $id)->single();
             } else {
                 if ($status) {
                     $query->eq('status', $status);
                 }
                 if ($category) {
                     $query->eq('category', $category);
                 }
                 $result = $query->order('created_at', 'desc')->get();
             }
             echo json_encode($result);
             break;
 
         case 'POST':
             // ORIGINAL: supabase.from('feedback').insert({...}).select().single()
             $data = json_decode(file_get_contents('php://input'), true);
             $result = $db->insert('feedback', [
                 'patient_id' => $data['patient_id'] ?? null,
                 'patient_name' => $data['patient_name'],
                 'patient_email' => $data['patient_email'],
                 'rating' => $data['rating'],
                 'message' => $data['message'],
                 'category' => $data['category'] ?? 'general',
                 'status' => 'new'
             ]);
             echo json_encode($result);
             break;
 
         case 'PUT':
             // ORIGINAL: supabase.from('feedback').update({...}).eq('id', id)
             // Protected: Only admin can update feedback status
             $user = requireAuth();
             $auth = new AuthService();
             
             if (!$auth->hasRole($user['id'], 'admin')) {
                 http_response_code(403);
                 echo json_encode(['error' => 'Admin access required']);
                 exit;
             }
             
             $data = json_decode(file_get_contents('php://input'), true);
             $id = $data['id'] ?? $_GET['id'];
             unset($data['id']);
             $data['updated_at'] = date('Y-m-d H:i:s');
             $result = QueryBuilder::table('feedback')->eq('id', $id)->update($data);
             echo json_encode($result);
             break;
 
         default:
             http_response_code(405);
             echo json_encode(['error' => 'Method not allowed']);
     }
 } catch (Exception $e) {
     http_response_code(500);
     echo json_encode(['error' => $e->getMessage()]);
 }