 <?php
 /**
  * Patient Financials API Endpoint
  * Replaces Supabase SDK calls for financial operations
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
             // ORIGINAL: supabase.from('patient_financials').select('*')
             $id = $_GET['id'] ?? null;
             $patientId = $_GET['patient_id'] ?? null;
             
             $query = QueryBuilder::table('patient_financials');
             
             if ($id) {
                 $result = $query->eq('id', $id)->single();
             } elseif ($patientId) {
                 $result = $query->eq('patient_id', $patientId)->single();
             } else {
                 $result = $query->order('updated_at', 'desc')->get();
             }
             echo json_encode($result);
             break;
 
         case 'POST':
             // ORIGINAL: supabase.from('patient_financials').insert({...}).select().single()
             $data = json_decode(file_get_contents('php://input'), true);
             
             // Calculate remaining from patient
             $totalCost = floatval($data['total_treatment_cost'] ?? 0);
             $amountPaid = floatval($data['amount_paid_by_patient'] ?? 0);
             $remaining = $totalCost - $amountPaid;
             
             $result = $db->insert('patient_financials', [
                 'patient_id' => $data['patient_id'],
                 'total_treatment_cost' => $totalCost,
                 'amount_paid_by_patient' => $amountPaid,
                 'remaining_from_patient' => $remaining,
                 'amount_due_to_doctor' => $data['amount_due_to_doctor'] ?? 0,
                 'notes' => $data['notes'] ?? null
             ]);
             echo json_encode($result);
             break;
 
         case 'PUT':
             // ORIGINAL: supabase.from('patient_financials').update({...}).eq('id', id)
             $data = json_decode(file_get_contents('php://input'), true);
             $id = $data['id'] ?? $_GET['id'];
             unset($data['id']);
             
             // Recalculate remaining if costs changed
             if (isset($data['total_treatment_cost']) || isset($data['amount_paid_by_patient'])) {
                 $existing = QueryBuilder::table('patient_financials')->eq('id', $id)->single();
                 if ($existing['data']) {
                     $totalCost = floatval($data['total_treatment_cost'] ?? $existing['data']['total_treatment_cost']);
                     $amountPaid = floatval($data['amount_paid_by_patient'] ?? $existing['data']['amount_paid_by_patient']);
                     $data['remaining_from_patient'] = $totalCost - $amountPaid;
                 }
             }
             
             $data['updated_at'] = date('Y-m-d H:i:s');
             $result = QueryBuilder::table('patient_financials')->eq('id', $id)->update($data);
             echo json_encode($result);
             break;
 
         case 'DELETE':
             // ORIGINAL: supabase.from('patient_financials').delete().eq('id', id)
             $id = $_GET['id'];
             $result = QueryBuilder::table('patient_financials')->eq('id', $id)->delete();
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