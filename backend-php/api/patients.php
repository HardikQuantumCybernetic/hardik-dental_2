<?php
/**
 * Patients API Endpoint
 * Replaces Supabase SDK calls for patient operations
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
            // ORIGINAL: supabase.from('patients').select('*').order('created_at', { ascending: false })
            $id = $_GET['id'] ?? null;
            if ($id) {
                $result = QueryBuilder::table('patients')->eq('id', $id)->single();
            } else {
                $result = QueryBuilder::table('patients')->order('created_at', 'desc')->get();
            }
            echo json_encode($result);
            break;

        case 'POST':
            // ORIGINAL: supabase.from('patients').insert({...}).select().single()
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $db->insert('patients', [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'date_of_birth' => $data['date_of_birth'],
                'address' => $data['address'] ?? null,
                'medical_history' => $data['medical_history'] ?? null,
                'insurance_info' => $data['insurance_info'] ?? null,
                'status' => 'active'
            ]);
            echo json_encode($result);
            break;

        case 'PUT':
            // ORIGINAL: supabase.from('patients').update({...}).eq('id', id)
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? $_GET['id'];
            unset($data['id']);
            $result = QueryBuilder::table('patients')->eq('id', $id)->update($data);
            echo json_encode($result);
            break;

        case 'DELETE':
            // ORIGINAL: supabase.from('patients').delete().eq('id', id)
            $id = $_GET['id'];
            $result = QueryBuilder::table('patients')->eq('id', $id)->delete();
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
