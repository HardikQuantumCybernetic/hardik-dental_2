<?php
/**
 * ============================================================================
 * Authentication Service
 * ============================================================================
 * Replaces Supabase Auth with PHP session/JWT-based authentication
 * 
 * ORIGINAL SUPABASE:
 * const { data, error } = await supabase.auth.signInWithPassword({
 *   email: 'user@example.com',
 *   password: 'password'
 * });
 * 
 * PHP EQUIVALENT:
 * $auth = new AuthService();
 * $result = $auth->signIn($email, $password);
 * ============================================================================
 */

define('DENTAL_APP', true);
require_once __DIR__ . '/../config/Database.class.php';

class AuthService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Register a new user
     * 
     * ORIGINAL SUPABASE:
     * const { data, error } = await supabase.auth.signUp({
     *   email: 'user@example.com',
     *   password: 'password',
     *   options: { emailRedirectTo: window.location.origin }
     * });
     */
    public function signUp(string $email, string $password, string $role = 'patient'): array {
        $result = [
            'user' => null,
            'session' => null,
            'error' => null
        ];
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result['error'] = 'Invalid email format';
            return $result;
        }
        
        // Validate password (minimum 6 characters)
        if (strlen($password) < 6) {
            $result['error'] = 'Password must be at least 6 characters';
            return $result;
        }
        
        // Check if email already exists
        $existing = QueryBuilder::table('users')
            ->select('id')
            ->eq('email', strtolower($email))
            ->single();
        
        if ($existing['data']) {
            $result['error'] = 'Email already registered';
            return $result;
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        try {
            $this->db->beginTransaction();
            
            // Insert user
            $userResult = $this->db->insert('users', [
                'email' => strtolower($email),
                'password_hash' => $passwordHash,
                'email_verified' => 0
            ]);
            
            if ($userResult['error']) {
                throw new Exception($userResult['error']);
            }
            
            $userId = $userResult['id'];
            
            // Assign role
            $this->db->insert('user_roles', [
                'user_id' => $userId,
                'role' => $role
            ]);
            
            $this->db->commit();
            
            // Create session
            $session = $this->createSession($userId);
            
            $result['user'] = [
                'id' => $userId,
                'email' => $email,
                'role' => $role
            ];
            $result['session'] = $session;
            
        } catch (Exception $e) {
            $this->db->rollback();
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Sign in user
     * 
     * ORIGINAL SUPABASE:
     * const { data, error } = await supabase.auth.signInWithPassword({
     *   email: 'user@example.com',
     *   password: 'password'
     * });
     */
    public function signIn(string $email, string $password): array {
        $result = [
            'user' => null,
            'session' => null,
            'error' => null
        ];
        
        // Find user
        $userResult = QueryBuilder::table('users')
            ->select('id, email, password_hash')
            ->eq('email', strtolower($email))
            ->single();
        
        if ($userResult['error'] || !$userResult['data']) {
            $result['error'] = 'Invalid email or password';
            return $result;
        }
        
        $user = $userResult['data'];
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $result['error'] = 'Invalid email or password';
            return $result;
        }
        
        // Get user role
        $roleResult = QueryBuilder::table('user_roles')
            ->select('role')
            ->eq('user_id', $user['id'])
            ->single();
        
        $role = $roleResult['data']['role'] ?? 'patient';
        
        // Update last sign in
        $this->db->update('users', 
            ['last_sign_in' => date('Y-m-d H:i:s')],
            ['id' => $user['id']]
        );
        
        // Create session
        $session = $this->createSession($user['id']);
        
        $result['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $role
        ];
        $result['session'] = $session;
        
        return $result;
    }
    
    /**
     * Sign out user
     * 
     * ORIGINAL SUPABASE:
     * await supabase.auth.signOut();
     */
    public function signOut(string $token): array {
        $result = [
            'success' => false,
            'error' => null
        ];
        
        try {
            // Delete session from database
            $this->db->delete('user_sessions', ['token' => $token]);
            
            // Destroy PHP session
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            
            $result['success'] = true;
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Get current session
     * 
     * ORIGINAL SUPABASE:
     * const { data: { session }, error } = await supabase.auth.getSession();
     */
    public function getSession(string $token): array {
        $result = [
            'session' => null,
            'user' => null,
            'error' => null
        ];
        
        // Validate token
        $sessionResult = QueryBuilder::table('user_sessions')
            ->select('user_id, expires_at')
            ->eq('token', $token)
            ->single();
        
        if ($sessionResult['error'] || !$sessionResult['data']) {
            $result['error'] = 'Invalid or expired session';
            return $result;
        }
        
        $session = $sessionResult['data'];
        
        // Check expiration
        if (strtotime($session['expires_at']) < time()) {
            // Clean up expired session
            $this->db->delete('user_sessions', ['token' => $token]);
            $result['error'] = 'Session expired';
            return $result;
        }
        
        // Get user
        $userResult = QueryBuilder::table('users')
            ->select('id, email')
            ->eq('id', $session['user_id'])
            ->single();
        
        if ($userResult['error'] || !$userResult['data']) {
            $result['error'] = 'User not found';
            return $result;
        }
        
        // Get role
        $roleResult = QueryBuilder::table('user_roles')
            ->select('role')
            ->eq('user_id', $session['user_id'])
            ->single();
        
        $result['session'] = [
            'token' => $token,
            'expires_at' => $session['expires_at']
        ];
        $result['user'] = [
            'id' => $userResult['data']['id'],
            'email' => $userResult['data']['email'],
            'role' => $roleResult['data']['role'] ?? 'patient'
        ];
        
        return $result;
    }
    
    /**
     * Check if user has a specific role
     * 
     * ORIGINAL SUPABASE:
     * const { data } = await supabase
     *   .from('user_roles')
     *   .select('role')
     *   .eq('user_id', userId)
     *   .eq('role', 'admin')
     *   .single();
     */
    public function hasRole(int $userId, string $role): bool {
        $result = QueryBuilder::table('user_roles')
            ->select('id')
            ->eq('user_id', $userId)
            ->eq('role', $role)
            ->single();
        
        return $result['data'] !== null;
    }
    
    /**
     * Check if user is healthcare provider
     * 
     * ORIGINAL SUPABASE RPC:
     * is_healthcare_provider(auth.uid())
     */
    public function isHealthcareProvider(int $userId): bool {
        $result = QueryBuilder::table('user_roles')
            ->select('id')
            ->eq('user_id', $userId)
            ->in('role', ['admin', 'doctor', 'staff'])
            ->single();
        
        return $result['data'] !== null;
    }
    
    /**
     * Create session and generate token
     */
    private function createSession(int $userId): array {
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        
        // Set expiration (24 hours)
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        
        // Store session
        $this->db->insert('user_sessions', [
            'id' => bin2hex(random_bytes(16)),
            'user_id' => $userId,
            'token' => $token,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'expires_at' => $expiresAt
        ]);
        
        // Start PHP session
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['token'] = $token;
        
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_at' => $expiresAt,
            'expires_in' => SESSION_LIFETIME
        ];
    }
    
    /**
     * Refresh session
     * 
     * ORIGINAL SUPABASE:
     * Auto-handled by Supabase client with autoRefreshToken: true
     */
    public function refreshSession(string $token): array {
        $session = $this->getSession($token);
        
        if ($session['error']) {
            return $session;
        }
        
        // Delete old session
        $this->db->delete('user_sessions', ['token' => $token]);
        
        // Create new session
        $newSession = $this->createSession($session['user']['id']);
        
        return [
            'session' => $newSession,
            'user' => $session['user'],
            'error' => null
        ];
    }
    
    /**
     * Reset password request
     */
    public function resetPasswordRequest(string $email): array {
        $result = [
            'success' => false,
            'error' => null
        ];
        
        // Find user
        $userResult = QueryBuilder::table('users')
            ->select('id, email')
            ->eq('email', strtolower($email))
            ->single();
        
        if (!$userResult['data']) {
            // Don't reveal if email exists
            $result['success'] = true;
            return $result;
        }
        
        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        // Store reset token (you'd need a password_resets table)
        // For now, we'll just return success
        // In production, send email with reset link
        
        $result['success'] = true;
        return $result;
    }
    
    /**
     * Update password
     */
    public function updatePassword(int $userId, string $newPassword): array {
        $result = [
            'success' => false,
            'error' => null
        ];
        
        if (strlen($newPassword) < 6) {
            $result['error'] = 'Password must be at least 6 characters';
            return $result;
        }
        
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $updateResult = $this->db->update('users',
            ['password_hash' => $passwordHash],
            ['id' => $userId]
        );
        
        if ($updateResult['error']) {
            $result['error'] = $updateResult['error'];
        } else {
            $result['success'] = true;
        }
        
        return $result;
    }
}

// ============================================================================
// MIDDLEWARE: Authentication Check
// ============================================================================

/**
 * Middleware to verify authentication
 * 
 * ORIGINAL SUPABASE:
 * Supabase RLS policies automatically check auth.uid()
 * 
 * PHP EQUIVALENT:
 * requireAuth() called at start of protected endpoints
 */
function requireAuth(): ?array {
    // Check for Authorization header
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($authHeader)) {
        http_response_code(401);
        echo json_encode(['error' => 'No authorization token provided']);
        exit;
    }
    
    // Extract token
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid authorization header format']);
        exit;
    }
    
    // Validate session
    $auth = new AuthService();
    $session = $auth->getSession($token);
    
    if ($session['error']) {
        http_response_code(401);
        echo json_encode(['error' => $session['error']]);
        exit;
    }
    
    return $session['user'];
}

/**
 * Middleware to require specific role
 */
function requireRole(string $role): ?array {
    $user = requireAuth();
    
    $auth = new AuthService();
    if (!$auth->hasRole($user['id'], $role)) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        exit;
    }
    
    return $user;
}

/**
 * Middleware to require healthcare provider role
 */
function requireHealthcareProvider(): ?array {
    $user = requireAuth();
    
    $auth = new AuthService();
    if (!$auth->isHealthcareProvider($user['id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Healthcare provider access required']);
        exit;
    }
    
    return $user;
}
