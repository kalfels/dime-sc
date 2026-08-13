<?php

// app_classes.php

namespace App\Core;

/**
 * Carrega variáveis de ambiente de um arquivo .env local.
 */
class Env
{
    public static function load(?string $path = null): void
    {
        $path = $path ?? dirname(__DIR__) . '/.env';

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            $value = str_replace(['\\n', '\\r'], ["\n", "\r"], $value);

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    public static function get(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}

Env::load();

/**
 * Classe responsável por gerenciar a conexão com o banco de dados.
 */
class DBConnection
{
    private static $instance = null;
    private $pdo;

    // Configurações do banco de dados - ATUALIZE CONFORME SEU AMBIENTE
    private $dbConfig = [];

    private function __construct()
    {
        $this->dbConfig = [
            'host' => Env::get('DB_HOST', 'localhost'),
            'dbname' => Env::get('DB_NAME', 'dime'),
            'user' => Env::get('DB_USER', 'root'),
            'pass' => Env::get('DB_PASS', ''),
            'charset' => Env::get('DB_CHARSET', 'utf8mb4')
        ];

        $dsn = "mysql:host={$this->dbConfig['host']};dbname={$this->dbConfig['dbname']};charset={$this->dbConfig['charset']}";
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $this->pdo = new \PDO($dsn, $this->dbConfig['user'], $this->dbConfig['pass'], $options);
        } catch (\PDOException $e) {
            // Em um ambiente de produção, você pode registrar o erro e mostrar uma mensagem genérica.
            die("Erro de conexão com o banco de dados: " . $e->getMessage());
        }
    }

    public static function getInstance(): DBConnection
    {
        if (self::$instance === null) {
            self::$instance = new DBConnection();
        }
        return self::$instance;
    }

    public function getConnection(): \PDO
    {
        return $this->pdo;
    }
}

namespace App\Models;

use App\Core\DBConnection;

/**
 * Classe base abstrata para todos os modelos de banco de dados.
 * Fornece métodos CRUD genéricos.
 */
abstract class Model
{
    // Deve ser sobrescrito pelas classes filhas
    protected const TABLE_NAME = '';
    protected const PRIMARY_KEY = 'id';
    protected const FILLABLE = []; // Campos que podem ser preenchidos em massa
    protected const DATES = ['created_at', 'updated_at', 'data_publicacao', 'ultimo_login_at', 'data_vencimento']; // Campos que devem ser tratados como datas

    protected $attributes = []; // Armazena os dados do modelo

    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            // Special handling for ID to ensure it's always set in the attributes array
            if ($key === 'id' || $key === static::PRIMARY_KEY) {
                $this->attributes[static::PRIMARY_KEY] = $value;
            } else {
                $this->attributes[$key] = $value;
            }
        }
        
