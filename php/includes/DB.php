<?php
class DB extends mysqli {
    // Attributi
    private $status = 200;
    private $headers = [];
    private $response = [];
    private static $instance = null;
    private $transactionActive = false;

    // Costruttore privato
    private function __construct() {
        parent::__construct("my_mariadb", "root", "ciccio", "scuola");
        
        if ($this->connect_error) {
            $this->handleError("Database connection failed: " . $this->connect_error, 500);
        }
        
        $this->setDefaultHeaders();
    }

    // Singleton
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Metodi per la gestione delle response HTTP
    private function setDefaultHeaders(): void {
        $this->headers = ["Content-Type" => "application/json"];
    }

    public function setStatus(int $status) {
        $this->status = $status;
        return $this;
    }

    public function addHeader(string $name, string $value) {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setResponse(array $data) {
        $this->response = $data;
        return $this;
    }

    // Metodi per la costruzione delle query
    public function select(string $table, array $columns = ['*'], array $where = [], array $orderBy = [], int $limit = null, array $joins = []): array {
        // Costruzione colonne
        $columnsStr = implode(', ', array_map(function($col) { 
            return "`$col`"; 
        }, $columns));

        // Costruzione JOIN
        $joinClause = '';
        foreach ($joins as $join) {
            $type = strtoupper($join['type'] ?? 'INNER');
            $joinTable = $join['table'];
            $on = $join['on'];
            $joinClause .= " $type JOIN `$joinTable` ON $on";
        }

        // Base SQL
        $sql = "SELECT $columnsStr FROM `$table`$joinClause";

        // WHERE
        list($whereSql, $params, $types) = $this->buildWhereClause($where);
        if ($whereSql) $sql .= " WHERE $whereSql";

        // ORDER BY
        if (!empty($orderBy)) {
            $order = [];
            foreach ($orderBy as $col => $dir) {
                $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
                $order[] = "`$col` $dir";
            }
            $sql .= " ORDER BY " . implode(', ', $order);
        }

        // LIMIT
        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
            $types .= 'i';
        }

        return $this->executeQuery($sql, $types, $params);
    }

    public function insertInto(string $table, array $data): int {
        $columns = array_keys($data);
        $values = array_values($data);
        
        $columnsStr = implode(', ', array_map(function($col) { 
            return "`$col`"; 
        }, $columns));
        
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        
        $sql = "INSERT INTO `$table` ($columnsStr) VALUES ($placeholders)";
        $types = $this->determineTypes($values);
        
        return $this->insert($sql, $types, $values);
    }

    public function updateWhere(string $table, array $data, array $where): int {
        // SET
        $set = [];
        $params = [];
        $types = '';
        foreach ($data as $col => $val) {
            $set[] = "`$col` = ?";
            $params[] = $val;
            $types .= $this->determineType($val);
        }
        $sql = "UPDATE `$table` SET " . implode(', ', $set);

        // WHERE
        list($whereSql, $whereParams, $whereTypes) = $this->buildWhereClause($where);
        if ($whereSql) {
            $sql .= " WHERE $whereSql";
            $params = array_merge($params, $whereParams);
            $types .= $whereTypes;
        }

        return $this->update($sql, $types, $params);
    }

    public function deleteWhere(string $table, array $where): int {
        $sql = "DELETE FROM `$table`";
        list($whereSql, $params, $types) = $this->buildWhereClause($where);
        
        if ($whereSql) $sql .= " WHERE $whereSql";
        
        return $this->delete($sql, $types, $params);
    }

    public function insertBatch(string $table, array $data): int {
        if (empty($data)) return 0;
        
        $columns = array_keys($data[0]);
        $columnsStr = implode(', ', array_map(function($col) { 
            return "`$col`"; 
        }, $columns));
        
        $placeholders = [];
        $params = [];
        $types = '';
        
        foreach ($data as $row) {
            $values = array_values($row);
            $placeholders[] = '(' . implode(', ', array_fill(0, count($values), '?')) . ')';
            $params = array_merge($params, $values);
            $types .= $this->determineTypes($values);
        }
        
        $sql = "INSERT INTO `$table` ($columnsStr) VALUES " . implode(', ', $placeholders);
        return $this->insert($sql, $types, $params);
    }

    // Metodi transazionali
    public function beginTransaction(): bool {
        $this->transactionActive = true;
        return parent::begin_transaction();
    }

    public function commitTransaction(): bool {
        $this->transactionActive = false;
        return parent::commit();
    }

    public function rollbackTransaction(): bool {
        $this->transactionActive = false;
        return parent::rollback();
    }

    // Metodi di utilità
    private function buildWhereClause(array $where): array {
        $clauses = [];
        $params = [];
        $types = '';
        
        foreach ($where as $key => $value) {
            if (is_array($value)) {
                $clauses[] = "`$key` IN (" . implode(', ', array_fill(0, count($value), '?')) . ")";
                $params = array_merge($params, $value);
                $types .= $this->determineTypes($value);
            } elseif ($value === null) {
                $clauses[] = "`$key` IS NULL";
            } else {
                $clauses[] = "`$key` = ?";
                $params[] = $value;
                $types .= $this->determineType($value);
            }
        }
        
        return [implode(' AND ', $clauses), $params, $types];
    }

    private function determineType($value): string {
        switch (gettype($value)) {
            case 'integer': return 'i';
            case 'double':  return 'd';
            case 'string':  return 's';
            default:        return 'b';
        }
    }

    private function determineTypes(array $values): string {
        return array_reduce($values, function($carry, $item) {
            return $carry . $this->determineType($item);
        }, '');
    }

    // Gestione errori
    private function handleError(string $message, int $status = 500): void {
        if ($this->transactionActive) {
            $this->rollbackTransaction();
        }
        
        $this->setStatus($status)
            ->setResponse(["error" => $message])
            ->send();
    }

    public function send(): void {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
        }
        
        echo json_encode($this->response);
        exit();
    }

    // Metodi originali mantenuti per compatibilità
    public function executeQuery(string $sql, string $types = "", array $params = []): array {
        $stmt = $this->prepare($sql);
        if (!$stmt) $this->handleError("Query preparation failed: " . $this->error, 500);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) $this->handleError("Query failed: " . $stmt->error, 500);
        
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        
        $stmt->close();
        return $data;
    }

    public function insert(string $sql, string $types = "", array $params = []): int {
        $stmt = $this->prepare($sql);
        if (!$stmt) $this->handleError("Insert failed: " . $this->error, 500);
        
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) $this->handleError("Insert failed: " . $stmt->error, 500);
        
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function update(string $sql, string $types = "", array $params = []): int {
        $stmt = $this->prepare($sql);
        if (!$stmt) $this->handleError("Update failed: " . $this->error, 500);
        
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) $this->handleError("Update failed: " . $stmt->error, 500);
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    public function delete(string $sql, string $types = "", array $params = []): int {
        return $this->update($sql, $types, $params);
    }
}