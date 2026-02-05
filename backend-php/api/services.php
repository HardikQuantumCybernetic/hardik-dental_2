 <?php
 /**
  * Services API Endpoint
  * Replaces Supabase SDK calls for dental services catalog
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
             // ORIGINAL: supabase.from('services').select('*').order('name')
             $id = $_GET['id'] ?? null;
             $category = $_GET['category'] ?? null;
             
             $query = QueryBuilder::table('services');
             
             if ($id) {
                 $result = $query->eq('id', $id)->single();
             } else {
                 if ($category) {
                     $query->eq('category', $category);
                 }
                 $result = $query->order('name', 'asc')->get();
             }
             echo json_encode($result);
             break;
 
         case 'POST':
             // Protected: Only admin can create services
             $user = requireRole('admin');
             
             $data = json_decode(file_get_contents('php://input'), true);
             $result = $db->insert('services', [
                 'name' => $data['name'],
                 'description' => $data['description'] ?? null,
                 'category' => $data['category'] ?? 'general',
                 'default_cost' => $data['default_cost'] ?? 0
             ]);
             echo json_encode($result);
             break;
 
         case 'PUT':
             // Protected: Only admin can update services
             $user = requireRole('admin');
             
             $data = json_decode(file_get_contents('php://input'), true);
             $id = $data['id'] ?? $_GET['id'];
             unset($data['id']);
             $result = QueryBuilder::table('services')->eq('id', $id)->update($data);
             echo json_encode($result);
             break;
 
         case 'DELETE':
             // Protected: Only admin can delete services
             $user = requireRole('admin');
             
             $id = $_GET['id'];
             $result = QueryBuilder::table('services')->eq('id', $id)->delete();
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