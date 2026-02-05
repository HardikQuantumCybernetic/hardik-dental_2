 <?php
 /**
  * ============================================================================
  * Chatbot API Endpoint - Gemini AI Integration
  * ============================================================================
  * Replaces Supabase Edge Function: supabase/functions/chat-with-gemini
  * ============================================================================
  */
 
 header('Content-Type: application/json');
 header('Access-Control-Allow-Origin: *');
 header('Access-Control-Allow-Methods: POST, OPTIONS');
 header('Access-Control-Allow-Headers: Content-Type, Authorization');
 
 // Handle preflight
 if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
     http_response_code(200);
     exit;
 }
 
 // Only allow POST
 if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
     http_response_code(405);
     echo json_encode(['error' => 'Method not allowed']);
     exit;
 }
 
 // Get Gemini API key from environment
 $geminiApiKey = getenv('GEMINI_API_KEY') ?: '';
 
 if (empty($geminiApiKey)) {
     http_response_code(500);
     echo json_encode(['error' => 'Gemini API key not configured']);
     exit;
 }
 
 // Get request data
 $input = json_decode(file_get_contents('php://input'), true);
 $message = $input['message'] ?? '';
 $context = $input['context'] ?? '';
 
 if (empty($message)) {
     http_response_code(400);
     echo json_encode(['error' => 'Message is required']);
     exit;
 }
 
 // Rate limiting (simple implementation)
 session_start();
 $rateLimitKey = 'chatbot_requests';
 $maxRequests = 10; // per minute
 $timeWindow = 60;
 
 if (!isset($_SESSION[$rateLimitKey])) {
     $_SESSION[$rateLimitKey] = ['count' => 0, 'start' => time()];
 }
 
 if (time() - $_SESSION[$rateLimitKey]['start'] > $timeWindow) {
     $_SESSION[$rateLimitKey] = ['count' => 0, 'start' => time()];
 }
 
 $_SESSION[$rateLimitKey]['count']++;
 
 if ($_SESSION[$rateLimitKey]['count'] > $maxRequests) {
     http_response_code(429);
     echo json_encode(['error' => 'Too many requests. Please try again later.']);
     exit;
 }
 
 // Build the prompt
 $systemPrompt = "You are a helpful dental assistant for Hardik Dental Practice. 
 You provide information about dental services, appointments, and general oral health advice.
 Be professional, friendly, and concise in your responses.
 If asked about specific medical advice, recommend consulting with a dentist.
 
 Practice Information:
 - Name: Hardik Dental Practice
 - Phone: (808) 095-0921
 - Hours: Monday-Saturday 9:00 AM - 6:00 PM, Saturday closes at 2:00 PM
 - Services: General dentistry, teeth cleaning, fillings, root canals, orthodontics, cosmetic dentistry, teeth whitening, emergency care
 - Location: Available on Google Maps
 
 " . ($context ? "Additional Context: $context" : "");
 
 // Call Gemini API
 $geminiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';
 
 $payload = [
     'contents' => [
         [
             'parts' => [
                 ['text' => $systemPrompt . "\n\nUser: " . $message]
             ]
         ]
     ],
     'generationConfig' => [
         'temperature' => 0.7,
         'topK' => 40,
         'topP' => 0.95,
         'maxOutputTokens' => 1024,
     ],
     'safetySettings' => [
         [
             'category' => 'HARM_CATEGORY_HARASSMENT',
             'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
         ],
         [
             'category' => 'HARM_CATEGORY_HATE_SPEECH',
             'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
         ],
         [
             'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
             'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
         ],
         [
             'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
             'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
         ]
     ]
 ];
 
 $ch = curl_init();
 curl_setopt_array($ch, [
     CURLOPT_URL => $geminiUrl . '?key=' . $geminiApiKey,
     CURLOPT_RETURNTRANSFER => true,
     CURLOPT_POST => true,
     CURLOPT_POSTFIELDS => json_encode($payload),
     CURLOPT_HTTPHEADER => [
         'Content-Type: application/json'
     ],
     CURLOPT_TIMEOUT => 30
 ]);
 
 $response = curl_exec($ch);
 $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
 $error = curl_error($ch);
 curl_close($ch);
 
 if ($error) {
     http_response_code(500);
     echo json_encode(['error' => 'Failed to connect to AI service']);
     exit;
 }
 
 if ($httpCode !== 200) {
     http_response_code(502);
     echo json_encode(['error' => 'AI service returned an error']);
     exit;
 }
 
 $data = json_decode($response, true);
 
 // Extract the response text
 $responseText = '';
 if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
     $responseText = $data['candidates'][0]['content']['parts'][0]['text'];
 } else {
     $responseText = 'I apologize, but I could not generate a response. Please try again or contact our office directly at (808) 095-0921.';
 }
 
 echo json_encode([
     'success' => true,
     'response' => $responseText
 ]);