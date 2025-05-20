<?php
namespace app\repositories;

use PDO;
use app\models\BaseModel;

abstract class BaseRepository
{
    protected PDO $pdo;
    protected string $table;
    protected string $modelClass;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function find($id): ?BaseModel
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? new $this->modelClass($data) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new $this->modelClass($row);
        }
        return $results;
    }
}
