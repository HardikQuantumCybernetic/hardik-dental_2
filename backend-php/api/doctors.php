 <?php
 /**
  * Doctors API Endpoint
  * Replaces Supabase SDK calls for doctor operations
  */
 
 define('DENTAL_APP', true);
 require_once __DIR__ . '/../config/Database.class.php';
 require_once __DIR__ . '/../services/AuthService.php';
 
 header('Content-Type: application/json');
 header('Access-Control-Allow-Origin: *');
 header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
 header('Access-Control-Allow-Headers: Content-Type, Authorization');
 
 if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
     exit(0);
 }
 
 $method = $_SERVER['REQUEST_METHOD'];
 $db = Database::getInstance();
 
 try {
     switch ($method) {
         case 'GET':
             // ORIGINAL: supabase.from('doctors').select('*').order('name')
             $id = $_GET['id'] ?? null;
             $activeOnly = isset($_GET['active']) && $_GET['active'] === 'true';
             
             $query = QueryBuilder::table('doctors');
             
             if ($id) {
                 $result = $query->eq('id', $id)->single();
             } else {
                 if ($activeOnly) {
                     $query->eq('is_active', 1);
                 }
                 $result = $query->order('name', 'asc')->get();
             }
             echo json_encode($result);
             break;
 
         case 'POST':
             // Protected: Only admin can add doctors
             $user = requireRole('admin');
             
             $data = json_decode(file_get_contents('php://input'), true);
             $result = $db->insert('doctors', [
                 'name' => $data['name'],
                 'specialty' => $data['specialty'] ?? null,
                 'email' => $data['email'] ?? null,
                 'phone' => $data['phone'] ?? null,
                 'is_active' => $data['is_active'] ?? 1
             ]);
             echo json_encode($result);
             break;
 
         case 'PUT':
             // Protected: Only admin can update doctors
             $user = requireRole('admin');
             
             $data = json_decode(file_get_contents('php://input'), true);
             $id = $data['id'] ?? $_GET['id'];
             unset($data['id']);
             $result = QueryBuilder::table('doctors')->eq('id', $id)->update($data);
             echo json_encode($result);
             break;
 
         case 'DELETE':
             // Protected: Only admin can delete doctors
             $user = requireRole('admin');
             
             $id = $_GET['id'];
             $result = QueryBuilder::table('doctors')->eq('id', $id)->delete();
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