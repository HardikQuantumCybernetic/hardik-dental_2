 <?php
 /**
  * Appointments API Endpoint
  * Replaces Supabase SDK calls for appointment operations
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
             // ORIGINAL: supabase.from('appointments').select('*').order('appointment_date', { ascending: true })
             $id = $_GET['id'] ?? null;
             $patientId = $_GET['patient_id'] ?? null;
             $status = $_GET['status'] ?? null;
             $date = $_GET['date'] ?? null;
             
             $query = QueryBuilder::table('appointments');
             
             if ($id) {
                 $result = $query->eq('id', $id)->single();
             } else {
                 if ($patientId) {
                     $query->eq('patient_id', $patientId);
                 }
                 if ($status) {
                     $query->eq('status', $status);
                 }
                 if ($date) {
                     $query->eq('appointment_date', $date);
                 }
                 $result = $query->order('appointment_date', 'asc')
                                 ->order('appointment_time', 'asc')
                                 ->get();
             }
             echo json_encode($result);
             break;
 
         case 'POST':
             // ORIGINAL: supabase.from('appointments').insert({...}).select().single()
             $data = json_decode(file_get_contents('php://input'), true);
             
             // Check for time slot conflicts
             $existing = QueryBuilder::table('appointments')
                 ->eq('appointment_date', $data['appointment_date'])
                 ->eq('appointment_time', $data['appointment_time'])
                 ->eq('doctor', $data['doctor'])
                 ->neq('status', 'cancelled')
                 ->get();
             
             if (!empty($existing['data'])) {
                 http_response_code(409);
                 echo json_encode(['error' => 'Time slot already booked']);
                 exit;
             }
             
             $result = $db->insert('appointments', [
                 'patient_id' => $data['patient_id'] ?? null,
                 'appointment_date' => $data['appointment_date'],
                 'appointment_time' => $data['appointment_time'],
                 'doctor' => $data['doctor'],
                 'service_type' => $data['service_type'],
                 'notes' => $data['notes'] ?? null,
                 'status' => $data['status'] ?? 'scheduled'
             ]);
             echo json_encode($result);
             break;
 
         case 'PUT':
             // ORIGINAL: supabase.from('appointments').update({...}).eq('id', id)
             $data = json_decode(file_get_contents('php://input'), true);
             $id = $data['id'] ?? $_GET['id'];
             unset($data['id']);
             $result = QueryBuilder::table('appointments')->eq('id', $id)->update($data);
             echo json_encode($result);
             break;
 
         case 'DELETE':
             // ORIGINAL: supabase.from('appointments').delete().eq('id', id)
             $id = $_GET['id'];
             $result = QueryBuilder::table('appointments')->eq('id', $id)->delete();
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