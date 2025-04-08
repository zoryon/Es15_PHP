<?php
class DB {
    private static $instance = null;
    private $connection;
    private $error;

    private function __construct() {
        $host = 'my_mariadb';
        $db   = 'scuola';
        $user = 'root';
        $pass = 'ciccio';
        
        try {
            $this->connection = new mysqli($host, $user, $pass, $db);
            $this->connection->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            throw new mysqli_sql_exception($e->getMessage(), $e->getCode());
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new DB();
        }
        return self::$instance;
    }

    private function getType($var) {
        if (is_int($var)) return 'i';
        if (is_double($var)) return 'd';
        return 's';
    }

    public function execute($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            
            if (!empty($params)) {
                $types = array_reduce($params, function($carry, $item) {
                    return $carry . $this->getType($item);
                }, '');
                
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            return $stmt;
        } catch (mysqli_sql_exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    // SELECT multiple rows
    public function select($table, $conditions = [], $fetchMode = MYSQLI_ASSOC) {
        $sql = "SELECT * FROM $table";
        $params = [];
        
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                $where[] = "$key = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $stmt = $this->execute($sql, $params);
        return $stmt ? $stmt->get_result()->fetch_all($fetchMode) : false;
    }

    // SELECT single row
    public function selectOne($table, $conditions = [], $fetchMode = MYSQLI_ASSOC) {
        $sql = "SELECT * FROM $table";
        $params = [];
        
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                $where[] = "$key = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " LIMIT 1";
        
        $stmt = $this->execute($sql, $params);
        return $stmt ? $stmt->get_result()->fetch_array($fetchMode) : false;
    }

    // INSERT
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        
        $stmt = $this->execute($sql, array_values($data));
        return $stmt ? $this->connection->insert_id : false;
    }

    // UPDATE
    public function update($table, $data, $conditions) {
        $set = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $set[] = "$key = ?";
            $params[] = $value;
        }
        
        $where = [];
        foreach ($conditions as $key => $value) {
            $where[] = "$key = ?";
            $params[] = $value;
        }
        
        $sql = "UPDATE $table SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $where);
        $stmt = $this->execute($sql, $params);
        return $stmt ? $stmt->affected_rows : false;
    }

    // DELETE
    public function delete($table, $conditions) {
        $where = [];
        $params = [];
        
        foreach ($conditions as $key => $value) {
            $where[] = "$key = ?";
            $params[] = $value;
        }
        
        $sql = "DELETE FROM $table WHERE " . implode(' AND ', $where);
        $stmt = $this->execute($sql, $params);
        return $stmt ? $stmt->affected_rows : false;
    }

    public function getError() {
        return $this->error;
    }
}
?>