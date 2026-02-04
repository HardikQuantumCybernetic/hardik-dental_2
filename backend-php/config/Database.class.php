<?php
/**
 * ============================================================================
 * Oracle Database Connection Class
 * ============================================================================
 * Replaces Supabase client SDK with OCI8 connection
 * 
 * ORIGINAL SUPABASE:
 * import { supabase } from "@/integrations/supabase/client";
 * const { data, error } = await supabase.from('table').select('*');
 * 
 * ORACLE EQUIVALENT:
 * $db = Database::getInstance();
 * $result = $db->query("SELECT * FROM table");
 * ============================================================================
 */

define('DENTAL_APP', true);
require_once __DIR__ . '/../config/database.php';

class Database {
    private static $instance = null;
    private $connection = null;
    private $lastError = null;
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establish Oracle connection
     * 
     * ORIGINAL SUPABASE:
     * export const supabase = createClient<Database>(SUPABASE_URL, SUPABASE_PUBLISHABLE_KEY, {
     *   auth: { storage: localStorage, persistSession: true, autoRefreshToken: true }
     * });
     */
    private function connect(): void {
        try {
            // OCI8 connection
            $this->connection = oci_connect(
                DB_USERNAME,
                DB_PASSWORD,
                DB_CONNECTION_STRING,
                'AL32UTF8'  // Character set
            );
            
            if (!$this->connection) {
                $error = oci_error();
                throw new Exception("Oracle connection failed: " . $error['message']);
            }
            
            // Set session parameters
            $this->execute("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD'");
            $this->execute("ALTER SESSION SET NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("Database connection error: " . $e->getMessage());
        }
    }
    
    /**
     * Get connection resource
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Execute a query and return results
     * 
     * ORIGINAL SUPABASE:
     * const { data, error } = await supabase.from('patients').select('*');
     * 
     * ORACLE EQUIVALENT:
     * $result = $db->query("SELECT * FROM patients");
     */
    public function query(string $sql, array $params = []): array {
        $result = [
            'data' => null,
            'error' => null,
            'count' => 0
        ];
        
        try {
            $statement = oci_parse($this->connection, $sql);
            
            if (!$statement) {
                $error = oci_error($this->connection);
                throw new Exception("Parse error: " . $error['message']);
            }
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $bindKey = ':' . ltrim($key, ':');
                oci_bind_by_name($statement, $bindKey, $params[$key], -1);
            }
            
            // Execute
            $success = oci_execute($statement, OCI_DEFAULT);
            
            if (!$success) {
                $error = oci_error($statement);
                throw new Exception("Execute error: " . $error['message']);
            }
            
            // Fetch results
            $rows = [];
            while ($row = oci_fetch_assoc($statement)) {
                // Convert keys to lowercase for consistency
                $rows[] = array_change_key_case($row, CASE_LOWER);
            }
            
            $result['data'] = $rows;
            $result['count'] = count($rows);
            
            oci_free_statement($statement);
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            $this->lastError = $e->getMessage();
            error_log("Query error: " . $e->getMessage() . " | SQL: " . $sql);
        }
        
        return $result;
    }
    
