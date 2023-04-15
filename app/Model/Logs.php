<?php
namespace MyApp\Model;
use PDO;
use FaaPz\PDO\Database;
use FaaPz\PDO\Clause\Join;
use FaaPz\PDO\Clause\Conditional;
use PDOException;

class Logs{
    
    /**
     *
     * @var string $table_name Наименование таблицы в бд
     */
    public static $table_name = "action_logs";

    /**
     *
     * @var $id Идентификатор
     */
    protected $id;

    /**
     *
     * @var int $action_id 
     */
    protected $action_id;
    
    /**
     *
     * @var string $action_description
     */
    protected $action_description;

    /**
     *
     * @var int $user_iin
     */
    protected $user_iin;

    /**
     *
     * @var int $page
     */
    protected $page;

    /**
     *
     * @var int $limit
     */
    protected $limit;

    /**
     *
     * @var int $offset
     */
    protected $offset;

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
     * Сеттер page
     *
     * @return bool
     */
    public function set_page(int $page)
    {
        $this->page = $page;
        return true;
    }

    /**
     * Сеттер limit
     *
     * @return bool
     */
    public function set_limit(int $limit)
    {
        $this->limit = $limit;
        return true;
    }

    /**
     * Сеттер offset
     *
     * @return bool
     */
    public function set_offset(int $offset)
    {
        $this->offset = $offset;
        return true;
    }

    /**
     * Сеттер ID лога
     *
     * @return bool
     */
    public function set_action_id(int $id)
    {
        $this->action_id = $id;
        return true;
    }

    /**
     * Сеттер action_description
     *
     * @return bool
     */
    public function set_action_description(string $action_description)
    {
        $this->action_description = $action_description;
        return true;
    }

    /**
     * Сеттер user_iin
     *
     * @return bool
     */
    public function set_user_iin(int $user_iin)
    {
        $this->user_iin = $user_iin;
        return true;
    }

    /**
     * Логирование действия
     *
     * @return bool
     */
    public function set_action()
    {
        $insert_array = [
            'action_id' => $this->action_id, 
            'action_description' => $this->action_description, 
            'user_iin' => $this->user_iin
        ];
        try{
            $insertStatement = $this->pdo->insert($insert_array)->into($this::$table_name);
            $insertId = $insertStatement->execute();
            return true;
        }catch(PDOException $e){
            throw new PDOException($e);
        }
    }

    /**
     * Список всех логов.
     *
     * @return bool
     */
    public function get_list_with_users()
    {
        try{
            $offset = 0;
            $query = "SELECT " . $this::$table_name . ".*, users.name, users.surname, users.lastname FROM " . $this::$table_name;
            $query .= " LEFT JOIN users ON " . $this::$table_name . ".user_iin=users.iin ";
            $query .= " ORDER BY created_at DESC";
            if(isset($this->limit)) $query .= " LIMIT " . $this->limit;
            if(isset($this->offset)) $query .= " OFFSET " . $this->offset;

            $prepared = $this->pdo->prepare($query);
            $prepared->execute();
            $result = $prepared->fetchAll();
            return $result;
        }catch(PDOException $e){
            throw new PDOException($e);
        }
    }

    /**
     * Возвращает общее кол-во записей.
     *
     * @return int
     */
    public function get_total()
    {
        try{
            $query = "SELECT count(id) as total FROM " . $this::$table_name;

            $prepared = $this->pdo->prepare($query);
            $prepared->execute();
            $result = $prepared->fetch();
            return $result['total'];
        }catch(PDOException $e){
            throw new PDOException($e);
        }
    }
}

?>