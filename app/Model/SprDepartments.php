<?php
namespace MyApp\Model;
use PDO;
use FaaPz\PDO\Database;
use FaaPz\PDO\Clause\Join;
use FaaPz\PDO\Clause\Conditional;
use PDOException;

class SprDepartments{
    
    /**
     *
     * @var string $table_name Наименование таблицы в бд
     */
    public static $table_name = "spr_departments";

    /**
     *
     * @var $id Идентификатор
     */
    protected $id;

    /**
     *
     * @var string $title Наименование отдела
     */
    protected $title;
    
    /**
     *
     * @var string $director ФИО начальника отдела
     */
    protected $director;

    /**
     *
     * @var Database $pdo
     */
    private $pdo;

    /**
     * Конструктор класса
     */
    public function __construct(Database $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * Сеттер идентификатор
     * 
     * @param        $id   
     *
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Есть ли в базе
     *
     * @return bool
     */
    public function isExist()
    {
        $query = $this->pdo->select()->from($this::$table_name)->where(new Conditional('id', '=', $this->id));
        $stmt = $query->execute();
        $data = $stmt->fetch();
        return is_array($data);
    }

    /**
     * Все записи по языку
     *
     * @return bool|array
     */
    public function get_list()
    {
        $query = $this->pdo->select(["id", "title"])
        ->from($this::$table_name)
        ->orderBy($this::$table_name.".id", "ASC");
        $stmt = $query->execute();
        return $stmt->fetchAll();
    }

    /**
     *
     * @return bool|array
     */
    public function get()
    {
        $query = $this->pdo->select(["id", "title"])->from($this::$table_name)->where(new Conditional('id', '=', $this->id));
        $stmt = $query->execute();
        $data = $stmt->fetch();
        return $data;
    }
}

?>