        // Ensure ID is set as a direct property for backward compatibility
        if (isset($this->attributes[static::PRIMARY_KEY])) {
            $this->id = $this->attributes[static::PRIMARY_KEY];
        }
    }

    public function __get($key)
    {
        // Special handling for ID to ensure it's always available as a direct property
        if ($key === 'id' || $key === static::PRIMARY_KEY) {
            return $this->attributes[static::PRIMARY_KEY] ?? null;
        }
        return $this->attributes[$key] ?? null;
    }

    public function __set($key, $value)
    {
        // Special handling for ID to ensure it's always set in the attributes array
        if ($key === 'id' || $key === static::PRIMARY_KEY) {
            $this->attributes[static::PRIMARY_KEY] = $value;
        } else {
            $this->attributes[$key] = $value;
        }
    }
    
    public function __isset($key)
    {
        // Special handling for ID to ensure isset() works correctly
        if ($key === 'id' || $key === static::PRIMARY_KEY) {
            return isset($this->attributes[static::PRIMARY_KEY]);
        }
        return isset($this->attributes[$key]);
    }
    
    /**
     * Get the attributes for debugging purposes
     */
    public function __debugInfo()
    {
        return [
            'id' => $this->id,
            'attributes' => $this->attributes
        ];
    }

    /**
     * Retorna o nome da tabela associada ao modelo.
     * @return string
     */
    public static function getTableName(): string
    {
        return static::TABLE_NAME;
    }

    /**
     * Retorna a chave primária da tabela associada ao modelo.
     * @return string
     */
    public static function getPrimaryKey(): string
    {
        return static::PRIMARY_KEY;
    }

    /**
     * Retorna todos os registros da tabela.
     * @return array<static>
     */
    public static function all(): array
    {
        $pdo = DBConnection::getInstance()->getConnection();
        $stmt = $pdo->query("SELECT * FROM " . static::TABLE_NAME);
        return array_map(function ($data) {
            return new static($data);
        }, $stmt->fetchAll());
    }

    /**
     * Encontra um registro pelo ID.
     * @param int $id
     * @return static|null
     */
    public static function find(int $id)
    {
        error_log('=== FIND METHOD START ===');
        error_log('Looking for ID: ' . $id . ' in table: ' . static::TABLE_NAME);
        
        $pdo = DBConnection::getInstance()->getConnection();
        $sql = "SELECT * FROM " . static::TABLE_NAME . " WHERE " . static::PRIMARY_KEY . " = ?";
        error_log('SQL: ' . $sql);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        
        error_log('Found data: ' . ($data ? print_r($data, true) : 'none'));
        
        if ($data) {
            $user = new static($data);
            error_log('Created user object. ID set to: ' . ($user->id ?? 'not set'));
            error_log('User object attributes: ' . print_r($user, true));
            error_log('=== FIND METHOD END ===');
            return $user;
        }
        
        error_log('No user found with ID: ' . $id);
        error_log('=== FIND METHOD END ===');
        return null;
    }

    /**
     * Ordena os resultados por um campo específico.
     * @param string $column
     * @param string $direction
     * @return array<static>
     */
    public static function orderBy(string $column, string $direction = 'ASC'): array
    {
        $pdo = DBConnection::getInstance()->getConnection();
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $stmt = $pdo->query("SELECT * FROM " . static::TABLE_NAME . " ORDER BY $column $direction");
        return array_map(function ($data) {
            return new static($data);
        }, $stmt->fetchAll());
    }
    
    /**
     * Encontra registros por um campo e valor.
     * @param string $field
     * @param mixed $value
     * @return array<static>
     */
    public static function where(string $field, $value): array
    {
        $pdo = DBConnection::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM " . static::TABLE_NAME . " WHERE {$field} = ?");
        $stmt->execute([$value]);
        return array_map(function ($data) {
            return new static($data);
        }, $stmt->fetchAll());
    }

    /**
     * Salva (insere ou atualiza) o registro no banco de dados.
     * @return bool
     */
    public function save(): bool
    {
        error_log('=== SAVE METHOD START ===');
        error_log('Table: ' . static::TABLE_NAME);
        error_log('Primary key: ' . static::PRIMARY_KEY);
        
        // Ensure ID is properly synchronized between direct property and attributes array
        if (isset($this->id) && empty($this->{static::PRIMARY_KEY})) {
            $this->{static::PRIMARY_KEY} = $this->id;
        } elseif (isset($this->{static::PRIMARY_KEY}) && empty($this->id)) {
            $this->id = $this->{static::PRIMARY_KEY};
        }
        
        $primaryKeyValue = $this->{static::PRIMARY_KEY} ?? 'not set';
        error_log('Primary key value: ' . $primaryKeyValue);
        
        // Get a safe array of attributes for logging
        $logAttributes = [];
        foreach (static::FILLABLE as $field) {
            if (isset($this->$field)) {
                $logAttributes[$field] = $field === 'senha_hash' ? '***HIDDEN***' : $this->$field;
            }
        }
        error_log('Model data: ' . print_r($logAttributes, true));
        
        // Check if we should update or insert
        $shouldUpdate = !empty($this->{static::PRIMARY_KEY});
        error_log('Should update? ' . ($shouldUpdate ? 'YES' : 'NO'));
        
        try {
            if ($shouldUpdate) {
                error_log('Calling update()');
                $result = $this->update();
            } else {
                error_log('Calling insert()');
                $result = $this->insert();
            }
            
            error_log('Save result: ' . ($result ? 'SUCCESS' : 'FAILED'));
            error_log('=== SAVE METHOD END ===');
            return $result;
        } catch (\Exception $e) {
            error_log('Save error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Insere um novo registro no banco de dados.
     * @return bool
     */
    protected function insert(): bool
    {
        $pdo = DBConnection::getInstance()->getConnection();
        $fillableAttributes = array_intersect_key($this->attributes, array_flip(static::FILLABLE));

        // Ajusta campos de data/datetime para o formato SQL
        foreach (static::DATES as $dateField) {
            if (isset($fillableAttributes[$dateField]) && !empty($fillableAttributes[$dateField])) {
                $fillableAttributes[$dateField] = date('Y-m-d H:i:s', strtotime($fillableAttributes[$dateField]));
            } else if (array_key_exists($dateField, $fillableAttributes)) { // Se o campo está presente mas vazio/null
                $fillableAttributes[$dateField] = null;
            }
        }
        
        $columns = implode(', ', array_keys($fillableAttributes));
        $placeholders = implode(', ', array_fill(0, count($fillableAttributes), '?'));
        $sql = "INSERT INTO " . static::TABLE_NAME . " ({$columns}) VALUES ({$placeholders})";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute(array_values($fillableAttributes));

        if ($result) {
            $this->{static::PRIMARY_KEY} = (int)$pdo->lastInsertId();
            // Atualiza created_at e updated_at se a tabela os tiver e não foram definidos
            if (in_array('created_at', static::FILLABLE) && !isset($this->attributes['created_at'])) {
                $this->attributes['created_at'] = date('Y-m-d H:i:s');
            }
            if (in_array('updated_at', static::FILLABLE) && !isset($this->attributes['updated_at'])) {
                $this->attributes['updated_at'] = date('Y-m-d H:i:s');
            }
        }
        return $result;
    }

    /**
     * Atualiza um registro existente no banco de dados.
     * @return bool
     */
    protected function update(): bool
    {
        $pdo = DBConnection::getInstance()->getConnection();
        $fillableAttributes = array_intersect_key($this->attributes, array_flip(static::FILLABLE));
        
        // Remove primary key from attributes to update
        unset($fillableAttributes[static::PRIMARY_KEY]);
        
        // Email updates are now allowed, but the controller should validate uniqueness
        // before calling save()

        // Ajusta campos de data/datetime para o formato SQL
        foreach (static::DATES as $dateField) {
            if (isset($fillableAttributes[$dateField]) && !empty($fillableAttributes[$dateField])) {
                $fillableAttributes[$dateField] = date('Y-m-d H:i:s', strtotime($fillableAttributes[$dateField]));
            } else if (array_key_exists($dateField, $fillableAttributes)) { // Se o campo está presente mas vazio/null
                $fillableAttributes[$dateField] = null;
            }
        }

        // Adiciona updated_at se a tabela o tiver
        if (in_array('updated_at', static::FILLABLE)) {
            $fillableAttributes['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $setParts = [];
        foreach (array_keys($fillableAttributes) as $column) {
            $setParts[] = "{$column} = ?";
        }
        $sql = "UPDATE " . static::TABLE_NAME . " SET " . implode(', ', $setParts) . " WHERE " . static::PRIMARY_KEY . " = ?";

        $params = array_values($fillableAttributes);
        $params[] = $this->{static::PRIMARY_KEY}; // Adiciona o ID como último parâmetro

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Exclui o registro do banco de dados.
     * @return bool
     */
    public function delete(): bool
    {
        if (!isset($this->{static::PRIMARY_KEY})) {
            return false;
        }
        $pdo = DBConnection::getInstance()->getConnection();
        $stmt = $pdo->prepare("DELETE FROM " . static::TABLE_NAME . " WHERE " . static::PRIMARY_KEY . " = ?");
        return $stmt->execute([$this->{static::PRIMARY_KEY}]);
    }

    /**
     * Retorna valores distintos de uma coluna.
     * @param string $column
     * @param array $whereConditions array of ['field', 'operator', 'value']
     * @return array
     */
    public static function fetchDistinct(string $column, array $whereConditions = []): array
    {
        $pdo = DBConnection::getInstance()->getConnection();
        $sql = "SELECT DISTINCT {$column} FROM " . static::TABLE_NAME;
        $params = [];

        if (!empty($whereConditions)) {
            $whereClauses = [];
            foreach ($whereConditions as $condition) {
                $whereClauses[] = "{$condition[0]} {$condition[1]} ?";
                $params[] = $condition[2];
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        $sql .= " ORDER BY {$column}"; // Adiciona ordenação para selects

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Executa uma consulta SQL genérica com join e retorna os resultados.
     * @param array $selects Colunas a serem selecionadas (ex: ['t1.col1', 't2.col2'])
     * @param array $joins Array de [tipo_join, tabela_join, condicao_on] (ex: ['LEFT JOIN', 'other_table t2', 't1.id = t2.t1_id'])
     * @param array $whereConditions Array de condições WHERE (ex: ['field', 'operator', 'value'])
     * @param string $orderBy Coluna para ordenação
     * @param string $groupBy Coluna para agrupamento
     * @return array
     */
    public static function fetchWithJoin(
        array $selects,
        array $joins = [],
        array $whereConditions = [],
        string $orderBy = '',
        string $groupBy = ''
    ): array {
        $pdo = DBConnection::getInstance()->getConnection();
        $sql = "SELECT " . implode(', ', $selects) . " FROM " . static::TABLE_NAME . " AS t1";
        $params = [];

        foreach ($joins as $join) {
            $sql .= " {$join[0]} {$join[1]} ON {$join[2]}";
        }

        if (!empty($whereConditions)) {
            $whereClauses = [];
            foreach ($whereConditions as $condition) {
                $whereClauses[] = "{$condition[0]} {$condition[1]} ?";
                $params[] = $condition[2];
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        if (!empty($groupBy)) {
            $sql .= " GROUP BY {$groupBy}";
        }

        if (!empty($orderBy)) {
            $sql .= " ORDER BY {$orderBy}";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

/**
 * Representa a tabela `contabilistas`.
 */
class Contabilista extends Model
{
    protected const TABLE_NAME = 'contabilistas';
    protected const FILLABLE = [
        'nome', 'cpf', 'crc', 'endereco', 'numero', 'complemento',
        'bairro', 'cidade', 'estado', 'cep', 'telefone', 'email', 'created_at', 'updated_at'
    ];
    
    /**
     * Insert a new contabilista record
     */
    public static function insertContabilista(array $data): ?int
    {
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        
        // Clean CPF - remove formatting (dots, dashes, spaces)
        if (isset($data['cpf'])) {
            $data['cpf'] = preg_replace('/[^0-9]/', '', $data['cpf']);
        }
        
        // Required fields validation
        $required = ['nome', 'cpf', 'email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Campo obrigatório não preenchido: {$field}");
            }
        }
        
        // Validate CPF length (must be exactly 11 digits)
        if (strlen($data['cpf']) !== 11) {
            throw new \InvalidArgumentException("CPF deve conter exatamente 11 dígitos.");
        }
        
        // Check for duplicate CPF
        $stmt = $pdo->prepare("SELECT id FROM contabilistas WHERE cpf = ?");
        $stmt->execute([$data['cpf']]);
        if ($stmt->fetch()) {
            throw new \InvalidArgumentException("Já existe um contabilista cadastrado com este CPF.");
        }
        
        // Check for duplicate email
        $stmt = $pdo->prepare("SELECT id FROM contabilistas WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            throw new \InvalidArgumentException("Já existe um contabilista cadastrado com este email.");
        }
        
        $sql = "INSERT INTO contabilistas (cpf, nome, crc, endereco, numero, complemento, bairro, cidade, estado, cep, telefone, email) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['cpf'],
            $data['nome'],
            $data['crc'] ?? null,
            $data['endereco'] ?? null,
            $data['numero'] ?? null,
            $data['complemento'] ?? null,
            $data['bairro'] ?? null,
            $data['cidade'] ?? null,
            $data['estado'] ?? null,
            $data['cep'] ?? null,
            $data['telefone'] ?? null,
            $data['email']
        ]);
        
        return $result ? (int)$pdo->lastInsertId() : null;
    }
    
    /**
     * Update an existing contabilista record (CPF is immutable)
     */
    public static function updateContabilista(int $id, array $data): bool
    {
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        
        // Check if contabilista exists
        $stmt = $pdo->prepare("SELECT id FROM contabilistas WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new \InvalidArgumentException("Contabilista não encontrado.");
        }
        
        // Required fields validation (excluding CPF)
        $required = ['nome', 'email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Campo obrigatório não preenchido: {$field}");
            }
        }
        
        // Check for duplicate email (excluding current record)
        $stmt = $pdo->prepare("SELECT id FROM contabilistas WHERE email = ? AND id != ?");
        $stmt->execute([$data['email'], $id]);
        if ($stmt->fetch()) {
            throw new \InvalidArgumentException("Já existe outro contabilista cadastrado com este email.");
        }
        
        // Update query - CPF is intentionally excluded
        $sql = "UPDATE contabilistas SET 
                nome = ?, crc = ?, endereco = ?, numero = ?, complemento = ?, 
                bairro = ?, cidade = ?, estado = ?, cep = ?, telefone = ?, email = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['nome'],
            $data['crc'] ?? null,
            $data['endereco'] ?? null,
            $data['numero'] ?? null,
            $data['complemento'] ?? null,
            $data['bairro'] ?? null,
            $data['cidade'] ?? null,
            $data['estado'] ?? null,
            $data['cep'] ?? null,
            $data['telefone'] ?? null,
            $data['email'],
            $id
        ]);
    }
    
    /**
     * Delete a contabilista record
     */
    public static function deleteContabilista(int $id): bool
    {
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        
        // Check if contabilista exists
        $stmt = $pdo->prepare("SELECT id FROM contabilistas WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new \InvalidArgumentException("Contabilista não encontrado.");
        }
        
        // Check if contabilista is being used by empresas
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM empresas WHERE contabilista_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        if ($result['count'] > 0) {
            throw new \InvalidArgumentException("Não é possível excluir este contabilista pois ele está sendo usado por empresas.");
        }
        
        // Check if contabilista is being used by usuarios
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM usuarios WHERE contabilista_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        if ($result['count'] > 0) {
            throw new \InvalidArgumentException("Não é possível excluir este contabilista pois ele está sendo usado por usuários.");
        }
        
        $stmt = $pdo->prepare("DELETE FROM contabilistas WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

/**
 * Representa a tabela `empresas`.
 */
class Empresa extends Model
{
    protected const TABLE_NAME = 'empresas';
    protected const FILLABLE = [
        'cnpj', 'razao_social', 'inscricao_estadual', 'endereco', 'numero', 'complemento',
        'bairro', 'municipio_id', 'cep', 'telefone', 'email', 'contabilista_id', 'created_at', 'updated_at'
    ];

    public function getContabilista(): ?Contabilista
    {
        return Contabilista::find($this->contabilista_id);
    }
    
    /**
     * Insert a new empresa record
     */
    public static function insertEmpresa(array $data): ?int
    {
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        
        // Clean CNPJ - remove formatting (dots, dashes, slashes, spaces)
        if (isset($data['cnpj'])) {
            $data['cnpj'] = preg_replace('/[^0-9]/', '', $data['cnpj']);
        }
        
        // Clean CEP - remove formatting (dashes, dots, spaces)
        if (isset($data['cep']) && !empty($data['cep'])) {
            $data['cep'] = preg_replace('/[^0-9]/', '', $data['cep']);
            // Validate CEP length (must be exactly 8 digits)
            if (strlen($data['cep']) !== 8) {
                throw new \InvalidArgumentException("CEP deve conter exatamente 8 dígitos.");
            }
        }
        
        // Required fields validation
        $required = ['cnpj', 'razao_social', 'inscricao_estadual', 'contabilista_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Campo obrigatório não preenchido: {$field}");
            }
        }
        
        // Validate CNPJ length (must be exactly 14 digits)
        if (strlen($data['cnpj']) !== 14) {
            throw new \InvalidArgumentException("CNPJ deve conter exatamente 14 dígitos.");
        }
        
        // Check for duplicate CNPJ
        $stmt = $pdo->prepare("SELECT id FROM empresas WHERE cnpj = ?");
        $stmt->execute([$data['cnpj']]);
        if ($stmt->fetch()) {
            throw new \InvalidArgumentException("Já existe uma empresa cadastrada com este CNPJ.");
        }
        
        // Check for duplicate inscricao_estadual
        $stmt = $pdo->prepare("SELECT id FROM empresas WHERE inscricao_estadual = ?");
        $stmt->execute([$data['inscricao_estadual']]);
        if ($stmt->fetch()) {
            throw new \InvalidArgumentException("Já existe uma empresa cadastrada com esta Inscrição Estadual.");
        }
        
        $sql = "INSERT INTO empresas (cnpj, razao_social, inscricao_estadual, endereco, numero, complemento, 
                bairro, municipio_id, cep, telefone, email, contabilista_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['cnpj'],
            $data['razao_social'],
            $data['inscricao_estadual'],
            $data['endereco'] ?? null,
            $data['numero'] ?? null,
            $data['complemento'] ?? null,
            $data['bairro'] ?? null,
            $data['municipio_id'] ?? null,
            $data['cep'] ?? null,
            $data['telefone'] ?? null,
            $data['email'] ?? null,
            $data['contabilista_id']
        ]);
        
        return $result ? (int)$pdo->lastInsertId() : null;
    }
    
    /**
     * Update an existing empresa record
     */
    public static function updateEmpresa(int $id, array $data): bool
    {
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        
        // Check if empresa exists
        $stmt = $pdo->prepare("SELECT id FROM empresas WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new \InvalidArgumentException("Empresa não encontrada.");
        }
        
        // Clean CNPJ - remove formatting
        if (isset($data['cnpj'])) {
            $data['cnpj'] = preg_replace('/[^0-9]/', '', $data['cnpj']);
        }
        
        // Clean CEP - remove formatting (dashes, dots, spaces)
        if (isset($data['cep']) && !empty($data['cep'])) {
            $data['cep'] = preg_replace('/[^0-9]/', '', $data['cep']);
            // Validate CEP length (must be exactly 8 digits)
            if (strlen($data['cep']) !== 8) {
                throw new \InvalidArgumentException("CEP deve conter exatamente 8 dígitos.");
            }
        }
        
        // Required fields validation
        $required = ['cnpj', 'razao_social', 'inscricao_estadual', 'contabilista_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Campo obrigatório não preenchido: {$field}");
            }
        }
        
        // Validate CNPJ length
        if (strlen($data['cnpj']) !== 14) {
            throw new \InvalidArgumentException("CNPJ deve conter exatamente 14 dígitos.");
        }
        
        // Check for duplicate CNPJ (excluding current record)
        $stmt = $pdo->prepare("SELECT id FROM empresas WHERE cnpj = ? AND id != ?");
        $stmt->execute([$data['cnpj'], $id]);
        if ($stmt->fetch()) {
            throw new \InvalidArgumentException("Já existe outra empresa cadastrada com este CNPJ.");
        }
        
        // Check for duplicate inscricao_estadual (excluding current record)
        $stmt = $pdo->prepare("SELECT id FROM empresas WHERE inscricao_estadual = ? AND id != ?");
        $stmt->execute([$data['inscricao_estadual'], $id]);
        if ($stmt->fetch()) {
            throw new \InvalidArgumentException("Já existe outra empresa cadastrada com esta Inscrição Estadual.");
        }
        
        $sql = "UPDATE empresas SET 
                cnpj = ?, razao_social = ?, inscricao_estadual = ?, endereco = ?, numero = ?, 
                complemento = ?, bairro = ?, municipio_id = ?, cep = ?, telefone = ?, email = ?, contabilista_id = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['cnpj'],
            $data['razao_social'],
            $data['inscricao_estadual'],
            $data['endereco'] ?? null,
            $data['numero'] ?? null,
            $data['complemento'] ?? null,
            $data['bairro'] ?? null,
            $data['municipio_id'] ?? null,
            $data['cep'] ?? null,
            $data['telefone'] ?? null,
            $data['email'] ?? null,
            $data['contabilista_id'],
            $id
        ]);
    }
    
    /**
     * Delete an empresa record
     */
    public static function deleteEmpresa(int $id): bool
    {
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        
        // Check if empresa exists
        $stmt = $pdo->prepare("SELECT id FROM empresas WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new \InvalidArgumentException("Empresa não encontrada.");
        }
        
        // Check if empresa has DIME declarations
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM dime_declaracoes WHERE empresa_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        if ($result['count'] > 0) {
            throw new \InvalidArgumentException("Não é possível excluir esta empresa pois ela possui declarações DIME.");
        }
        
        $stmt = $pdo->prepare("DELETE FROM empresas WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Check if empresa has DIME declarations
     */
    public function hasDimeDeclarations(): bool
    {
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM dime_declaracoes WHERE empresa_id = ?");
        $stmt->execute([$this->id]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    /**
     * Inactivate empresa (soft delete)
     */
    public function inactivate(): bool
    {
        // Note: This would require an 'active' or 'status' field in the database
        // For now, we'll just return true as a placeholder
        // In a real implementation, you'd add a status field to the database
        return true;
    }
}

/**
 * Representa a tabela `usuarios`.
 */
class Usuario extends Model
{
    protected const TABLE_NAME = 'usuarios';
    protected const PRIMARY_KEY = 'id';
    protected const FILLABLE = [
        'nome', 'email', 'senha_hash', 'role', 'status', 'contabilista_id', 'ultimo_login_at', 
        'status_pagamento', 'primeiro_acesso', 'created_at', 'updated_at',
        'token_redefinicao', 'token_expiracao'
    ];
    
    // Role constants for better code readability
    public const ROLE_ADMIN = 'admin';
    public const ROLE_OPERADOR = 'operador';
    
    // Status constants
    public const STATUS_ATIVO = 'ativo';
    public const STATUS_INATIVO = 'inativo';
    public const PAGAMENTO_ATIVO = 'ativo';
    public const PAGAMENTO_BLOQUEADO = 'bloqueado';

    /**
     * Gera o hash da senha antes de salvar.
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->senha_hash = password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verifica se a senha fornecida corresponde ao hash.
     * @param string $password
     * @return bool
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->senha_hash);
    }

    public function getContabilista(): ?Contabilista
    {
        return Contabilista::find($this->contabilista_id);
    }

    /**
     * Encontra um usuário pelo e-mail.
     * @param string $email
     * @return static|null
     */
    public static function findByEmail(string $email)
    {
        $pdo = DBConnection::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM " . static::TABLE_NAME . " WHERE email = ?");
        $stmt->execute([$email]);
        $data = $stmt->fetch();
        return $data ? new static($data) : null;
    }
    
    /**
     * Check if user is admin
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
    
    /**
     * Check if user is active
     * @return bool
     */
    public function isActive(): bool
    {
        return isset($this->status) && $this->status === self::STATUS_ATIVO;
    }
    
    /**
     * Check if user payment is active
     * @return bool
     */
    public function isPaymentActive(): bool
    {
        return isset($this->status_pagamento) && $this->status_pagamento === self::PAGAMENTO_ATIVO;
    }

    /**
     * Check if user has payment issues (for warnings)
     * @return bool
     */
    public function hasPaymentIssues(): bool
    {
        return isset($this->status_pagamento) && $this->status_pagamento === self::PAGAMENTO_BLOQUEADO;
    }
    
    /**
     * Gera um token de redefinição de senha
     * @return string Token gerado
     */
    public function gerarTokenRedefinicao(): string
    {
        $this->token_redefinicao = bin2hex(random_bytes(32));
        $this->token_expiracao = date('Y-m-d H:i:s', strtotime('+1 hour'));
        return $this->token_redefinicao;
    }
    
    /**
     * Verifica se o token de redefinição é válido
     * @param string $token Token a ser verificado
     * @return bool Retorna true se o token for válido e não estiver expirado
     */
    public function verificarTokenRedefinicao(string $token): bool
    {
        if (empty($this->token_redefinicao) || empty($this->token_expiracao)) {
            return false;
        }
        
        if (!hash_equals($this->token_redefinicao, $token)) {
            return false;
        }
        
        // Verifica se o token não expirou
        return strtotime($this->token_expiracao) >= time();
    }
    
    /**
     * Limpa o token de redefinição de senha
     */
    public function limparTokenRedefinicao(): void
    {
        $this->token_redefinicao = null;
        $this->token_expiracao = null;
    }
    
    /**
     * Check if user has access to the system
     * @return bool
     */
    public function hasAccess(): bool
    {
        // Admin always has access
        if ($this->isAdmin()) {
            return true;
        }

        // For operators, only check if they are active
        // Payment status is handled separately with warnings
        if ($this->role === self::ROLE_OPERADOR) {
            return $this->isActive();
        }

        // Default case (shouldn't happen for valid roles)
        return false;
    }
}

/**
 * Representa a tabela `dime_manual_versoes`.
 */
class DimeManualVersao extends Model
{
    protected const TABLE_NAME = 'dime_manual_versoes';
    protected const FILLABLE = [
        'versao_completa', 'data_publicacao', 'portarias_incorporadas',
        'resumo_alteracoes', 'caminho_arquivo_pdf', 'created_at', 'updated_at'
    ];
    // Adiciona data_publicacao aos campos de data
    protected const DATES = ['created_at', 'updated_at', 'data_publicacao'];
}

/**
 * Representa a tabela `t_cfops`.
 */
class Cfop extends Model
{
    protected const TABLE_NAME = 't_cfops';
    protected const FILLABLE = ['codigo', 'descricao'];
    // CFOPs geralmente não têm created_at/updated_at específicos para eles mesmos
    // Se sua tabela tiver, adicione em FILLABLE e DATES
    protected const DATES = [];
}

/**
 * Representa a tabela `t_classes_vencimento`.
 */
class ClasseVencimento extends Model
{
    protected const TABLE_NAME = 't_classes_vencimento';
    protected const FILLABLE = ['codigo', 'descricao'];
    protected const DATES = [];
}

/**
 * Representa a tabela `t_codigos_receita`.
 */
class CodigoReceita extends Model
{
    protected const TABLE_NAME = 't_codigos_receita';
    protected const FILLABLE = ['codigo', 'descricao', 'origem_dime_valida'];
    protected const DATES = [];
}

/**
 * Representa a tabela `dime_declaracoes`.
 * Esta é a tabela principal para cada DIME gerada.
 */
class DimeDeclaracao extends Model
{
    protected const TABLE_NAME = 'dime_declaracoes';
    protected const FILLABLE = [
        'empresa_id', 'contabilista_id', 'periodo_referencia', 'layout_versao', 'data_geracao', 'tipo_declaracao',
        'regime_apuracao', 'porte_empresa', 'apuracao_consolidada',
        'apuracao_centralizada', 'transferencia_creditos_periodo', 'tem_creditos_presumidos',
        'tem_creditos_incentivos_fiscais', 'movimento', 'substituto_tributario',
        'tem_escrita_contabil', 'quantidade_trabalhadores', 'status',
        'created_at', 'updated_at'
    ];

    public function getEmpresa(): ?Empresa
    {
        return Empresa::find($this->empresa_id);
    }

    public function getContabilista(): ?Contabilista
    {
        return Contabilista::find($this->contabilista_id);
    }

    /**
     * Retorna os pagamentos associados a esta declaração DIME.
     * @return array<DimeQuadro12Pagamentos>
     */
    public function getPagamentos(): array
    {
        return DimeQuadro12Pagamentos::where('dime_declaracao_id', $this->id);
    }

    /**
     * Retorna a descrição do tipo de declaração
     * @return string
     */
    public function getTipoDeclaracaoDescricao(): string
    {
        $tipos = [
            '1' => 'Normal',
            '2' => 'Encerramento',
            '4' => 'Simples Nacional'
        ];
        return $tipos[$this->tipo_declaracao] ?? 'Desconhecido';
    }

    /**
     * Retorna a descrição do regime de apuração
     * @return string
     */
    public function getRegimeApuracaoDescricao(): string
    {
        $regimes = [
            '2' => 'Mensal',
            '9' => 'Produtor Primário'
        ];
        return $regimes[$this->regime_apuracao] ?? 'Desconhecido';
    }

    /**
     * Retorna a descrição do porte da empresa
     * @return string
     */
    public function getPorteEmpresaDescricao(): string
    {
        $portes = [
            '1' => 'Microempresa',
            '2' => 'Pequena Empresa',
            '3' => 'Média Empresa',
            '4' => 'Grande Empresa'
        ];
        return $portes[$this->porte_empresa] ?? 'Desconhecido';
    }
}

/**
 * Representa a tabela `dime_quadro_12_pagamentos`.
 * Detalhes dos pagamentos do imposto para o Quadro 12 da DIME.
 */
class DimeQuadro12Pagamentos extends Model
{
    protected const TABLE_NAME = 'dime_quadro_12_pagamentos';
    protected const FILLABLE = [
        'dime_declaracao_id', 'origem', 'codigo_receita_id',
        'classe_vencimento_id', 'data_vencimento', 'valor', 'numero_acordo', 'created_at', 'updated_at'
    ];
    // Adiciona data_vencimento aos campos de data
    protected const DATES = ['created_at', 'updated_at', 'data_vencimento'];

    public function getDeclaracao(): ?DimeDeclaracao
    {
        return DimeDeclaracao::find($this->dime_declaracao_id);
    }

    public function getCodigoReceita(): ?CodigoReceita
    {
        return CodigoReceita::find($this->codigo_receita_id);
    }

    public function getClasseVencimento(): ?ClasseVencimento
    {
        return ClasseVencimento::find($this->classe_vencimento_id);
    }
}

/**
 * Base model class for common functionality
 */
abstract class BaseModel
{
    protected const TABLE = '';
    protected const FILLABLE = [];
    protected const DATES = [];
    
    protected $attributes = [];
    protected $original = [];
    protected $exists = false;
    
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }
    
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, static::FILLABLE) || empty(static::FILLABLE)) {
                $this->attributes[$key] = $value;
            }
        }
        return $this;
    }
    
    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }
    
    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }
    
    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }
    
    public static function all(): array
    {
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        $stmt = $pdo->query("SELECT * FROM " . static::TABLE);
        $results = [];
        
        while ($row = $stmt->fetch()) {
            $instance = new static($row);
            $instance->exists = true;
            $instance->original = $row;
            $results[] = $instance;
        }
        
        return $results;
    }
    
    public static function find($id): ?self
    {
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM " . static::TABLE . " WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        
        if ($row) {
            $instance = new static($row);
            $instance->exists = true;
            $instance->original = $row;
            return $instance;
        }
        
        return null;
    }
    
    public static function where(string $column, $value): array
    {
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM " . static::TABLE . " WHERE {$column} = ?");
        $stmt->execute([$value]);
        $results = [];
        
        while ($row = $stmt->fetch()) {
            $instance = new static($row);
            $instance->exists = true;
            $instance->original = $row;
            $results[] = $instance;
        }
        
        return $results;
    }
    
    public function save(): bool
    {
        if ($this->exists) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }
    
    protected function insert(): bool
    {
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        $fillable = static::FILLABLE;
        
        if (empty($fillable)) {
            $fillable = array_keys($this->attributes);
        }
        
        $columns = implode(', ', $fillable);
        $placeholders = ':' . implode(', :', $fillable);
        
        $sql = "INSERT INTO " . static::TABLE . " ({$columns}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        
        $data = [];
        foreach ($fillable as $column) {
            $data[$column] = $this->attributes[$column] ?? null;
        }
        
        if ($stmt->execute($data)) {
            $this->attributes['id'] = $pdo->lastInsertId();
            $this->exists = true;
            $this->original = $this->attributes;
            return true;
        }
        
        return false;
    }
    
    protected function update(): bool
    {
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        $fillable = static::FILLABLE;
        
        if (empty($fillable)) {
            $fillable = array_keys($this->attributes);
            $fillable = array_filter($fillable, fn($col) => $col !== 'id');
        }
        
        $setParts = [];
        foreach ($fillable as $column) {
            $setParts[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE " . static::TABLE . " SET {$setClause} WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        
        $data = ['id' => $this->attributes['id']];
        foreach ($fillable as $column) {
            $data[$column] = $this->attributes[$column] ?? null;
        }
        
        if ($stmt->execute($data)) {
            $this->original = $this->attributes;
            return true;
        }
        
        return false;
    }
    
    public function delete(): bool
    {
        if (!$this->exists || !isset($this->attributes['id'])) {
            return false;
        }
        
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        $stmt = $pdo->prepare("DELETE FROM " . static::TABLE . " WHERE id = ?");
        
        if ($stmt->execute([$this->attributes['id']])) {
            $this->exists = false;
            return true;
        }
        
        return false;
    }
}

/**
 * Modelo para gerenciar municípios
 */
class Municipio extends BaseModel
{
    protected const TABLE = 't_municipios';
    protected const FILLABLE = ['codigo_ibge', 'nome', 'ativo'];
    protected const DATES = [];

    /**
     * Insert new municipio
     */
    public static function insertMunicipio(array $data): ?int
    {
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        
        // Required fields validation
        $required = ['codigo_ibge', 'nome', 'estado'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Campo obrigatório não preenchido: {$field}");
            }
        }
        
        // Validate codigo_ibge uniqueness
        $stmt = $pdo->prepare("SELECT id FROM t_municipios WHERE codigo_ibge = ?");
        $stmt->execute([$data['codigo_ibge']]);
        if ($stmt->fetch()) {
            throw new \InvalidArgumentException("Código IBGE já existe.");
        }
        
        // Set default values
        $data['ativo'] = $data['ativo'] ?? 1;
        
        try {
            $sql = "INSERT INTO t_municipios (codigo_ibge, nome, estado, ativo) VALUES (?, ?, 'SC', ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['codigo_ibge'],
                $data['nome'],
                $data['ativo']
            ]);
            
            return $pdo->lastInsertId();
        } catch (\PDOException $e) {
            throw new \RuntimeException("Erro ao inserir município: " . $e->getMessage());
        }
    }

    /**
     * Update municipio
     */
    public static function updateMunicipio(int $id, array $data): bool
    {
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        
        // Debug logging
        error_log("updateMunicipio called with ID: $id");
        error_log("updateMunicipio data: " . json_encode($data));
        
        // Check if municipio exists
        $stmt = $pdo->prepare("SELECT id FROM t_municipios WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            error_log("Municipality with ID $id not found in database");
            throw new \InvalidArgumentException("Município não encontrado.");
        }
        
        // Required fields validation - only check codigo_ibge and nome since that's what we're updating
        $required = ['codigo_ibge', 'nome'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                error_log("Required field missing: $field");
                throw new \InvalidArgumentException("Campo obrigatório não preenchido: {$field}");
            }
        }
        
        // Validate codigo_ibge uniqueness (excluding current record)
        $stmt = $pdo->prepare("SELECT id FROM t_municipios WHERE codigo_ibge = ? AND id != ?");
        $stmt->execute([$data['codigo_ibge'], $id]);
        if ($stmt->fetch()) {
            throw new \InvalidArgumentException("Código IBGE já existe.");
        }
        
        try {
            $sql = "UPDATE t_municipios SET codigo_ibge = ?, nome = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                $data['codigo_ibge'],
                $data['nome'],
                $id
            ]);
        } catch (\PDOException $e) {
            throw new \RuntimeException("Erro ao atualizar município: " . $e->getMessage());
        }
    }

    /**
     * Delete municipio (soft delete by setting ativo = 0)
     */
    public static function deleteMunicipio(int $id): bool
    {
        $pdo = \App\Core\DBConnection::getInstance()->getConnection();
        
        // Check if municipio exists
        $stmt = $pdo->prepare("SELECT id FROM t_municipios WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new \InvalidArgumentException("Município não encontrado.");
        }
        
        // Check if municipio is being used by empresas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM empresas WHERE municipio_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            throw new \InvalidArgumentException("Não é possível excluir este município pois existem {$count} empresa(s) vinculada(s) a ele.");
        }
        
        // Hard delete if no references
        $stmt = $pdo->prepare("DELETE FROM t_municipios WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

// ... Outras classes de modelos podem ser adicionadas aqui
// 
