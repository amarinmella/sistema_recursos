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
        // Cargar configuración desde config.ini
        $configPath = __DIR__ . '/config.ini';
        if (!file_exists($configPath)) {
            $this->error = "Archivo de configuración no encontrado: " . $configPath;
            error_log($this->error);
            $this->connection = null;
            return;
        }
        
        $config = parse_ini_file($configPath);
        if ($config === false) {
            $this->error = "Error al parsear el archivo de configuración";
            error_log($this->error);
            $this->connection = null;
            return;
        }

        // Configuración de la base de datos
        $host = $config['host'];
        $user = $config['user'];
        $pass = $config['pass'];
        $name = $config['name'];
        $charset = $config['charset'];

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
            $this->connection = null; // Asegurar que connection sea null en caso de error
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
        if ($this->connection === null) {
            $this->error = "No hay conexión a la base de datos disponible";
            error_log($this->error);
            return false;
        }
        return $this->connection;
    }

    /**
     * Ejecutar una consulta SQL
     */
    public function query($sql, $params = [])
    {
        // Verificar que la conexión esté disponible
        if ($this->connection === null) {
            $this->error = "No hay conexión a la base de datos disponible";
            error_log($this->error);
            return false;
        }

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

            // Si hay resultados (por ejemplo, en un SELECT), obtenerlos; si no, retornar true.
            if ($stmt->field_count > 0) {
                $result = $stmt->get_result();
                $stmt->close();
                return $result;
            } else {
                $stmt->close();
                return true;
            }
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
        // Verificar que la conexión esté disponible
        if ($this->connection === null) {
            $this->error = "No hay conexión a la base de datos disponible";
            error_log($this->error);
            return false;
        }

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
        // Verificar que la conexión esté disponible
        if ($this->connection === null) {
            $this->error = "No hay conexión a la base de datos disponible";
            error_log($this->error);
            return false;
        }

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
        // Verificar que la conexión esté disponible
        if ($this->connection === null) {
            $this->error = "No hay conexión a la base de datos disponible";
            error_log($this->error);
            return false;
        }

        try {
            $fields = array_keys($data);
            $placeholders = array_fill(0, count($fields), '?');

            $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ")
                    VALUES (" . implode(', ', $placeholders) . ")";

            $stmt = $this->connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Error al preparar la consulta: " . $this->connection->error);
            }

            // Determinar los tipos de datos para bind_param
            $types = '';
            foreach ($data as $value) {
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_float($value)) {
                    $types .= 'd';
                } elseif (is_string($value)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
            }

            // Preparar parámetros para bind_param
            $bindParams = array_merge([$types], array_values($data));
            $bindParamsRefs = [];
            foreach ($bindParams as $key => $value) {
                $bindParamsRefs[$key] = &$bindParams[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $bindParamsRefs);

            // Ejecutar la consulta
            if (!$stmt->execute()) {
                throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
            }

            $insertId = $this->connection->insert_id;
            $stmt->close();
            return $insertId;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            error_log("Error en insert(): " . $this->error);
            return false;
        }
    }

    /**
     * Actualizar registros en la base de datos
     */
    public function update($table, $data, $where, $params = [])
    {
        // Verificar que la conexión esté disponible
        if ($this->connection === null) {
            $this->error = "No hay conexión a la base de datos disponible";
            error_log($this->error);
            return false;
        }

        try {
            $fields = [];
            foreach ($data as $key => $value) {
                $fields[] = "{$key} = ?";
            }
            $sql = "UPDATE {$table} SET " . implode(', ', $fields) . " WHERE {$where}";
            $combinedParams = array_merge(array_values($data), $params);
            $result = $this->query($sql, $combinedParams);
            if ($result === false) {
                return false;
            }
            return true;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            error_log($this->error);
            return false;
        }
    }

    /**
     * Eliminar registros de la base de datos
     *
     * @param string $table      Nombre de la tabla
     * @param string $condition  Condición del WHERE (ej. "id = ?")
     * @param array  $params     Parámetros para bind_param
     * @return mixed Número de filas afectadas o false en error
     */
    public function delete($table, $condition, $params = [])
    {
        // Verificar que la conexión esté disponible
        if ($this->connection === null) {
            $this->error = "No hay conexión a la base de datos disponible";
            error_log($this->error);
            return false;
        }

        try {
            $sql = "DELETE FROM {$table} WHERE {$condition}";
            $stmt = $this->connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Error al preparar la consulta DELETE: " . $this->connection->error);
            }
            if (!empty($params)) {
                $types = "";
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= "i";
                    } elseif (is_float($param)) {
                        $types .= "d";
                    } elseif (is_string($param)) {
                        $types .= "s";
                    } else {
                        $types .= "b";
                    }
                }
                $bindParams = array_merge([$types], $params);
                $bindParamsRefs = [];
                foreach ($bindParams as $key => $value) {
                    $bindParamsRefs[$key] = &$bindParams[$key];
                }
                call_user_func_array([$stmt, 'bind_param'], $bindParamsRefs);
            }
            if (!$stmt->execute()) {
                throw new Exception("Error al ejecutar la consulta DELETE: " . $stmt->error);
            }
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            error_log("Error en delete(): " . $this->error);
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