    /**
     * Execute a statement without returning results (INSERT, UPDATE, DELETE)
     */
    public function execute(string $sql, array $params = []): array {
        $result = [
            'success' => false,
            'error' => null,
            'affected_rows' => 0
        ];
        
        try {
            $statement = oci_parse($this->connection, $sql);
            
            if (!$statement) {
                $error = oci_error($this->connection);
                throw new Exception("Parse error: " . $error['message']);
            }
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $bindKey = ':' . ltrim($key, ':');
                oci_bind_by_name($statement, $bindKey, $params[$key], -1);
            }
            
            $success = oci_execute($statement, OCI_COMMIT_ON_SUCCESS);
            
            if (!$success) {
                $error = oci_error($statement);
                throw new Exception("Execute error: " . $error['message']);
            }
            
            $result['success'] = true;
            $result['affected_rows'] = oci_num_rows($statement);
            
            oci_free_statement($statement);
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            $this->lastError = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Execute INSERT and return the new ID
     * 
     * ORIGINAL SUPABASE:
     * const { data, error } = await supabase.from('patients')
     *   .insert({ name: 'John' })
     *   .select()
     *   .single();
     */
    public function insert(string $table, array $data, string $idColumn = 'id'): array {
        $result = [
            'data' => null,
            'error' => null,
            'id' => null
        ];
        
        try {
            $columns = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders}) 
                    RETURNING {$idColumn} INTO :new_id";
            
            $statement = oci_parse($this->connection, $sql);
            
            // Bind data values
            foreach ($data as $key => $value) {
                $bindValue = $value;
                oci_bind_by_name($statement, ':' . $key, $bindValue, -1);
            }
            
            // Bind return value
            $newId = 0;
            oci_bind_by_name($statement, ':new_id', $newId, 32);
            
            $success = oci_execute($statement, OCI_COMMIT_ON_SUCCESS);
            
            if (!$success) {
                $error = oci_error($statement);
                throw new Exception("Insert error: " . $error['message']);
            }
            
            $result['id'] = $newId;
            $result['data'] = array_merge($data, [$idColumn => $newId]);
            
            oci_free_statement($statement);
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Execute UPDATE
     * 
     * ORIGINAL SUPABASE:
     * const { data, error } = await supabase.from('patients')
     *   .update({ name: 'Jane' })
     *   .eq('id', 1);
     */
    public function update(string $table, array $data, array $where): array {
        $result = [
            'success' => false,
            'error' => null,
            'affected_rows' => 0
        ];
        
        try {
            $setParts = [];
            foreach (array_keys($data) as $key) {
                $setParts[] = "{$key} = :set_{$key}";
            }
            $setClause = implode(', ', $setParts);
            
            $whereParts = [];
            foreach (array_keys($where) as $key) {
                $whereParts[] = "{$key} = :where_{$key}";
            }
            $whereClause = implode(' AND ', $whereParts);
            
            $sql = "UPDATE {$table} SET {$setClause} WHERE {$whereClause}";
            
            $statement = oci_parse($this->connection, $sql);
            
            // Bind SET values
            foreach ($data as $key => $value) {
                $bindValue = $value;
                oci_bind_by_name($statement, ':set_' . $key, $bindValue, -1);
            }
            
            // Bind WHERE values
            foreach ($where as $key => $value) {
                $bindValue = $value;
                oci_bind_by_name($statement, ':where_' . $key, $bindValue, -1);
            }
            
            $success = oci_execute($statement, OCI_COMMIT_ON_SUCCESS);
            
            if (!$success) {
                $error = oci_error($statement);
                throw new Exception("Update error: " . $error['message']);
            }
            
            $result['success'] = true;
            $result['affected_rows'] = oci_num_rows($statement);
            
            oci_free_statement($statement);
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Execute DELETE
     * 
     * ORIGINAL SUPABASE:
     * const { error } = await supabase.from('patients')
     *   .delete()
     *   .eq('id', 1);
     */
    public function delete(string $table, array $where): array {
        $result = [
            'success' => false,
            'error' => null,
            'affected_rows' => 0
        ];
        
        try {
            $whereParts = [];
            foreach (array_keys($where) as $key) {
                $whereParts[] = "{$key} = :{$key}";
            }
            $whereClause = implode(' AND ', $whereParts);
            
            $sql = "DELETE FROM {$table} WHERE {$whereClause}";
            
            $statement = oci_parse($this->connection, $sql);
            
            foreach ($where as $key => $value) {
                $bindValue = $value;
                oci_bind_by_name($statement, ':' . $key, $bindValue, -1);
            }
            
            $success = oci_execute($statement, OCI_COMMIT_ON_SUCCESS);
            
            if (!$success) {
                $error = oci_error($statement);
                throw new Exception("Delete error: " . $error['message']);
            }
            
            $result['success'] = true;
            $result['affected_rows'] = oci_num_rows($statement);
            
            oci_free_statement($statement);
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Call a stored procedure
     * 
     * ORIGINAL SUPABASE:
     * const { data, error } = await supabase.rpc('function_name', { param1: value });
     * 
     * ORACLE EQUIVALENT:
     * $result = $db->callProcedure('pkg_patients.create_patient', [...]);
     */
    public function callProcedure(string $procedure, array $inParams = [], array &$outParams = []): array {
        $result = [
            'success' => false,
            'error' => null,
            'out' => []
        ];
        
        try {
            // Build parameter list
            $allParams = [];
            foreach (array_keys($inParams) as $key) {
                $allParams[] = ':' . $key;
            }
            foreach (array_keys($outParams) as $key) {
                $allParams[] = ':' . $key;
            }
            
            $paramList = implode(', ', $allParams);
            $sql = "BEGIN {$procedure}({$paramList}); END;";
            
            $statement = oci_parse($this->connection, $sql);
            
            // Bind IN parameters
            foreach ($inParams as $key => $value) {
                $bindValue = $value;
                oci_bind_by_name($statement, ':' . $key, $bindValue, -1);
            }
            
            // Bind OUT parameters
            foreach ($outParams as $key => &$value) {
                oci_bind_by_name($statement, ':' . $key, $value, 4000);
            }
            
            $success = oci_execute($statement, OCI_COMMIT_ON_SUCCESS);
            
            if (!$success) {
                $error = oci_error($statement);
                throw new Exception("Procedure error: " . $error['message']);
            }
            
            $result['success'] = true;
            $result['out'] = $outParams;
            
            oci_free_statement($statement);
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Call a function that returns a cursor
     */
    public function callFunctionCursor(string $function, array $params = []): array {
        $result = [
            'data' => null,
            'error' => null
        ];
        
        try {
            $paramList = [];
            foreach (array_keys($params) as $key) {
                $paramList[] = ':' . $key;
            }
            $paramString = implode(', ', $paramList);
            
            $sql = "BEGIN :cursor := {$function}({$paramString}); END;";
            
            $statement = oci_parse($this->connection, $sql);
            
            // Create cursor
            $cursor = oci_new_cursor($this->connection);
            oci_bind_by_name($statement, ':cursor', $cursor, -1, OCI_B_CURSOR);
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $bindValue = $value;
                oci_bind_by_name($statement, ':' . $key, $bindValue, -1);
            }
            
            oci_execute($statement);
            oci_execute($cursor);
            
            // Fetch results
            $rows = [];
            while ($row = oci_fetch_assoc($cursor)) {
                $rows[] = array_change_key_case($row, CASE_LOWER);
            }
            
            $result['data'] = $rows;
            
            oci_free_statement($cursor);
            oci_free_statement($statement);
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction(): void {
        // Oracle uses implicit transactions, no explicit BEGIN needed
        // But we can set savepoint
        oci_execute(
            oci_parse($this->connection, 'SAVEPOINT transaction_start'),
            OCI_DEFAULT
        );
    }
    
    /**
     * Commit transaction
     */
    public function commit(): bool {
        return oci_commit($this->connection);
    }
    
    /**
     * Rollback transaction
     */
    public function rollback(): bool {
        return oci_rollback($this->connection);
    }
    
    /**
     * Get last error
     */
    public function getLastError(): ?string {
        return $this->lastError;
    }
    
    /**
     * Close connection
     */
    public function close(): void {
        if ($this->connection) {
            oci_close($this->connection);
            $this->connection = null;
        }
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
}

// ============================================================================
// QUERY BUILDER CLASS (Mimics Supabase SDK syntax)
// ============================================================================

/**
 * QueryBuilder - Provides Supabase-like fluent interface
 * 
 * ORIGINAL SUPABASE:
 * const { data } = await supabase
 *   .from('patients')
 *   .select('*')
 *   .eq('status', 'active')
 *   .order('created_at', { ascending: false });
 * 
 * ORACLE EQUIVALENT:
 * $data = QueryBuilder::table('patients')
 *   ->select('*')
 *   ->eq('status', 'active')
 *   ->order('created_at', 'desc')
 *   ->get();
 */
class QueryBuilder {
    private $db;
    private $table;
    private $selectColumns = '*';
    private $whereClauses = [];
    private $whereParams = [];
    private $orderBy = [];
    private $limit = null;
    private $offset = null;
    
    public function __construct(string $table) {
        $this->db = Database::getInstance();
        $this->table = $table;
    }
    
    public static function table(string $table): QueryBuilder {
        return new self($table);
    }
    
    public function select(string $columns = '*'): QueryBuilder {
        $this->selectColumns = $columns;
        return $this;
    }
    
    public function eq(string $column, $value): QueryBuilder {
        $paramKey = 'p' . count($this->whereParams);
        $this->whereClauses[] = "{$column} = :{$paramKey}";
        $this->whereParams[$paramKey] = $value;
        return $this;
    }
    
    public function neq(string $column, $value): QueryBuilder {
        $paramKey = 'p' . count($this->whereParams);
        $this->whereClauses[] = "{$column} != :{$paramKey}";
        $this->whereParams[$paramKey] = $value;
        return $this;
    }
    
    public function gt(string $column, $value): QueryBuilder {
        $paramKey = 'p' . count($this->whereParams);
        $this->whereClauses[] = "{$column} > :{$paramKey}";
        $this->whereParams[$paramKey] = $value;
        return $this;
    }
    
    public function gte(string $column, $value): QueryBuilder {
        $paramKey = 'p' . count($this->whereParams);
        $this->whereClauses[] = "{$column} >= :{$paramKey}";
        $this->whereParams[$paramKey] = $value;
        return $this;
    }
    
    public function lt(string $column, $value): QueryBuilder {
        $paramKey = 'p' . count($this->whereParams);
        $this->whereClauses[] = "{$column} < :{$paramKey}";
        $this->whereParams[$paramKey] = $value;
        return $this;
    }
    
    public function lte(string $column, $value): QueryBuilder {
        $paramKey = 'p' . count($this->whereParams);
        $this->whereClauses[] = "{$column} <= :{$paramKey}";
        $this->whereParams[$paramKey] = $value;
        return $this;
    }
    
    public function like(string $column, string $pattern): QueryBuilder {
        $paramKey = 'p' . count($this->whereParams);
        $this->whereClauses[] = "UPPER({$column}) LIKE UPPER(:{$paramKey})";
        $this->whereParams[$paramKey] = $pattern;
        return $this;
    }
    
    public function in(string $column, array $values): QueryBuilder {
        $placeholders = [];
        foreach ($values as $i => $value) {
            $paramKey = 'p' . count($this->whereParams);
            $placeholders[] = ':' . $paramKey;
            $this->whereParams[$paramKey] = $value;
        }
        $this->whereClauses[] = "{$column} IN (" . implode(', ', $placeholders) . ")";
        return $this;
    }
    
    public function isNull(string $column): QueryBuilder {
        $this->whereClauses[] = "{$column} IS NULL";
        return $this;
    }
    
    public function isNotNull(string $column): QueryBuilder {
        $this->whereClauses[] = "{$column} IS NOT NULL";
        return $this;
    }
    
    public function order(string $column, string $direction = 'asc'): QueryBuilder {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy[] = "{$column} {$dir}";
        return $this;
    }
    
    public function limit(int $limit): QueryBuilder {
        $this->limit = $limit;
        return $this;
    }
    
    public function offset(int $offset): QueryBuilder {
        $this->offset = $offset;
        return $this;
    }
    
    /**
     * Execute SELECT and return all results
     */
    public function get(): array {
        $sql = "SELECT {$this->selectColumns} FROM {$this->table}";
        
        if (!empty($this->whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $this->whereClauses);
        }
        
        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }
        
        // Oracle pagination using ROWNUM or FETCH (12c+)
        if ($this->limit !== null) {
            if ($this->offset !== null) {
                $sql .= " OFFSET {$this->offset} ROWS FETCH NEXT {$this->limit} ROWS ONLY";
            } else {
                $sql .= " FETCH FIRST {$this->limit} ROWS ONLY";
            }
        }
        
        return $this->db->query($sql, $this->whereParams);
    }
    
    /**
     * Execute SELECT and return single result
     */
    public function single(): array {
        $this->limit = 1;
        $result = $this->get();
        
        if ($result['error']) {
            return $result;
        }
        
        return [
            'data' => $result['data'][0] ?? null,
            'error' => null
        ];
    }
    
    /**
     * Get count only
     */
    public function count(): array {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        
        if (!empty($this->whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $this->whereClauses);
        }
        
        $result = $this->db->query($sql, $this->whereParams);
        
        return [
            'count' => $result['data'][0]['count'] ?? 0,
            'error' => $result['error']
        ];
    }
    
    /**
     * Insert data
     */
    public function insert(array $data): array {
        return $this->db->insert($this->table, $data);
    }
    
    /**
     * Update data
     */
    public function update(array $data): array {
        $where = [];
        foreach ($this->whereParams as $key => $value) {
            // Extract column name from where clauses
            foreach ($this->whereClauses as $clause) {
                if (strpos($clause, ":{$key}") !== false) {
                    preg_match('/(\w+)\s*=/', $clause, $matches);
                    if (isset($matches[1])) {
                        $where[$matches[1]] = $value;
                    }
                }
            }
        }
        return $this->db->update($this->table, $data, $where);
    }
    
    /**
     * Delete data
     */
    public function delete(): array {
        $where = [];
        foreach ($this->whereParams as $key => $value) {
            foreach ($this->whereClauses as $clause) {
                if (strpos($clause, ":{$key}") !== false) {
                    preg_match('/(\w+)\s*=/', $clause, $matches);
                    if (isset($matches[1])) {
                        $where[$matches[1]] = $value;
                    }
                }
            }
        }
        return $this->db->delete($this->table, $where);
    }
}
