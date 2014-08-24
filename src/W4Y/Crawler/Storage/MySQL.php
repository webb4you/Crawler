<?php
namespace W4Y\Crawler\Storage;
use W4Y\Crawler\Crawler;

/**
 * MySQL DB
 *
 * Save data in SQL lite database.
 *
 */
class MySQL implements StorageInterface
{
    /** @var \SQLiteDatabase $db */
    private $db = array();

    public function __construct($user, $pass, $database, $host = '127.0.0.1')
    {
        $db = $this->createConnection($user, $pass, $database, $host);
        $this->db = $db;

        $this->setUpDatabase();
    }

    public function get($dataType, $singleResult = false)
    {
        $queryTpl = "SELECT * FROM `%s` ORDER BY id ASC";
        $query = sprintf($queryTpl, $dataType);

        /** @var \PDOStatement  $pdo */
        $pdo = $this->db->query($query);

        $data = array();
        foreach ($pdo as $result) {
            $jsonData = json_decode($result['data'], true);
            $parent = $result['parentKey'];
            if (!empty($parent)) {
                if (empty($data[$parent])) {
                    $data[$parent] = array();
                }
                $data[$parent] = array_merge($data[$parent], $jsonData);
            } else {
                $data = array_merge($data, $jsonData);
            }

        }

        return $data;
    }

    public function add($dataType, $data, $parent = null)
    {
        $dataKey = current(array_keys($data));

        if (empty($parent)) {
            $parent = '';
        }

        $queryTpl = "REPLACE INTO `%s` SET `key` = '%s', data = '%s', `parentKey` = '%s'";
        $query = sprintf($queryTpl, $dataType, $dataKey, (json_encode($data)), $parent);

        $this->db->exec($query);

        return $this->get($dataType);
    }

    public function remove($dataType, $id)
    {
        $queryTpl = "DELETE FROM `%s` WHERE `key` = '%s'";
        $query = sprintf($queryTpl, $dataType, $id);
        $this->db->exec($query);
    }

    public function has($dataType, $id)
    {
        $queryTpl = "SELECT id FROM `%s` WHERE `key` = '%s'";
        $query = sprintf($queryTpl, $dataType, $id);

        /** @var PDOStatement $result */
        $result = $this->db->query($query);

        return (bool) $result->rowCount();
    }

    public function reset()
    {
        $tables = $this->getTables();
        $queryTpl = 'DROP TABLE IF EXISTS `%s`';
        foreach ($tables as $tableName) {
            $query = sprintf($queryTpl, $tableName);
            $this->db->exec($query);
        }

        $this->setUpDatabase();
    }

    private function getTables()
    {
        $tables = array(
            Crawler::DATA_TYPE_CRAWLED,
            Crawler::DATA_TYPE_CRAWLED_EXTERNAL,
            Crawler::DATA_TYPE_CRAWLER_FOUND,
            Crawler::DATA_TYPE_EXCLUDED,
            Crawler::DATA_TYPE_EXTERNAL_URL,
            Crawler::DATA_TYPE_FAILED,
            Crawler::DATA_TYPE_PENDING
        );

        return $tables;
    }

    private function createConnection($user, $password, $database, $host)
    {
        $dsn = sprintf('mysql:dbname=%s;host=%s', $database, $host);
        $db = null;

        $db = new \PDO($dsn, $user, $password);

        return $db;
    }

    private function setUpDatabase()
    {
        $tables = $this->getTables();

        $queryTpl = 'CREATE TABLE `%s` (id INT AUTO_INCREMENT, `parentKey` char(32), `key` char(32), `data` MEDIUMTEXT, PRIMARY KEY (id), UNIQUE (`parentKey`, `key`))';

        foreach ($tables as $tableName) {
            $query = sprintf($queryTpl, $tableName);
            $this->db->exec($query);
        }
    }

}