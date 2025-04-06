<?php

/**
 * Clase para gestionar la conexión a la base de datos
 */
class Database
{
    private static $instance = null;
    private $connection;
    private $error;

    /**
     * Constructor privado (patrón Singleton)
     */
    private function __construct()
    {
        // Configuración de la base de datos
        $host = 'localhost';
        $user = 'root';        // Cambiar en producción
        $pass = '';            // Cambiar en producción
        $name = 'sistema_recursos';
        $charset = 'utf8mb4';

        try {
            // Crear conexión
            $this->connection = new mysqli($host, $user, $pass, $name);
            $this->connection->set_charset($charset);

            // Verificar conexión
            if ($this->connection->connect_error) {
                throw new Exception("Error de conexión: " . $this->connection->connect_error);
            }
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            error_log($this->error);
        }
    }

    /**
     * Obtener instancia única de la base de datos (Singleton)
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtener la conexión a la base de datos
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Ejecutar una consulta SQL
     */
    public function query($sql, $params = [])
    {
        try {
            // Si no hay parámetros, ejecutar consulta directa
            if (empty($params)) {
                $result = $this->connection->query($sql);

                if ($result === false) {
                    throw new Exception("Error en la consulta: " . $this->connection->error);
                }

                return $result;
            }

            // Preparar la consulta con parámetros
            $stmt = $this->connection->prepare($sql);

            if ($stmt === false) {
                throw new Exception("Error al preparar la consulta: " . $this->connection->error);
            }

            // Determinar los tipos de datos para bind_param
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
            }

            // Añadir los parámetros al bind_param
            $bindParams = array_merge([$types], $params);
            $tmp = [];
            foreach ($bindParams as $key => $value) {
                $tmp[$key] = &$bindParams[$key];
            }

            call_user_func_array([$stmt, 'bind_param'], $tmp);

            // Ejecutar la consulta
            if (!$stmt->execute()) {
                throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
            }

            // Obtener el resultado
            $result = $stmt->get_result();
            $stmt->close();

            return $result;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            error_log($this->error);
            return false;
        }
    }

    /**
     * Obtener un único registro de la base de datos
     */
    public function getRow($sql, $params = [])
    {
        $result = $this->query($sql, $params);

        if ($result === false) {
            return false;
        }

        $row = $result->fetch_assoc();
        $result->free();

        return $row;
    }

    /**
     * Obtener múltiples registros de la base de datos
     */
    public function getRows($sql, $params = [])
    {
        $result = $this->query($sql, $params);

        if ($result === false) {
            return false;
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $result->free();

        return $rows;
    }

    /**
     * Insertar un registro en la base de datos
     */
    public function insert($table, $data)
    {
        try {
            $fields = array_keys($data);
            $placeholders = array_fill(0, count($fields), '?');

            $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";

            if (!$this->query($sql, array_values($data))) {
                return false;
            }

            return $this->connection->insert_id;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            error_log($this->error);
            return false;
        }
    }

    /**
     * Actualizar registros en la base de datos
     */
    public function update($table, $data, $where, $params = [])
    {
        try {
            $fields = [];
            foreach ($data as $key => $value) {
                $fields[] = "{$key} = ?";
            }

            $sql = "UPDATE {$table} SET " . implode(', ', $fields) . " WHERE {$where}";

            // Combinar los valores de los datos y los parámetros WHERE
            $combinedParams = array_merge(array_values($data), $params);

            if (!$this->query($sql, $combinedParams)) {
                return false;
            }

            return $this->connection->affected_rows > 0;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            error_log($this->error);
            return false;
        }
    }

    /**
     * Obtener el último error ocurrido
     */
    public function getError()
    {
        return $this->error;
    }
}
