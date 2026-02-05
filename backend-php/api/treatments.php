 <?php
 /**
  * Treatments API Endpoint
  * Replaces Supabase SDK calls for treatment operations
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
             // ORIGINAL: supabase.from('treatments').select('*').order('created_at', { ascending: false })
             $id = $_GET['id'] ?? null;
             $patientId = $_GET['patient_id'] ?? null;
             $appointmentId = $_GET['appointment_id'] ?? null;
             $status = $_GET['status'] ?? null;
             
             $query = QueryBuilder::table('treatments');
             
             if ($id) {
                 $result = $query->eq('id', $id)->single();
             } else {
                 if ($patientId) {
                     $query->eq('patient_id', $patientId);
                 }
                 if ($appointmentId) {
                     $query->eq('appointment_id', $appointmentId);
                 }
                 if ($status) {
                     $query->eq('status', $status);
                 }
                 $result = $query->order('created_at', 'desc')->get();
             }
             echo json_encode($result);
             break;
 
         case 'POST':
             // ORIGINAL: supabase.from('treatments').insert({...}).select().single()
             $data = json_decode(file_get_contents('php://input'), true);
             $result = $db->insert('treatments', [
                 'patient_id' => $data['patient_id'] ?? null,
                 'appointment_id' => $data['appointment_id'] ?? null,
                 'treatment_type' => $data['treatment_type'],
                 'description' => $data['description'] ?? null,
                 'cost' => $data['cost'] ?? null,
                 'status' => $data['status'] ?? 'planned'
             ]);
             echo json_encode($result);
             break;
 
         case 'PUT':
             // ORIGINAL: supabase.from('treatments').update({...}).eq('id', id)
             $data = json_decode(file_get_contents('php://input'), true);
             $id = $data['id'] ?? $_GET['id'];
             unset($data['id']);
             $result = QueryBuilder::table('treatments')->eq('id', $id)->update($data);
             echo json_encode($result);
             break;
 
         case 'DELETE':
             // ORIGINAL: supabase.from('treatments').delete().eq('id', id)
             $id = $_GET['id'];
             $result = QueryBuilder::table('treatments')->eq('id', $id)->delete();
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