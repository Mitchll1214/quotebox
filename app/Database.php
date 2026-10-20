<?php
/**
 * Thin PDO wrapper with named table placeholders, e.g. {quotes}.
 */

declare(strict_types=1);

final class Database
{
    /** @var PDO */
    private $pdo;

    /** @var string */
    private $prefix;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 3306);
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $prefix = (string) ($config['prefix'] ?? 'qb_');

        if (!preg_match('/^[A-Za-z0-9_]{0,24}$/', $prefix)) {
            throw new RuntimeException('表前缀只能包含字母、数字和下划线。');
        }

        if (!in_array($charset, array('utf8mb4', 'utf8'), true)) {
            $charset = 'utf8mb4';
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        $this->pdo = new PDO($dsn, $username, $password, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ));
        $this->prefix = $prefix;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function table(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new InvalidArgumentException('Invalid table name.');
        }

        return '`' . $this->prefix . $name . '`';
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function run(string $sql, array $params = array()): PDOStatement
    {
        $statement = $this->pdo->prepare($this->expand($sql));
        $statement->execute($params);

        return $statement;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function one(string $sql, array $params = array()): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function all(string $sql, array $params = array()): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /**
     * @param array<int|string, mixed> $params
     * @return mixed
     */
    public function value(string $sql, array $params = array())
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $quoted = array();
        foreach ($columns as $column) {
            $quoted[] = $this->quoteIdentifier($column);
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO ' . $this->table($table) . ' (' . implode(', ', $quoted) . ') VALUES (' . $placeholders . ')';
        $this->run($sql, array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int|string, mixed> $params
     */
    public function update(string $table, array $data, string $where, array $params = array()): int
    {
        $sets = array();
        foreach (array_keys($data) as $column) {
            $sets[] = $this->quoteIdentifier($column) . ' = ?';
        }

        $sql = 'UPDATE ' . $this->table($table) . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        $statement = $this->run($sql, array_merge(array_values($data), $params));

        return $statement->rowCount();
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function delete(string $table, string $where, array $params = array()): int
    {
        return $this->run('DELETE FROM ' . $this->table($table) . ' WHERE ' . $where, $params)->rowCount();
    }

    /**
     * Replace table placeholders in raw SQL.
     */
    private function expand(string $sql): string
    {
        return (string) preg_replace_callback('/\{([A-Za-z0-9_]+)\}/', function (array $match): string {
            return $this->table($match[1]);
        }, $sql);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException('Invalid column name.');
        }

        return '`' . $identifier . '`';
    }
}
