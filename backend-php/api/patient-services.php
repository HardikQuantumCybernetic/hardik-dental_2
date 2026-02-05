 <?php
 /**
  * Patient Services API Endpoint
  * Replaces Supabase SDK calls for patient-service assignments
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
             // ORIGINAL: supabase.from('patient_services').select('*, services(*)').eq('patient_id', id)
             $id = $_GET['id'] ?? null;
             $patientId = $_GET['patient_id'] ?? null;
             $status = $_GET['status'] ?? null;
             
             $query = QueryBuilder::table('patient_services');
             
             if ($id) {
                 $result = $query->eq('id', $id)->single();
             } else {
                 if ($patientId) {
                     $query->eq('patient_id', $patientId);
                 }
                 if ($status) {
                     $query->eq('status', $status);
                 }
                 $result = $query->order('created_at', 'desc')->get();
             }
             echo json_encode($result);
             break;
 
         case 'POST':
             // ORIGINAL: supabase.from('patient_services').insert({...}).select().single()
             $data = json_decode(file_get_contents('php://input'), true);
             $result = $db->insert('patient_services', [
                 'patient_id' => $data['patient_id'],
                 'service_id' => $data['service_id'],
                 'assigned_cost' => $data['assigned_cost'] ?? 0,
                 'scheduled_date' => $data['scheduled_date'] ?? null,
                 'status' => $data['status'] ?? 'pending',
                 'notes' => $data['notes'] ?? null
             ]);
             echo json_encode($result);
             break;
 
         case 'PUT':
             // ORIGINAL: supabase.from('patient_services').update({...}).eq('id', id)
             $data = json_decode(file_get_contents('php://input'), true);
             $id = $data['id'] ?? $_GET['id'];
             unset($data['id']);
             
             // Set completed_date if status changed to completed
             if (isset($data['status']) && $data['status'] === 'completed' && !isset($data['completed_date'])) {
                 $data['completed_date'] = date('Y-m-d');
             }
             
             $data['updated_at'] = date('Y-m-d H:i:s');
             $result = QueryBuilder::table('patient_services')->eq('id', $id)->update($data);
             echo json_encode($result);
             break;
 
         case 'DELETE':
             // ORIGINAL: supabase.from('patient_services').delete().eq('id', id)
             $id = $_GET['id'];
             $result = QueryBuilder::table('patient_services')->eq('id', $id)->delete();
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