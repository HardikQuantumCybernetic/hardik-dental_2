<?php
/**
 * ============================================================================
 * Database Configuration for Oracle Connection
 * ============================================================================
 * Project: Dental Management System
 * Backend: PHP + Oracle
 * ============================================================================
 * 
 * ORIGINAL SUPABASE:
 * const SUPABASE_URL = "https://mmsmljkeedqfrbgsqipf.supabase.co";
 * const SUPABASE_PUBLISHABLE_KEY = "eyJhbGciOiJIUzI1NiIs...";
 * 
 * ORACLE EQUIVALENT:
 * OCI8 connection with TNS or Easy Connect string
 * ============================================================================
 */

// Prevent direct access
if (!defined('DENTAL_APP')) {
    die('Direct access not permitted');
}

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

define('DB_HOST', 'localhost');           // Oracle server hostname
define('DB_PORT', '1521');                // Oracle listener port
define('DB_SERVICE', 'ORCL');             // Oracle service name or SID
define('DB_USERNAME', 'dental_app');      // Database username
define('DB_PASSWORD', 'YourSecurePassword'); // Database password

// Connection string for Easy Connect
define('DB_CONNECTION_STRING', DB_HOST . ':' . DB_PORT . '/' . DB_SERVICE);

// Alternative: TNS connection (if using tnsnames.ora)
// define('DB_TNS_NAME', 'DENTAL_DB');

// ============================================================================
// APPLICATION CONFIGURATION
// ============================================================================

define('APP_NAME', 'Dental Management System');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development');  // development | production

// Session configuration
define('SESSION_LIFETIME', 86400);  // 24 hours in seconds
define('SESSION_NAME', 'dental_session');

// JWT configuration (for token-based auth)
define('JWT_SECRET', 'your-256-bit-secret-key-change-in-production');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 86400);  // 24 hours

// ============================================================================
// CORS CONFIGURATION
// ============================================================================

define('CORS_ALLOWED_ORIGINS', [
    'http://localhost:8080',
    'http://localhost:3000',
    'https://hardik306-72.lovable.app'
]);

define('CORS_ALLOWED_METHODS', 'GET, POST, PUT, DELETE, OPTIONS');
define('CORS_ALLOWED_HEADERS', 'Content-Type, Authorization, X-Requested-With');

// ============================================================================
// ERROR REPORTING
// ============================================================================

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// ============================================================================
// TIMEZONE
// ============================================================================

date_default_timezone_set('Asia/Kolkata');
