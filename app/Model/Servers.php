<?php
namespace MyApp\Model;
use PDO;
use FaaPz\PDO\Database;
use FaaPz\PDO\Clause\Join;
use FaaPz\PDO\Clause\Conditional;
use FaaPz\PDO\Clause\Grouping;
use FaaPz\PDO\Clause\Raw;
use PDOException;

class Servers{
    
    /**
     *
     * @var string $table_name Наименование таблицы в бд
     */
    public static $table_name = "mediana_servers";

    /**
     *
     * @var $id Идентификатор
     */
    protected $id;

    /**
     *
     * @var string $iin title
     */
    protected $title;
    
    /**
     *
     * @var string $login 
     */
    protected $login;

    /**
     *
     * @var string $password 
     */
    protected $password;

    /**
     *
     * @var int $created_by 
     */
    protected $created_by;

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
    public function is_exist()
    {
        $query = $this->pdo->select()->from($this::$table_name)->where(new Conditional('id', '=', $this->id));
        $stmt = $query->execute();
        $data = $stmt->fetch();
        return is_array($data);
    }

    /**
     * Одна запись по ID
     *
     * @return bool|array
     */
    public function get_one(int $id)
    {
        $query = $this->pdo->select()
        ->from($this::$table_name)
        ->where(new Conditional('id', '=', $id));
        $stmt = $query->execute();
        return $stmt->fetch();
    }

    /**
     * Все записи
     *
     * @return array
     */
    public function get_list()
    {
        $query = $this->pdo->select()
        ->from($this::$table_name);
        $stmt = $query->execute();
        return $stmt->fetchAll();
    }

    /**
     * Список серверов, у которых есть доступные аккаунты
     *
     * @return array
     */
    public function get_list_has_permission()
    {
        $iin = (int)$_SESSION['user']['iin'];
        $query = $this->pdo->select([$this::$table_name.'.*'])
        ->from($this::$table_name)
        ->join(new Join('permissions', new Conditional($this::$table_name.'.id', '=', new Raw('permissions.server_id')), 'LEFT'))
        ->where(new Grouping('AND', new Conditional('permissions.user_iin', '=', $iin), new Conditional('permissions.status', '=', 1)) )
        ->groupBy($this::$table_name.'.id');
        $stmt = $query->execute();
        return $stmt->fetchAll();
    }

    public function add_new_server(array $data){

        $insert_array = [
            'title' => $data['title'],
            'login' => $data['login'],
            'password' => $data['password'],
            'description' => $data['description']
        ];

        try{
            $insertStatement = $this->pdo->insert($insert_array)->into($this::$table_name);
            $insertId = $insertStatement->execute();
            return true;
        }catch(PDOException $e){
            throw new PDOException($e);
        }
    }

    public function delete_server(int $id){

        $stmt = $this->pdo->prepare("DELETE FROM " . $this::$table_name . " WHERE id=" . $id);
        $stmt->execute();
        $count = $stmt->rowCount();

        return $count > 0 ? true : false;
    }
}
?>