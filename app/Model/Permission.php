<?php
namespace MyApp\Model;
use MyApp\Model\Servers;
use Psr\Container\ContainerInterface;
use PDO;
use FaaPz\PDO\Database;
use FaaPz\PDO\Clause\Join;
use FaaPz\PDO\Clause\Conditional;
use FaaPz\PDO\Clause\Grouping;
use PDOException;

class Permission{
    
    /**
     *
     * @var string $table_name Наименование таблицы в бд
     */
    public static $table_name = "permissions";

    /**
     *
     * @var $id Идентификатор
     */
    protected $id;

    /**
     *
     * @var int $user_iin
     */
    protected $user_iin;
    
    /**
     *
     * @var int $server_id 
     */
    protected $server_id;

    /**
     *
     * @var string $account_name 
     */
    protected $account_name;

    /**
     *
     * @var string $account_username 
     */
    protected $account_username;

    /**
     *
     * @var Database $pdo
     */
    private $pdo;

    /**
     * Конструктор класса
     */
    public function __construct(Database $pdo = null, ContainerInterface $container = null)
    {
        $this->pdo = $pdo;
        $this->container = $container;
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
     * Все записи по ИИН пользователя
     *
     * @return array
     */
    public function get_list_by_iin(int $iin)
    {
        $servers_table_name = Servers::$table_name;

        $prepare = $this->pdo->prepare(
            "SELECT p.id, p.status, p.user_iin, p.server_id, p.account_name, p.account_username, p.created_at, p.updated_at, ms.title, 
                CASE
                    WHEN p.status=0 THEN 'Нет решения'
                    WHEN p.status=1 THEN 'Одобрен'
                    WHEN p.status=2 THEN 'Отклонен'
                    WHEN p.status=3 THEN 'Доступ снят'
                END as status_str
                FROM " . $this::$table_name . " p
                LEFT JOIN " . $servers_table_name . " ms ON p.server_id = ms.id
                WHERE p.user_iin=" . $iin
        );
        $prepare->execute();
        $data = $prepare->fetchAll();

        return $data;
    }

    /**
     * Все записи по ИИН пользователя и по Id сервера
     *
     * @return array
     */
    public function get_list_by_iin_and_server_id(int $iin, int $server_id)
    {
        $servers_table_name = Servers::$table_name;

        $prepare = $this->pdo->prepare(
            "SELECT * FROM " . $this::$table_name . "
            WHERE user_iin=" . $iin . " AND server_id=" . $server_id
        );
        $prepare->execute();
        $data = $prepare->fetchAll();

        return $data;
    }

    /**
     * ТОЛЬКО одобренные админом запросы по ИИН пользователя и по Id сервера
     *
     * @return array
     */
    public function get_list_by_iin_and_server_id_only_approved(int $iin, int $server_id)
    {
        $servers_table_name = Servers::$table_name;

        $prepare = $this->pdo->prepare(
            "SELECT status, user_iin, server_id, account_name, account_username, updated_at 
            FROM " . $this::$table_name . "
            WHERE user_iin=" . $iin . " AND server_id=" . $server_id . " AND status IN (1,2,3)"
        );
        $prepare->execute();
        $data = $prepare->fetchAll();
        $result = [];
        $new_result = [];
        foreach($data as $d){
            $result[$d['account_name']][] = $d;
        }

        foreach($result as $key=>$res){
            // $result[$key]['max'] = max(array_column($res, 'updated_at'));
            
            foreach($res as $k=>$r){
                if($r['updated_at'] == max(array_column($res, 'updated_at'))){
                    $result[$key]['max'] = $r;
                }
            }
        }

        return $result;
    }

    public function add_new_permission(array $data){
        
        //Проверяет, есть ли уже одобренный доступ либо есть необработанная заявка на этот CPanel account
        $is_have_record = $this->is_have_approved_decision($data);
        // print_r($is_have_record);exit();
        if((string)$is_have_record['status'] == '0'){
            $this->container->get('flash')->addMessage('error', 'На данный CPanel аккаунт вы уже запросили доступ. Ждите обработку администратором.');
            return false;
        }elseif((string)$is_have_record['status'] == '1'){
            $this->container->get('flash')->addMessage('error', 'На данный CPanel аккаунт доступ уже открыт.');
            return false;
        }

        try{
            $insertStatement = $this->pdo->insert($data)->into($this::$table_name);
            $insertId = $insertStatement->execute();
            return true;
        }catch(PDOException $e){
            if(isset($e->errorInfo[0])){
                if($e->errorInfo[0] == 23000){
                    $this->container->get('flash')->addMessage('error', 'Дублирующаяся запись.');
                    return false;
                }
            }
            throw $e;
        }
    }

    //Проверяет, есть ли уже одобренный доступ на этот CPanel account
    protected function is_have_approved_decision(array $data){
        $selectStatement  = $this->pdo->select(['status'])
            ->from($this::$table_name)
            ->where(new Grouping("AND", 
                    new Conditional("status", "IN", [0,1]),
                    new Conditional("user_iin", "=", $data['user_iin']),
                    new Conditional("server_id", "=", $data['server_id']),
                    new Conditional("account_name", "=", $data['account_name']),
                    new Conditional("account_username", "=", $data['account_username'])
                )
            );
        
        $stmt = $selectStatement->execute();
        return $stmt->fetch();
    }

    public function make_decision(int $id, int $decision){

        $updateStatement = $this->pdo->update(['status' => $decision])
            ->table($this::$table_name)
            ->where(new Conditional("id", "=", $id));

        $affectedRows = $updateStatement->execute();

        $count = $affectedRows->rowCount();

        return $count > 0 ? true : false;
    }

    /**
     * Получает кол-во всех новых запросов на доступ
     *
     * @return int
     */
    public function get_all_new_requests(){
        echo 'hhh';exit();
        $selectStatement  = $this->pdo->select(['id'])
            ->from($this::$table_name)
            ->where(new Conditional("status", "=", 0));

        $stmt = $selectStatement->execute();
        return count($stmt->fetchAll());
    }
}
?>