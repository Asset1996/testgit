<?php
namespace MyApp\Model;
use Psr\Container\ContainerInterface;
use PDO;
use FaaPz\PDO\Database;
use FaaPz\PDO\Clause\Join;
use FaaPz\PDO\Clause\Conditional;
use FaaPz\PDO\Clause\Grouping;
use PDOException;

class Users{
    
    /**
     *
     * @var string $table_name Наименование таблицы в бд
     */
    public static $table_name = "users";

    /**
     *
     * @var $id Идентификатор
     */
    protected $id;

    /**
     *
     * @var int $iin ИИН
     */
    protected $iin;
    
    /**
     *
     * @var string $name 
     */
    protected $name;

    /**
     *
     * @var string $surname 
     */
    protected $surname;

    /**
     *
     * @var string $lastname 
     */
    protected $lastname;

    /**
     *
     * @var string $email 
     */
    protected $email;

    /**
     *
     * @var string $password_hash 
     */
    protected $password_hash;

    /**
     *
     * @var string $registration_token 
     */
    protected $registration_token;

    /**
     *
     * @var bool $is_registrated 
     */
    protected $is_registrated;

    /**
     *
     * @var int $position_id 
     */
    protected $position_id;

    /**
     *
     * @var int $department_id 
     */
    protected $department_id;

    /**
     *
     * @var bool $is_in_state 
     */
    protected $is_in_state;

    /**
     *
     * @var bool $is_dismissed 
     */
    protected $is_dismissed;

    /**
     *
     * @var Database $pdo
     */
    private $pdo;

    /**
     *
     * @var Database $pdo
     */
    private $container;

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
     * Инфо обо всех пользователях, которые поттвердили свою почту
     *
     * @return bool|array
     */
    public function get_list()
    {
        $sql = "SELECT u.iin, u.name, u.surname, u.lastname, u.email, u.role, 
            SUM(if(p.status = 0, 1, 0)) as new_requests
            FROM " . $this::$table_name . " u
            LEFT JOIN permissions p ON u.iin=p.user_iin
            GROUP BY u.iin";
        
        $stmt= $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Вывести инфо о пользователе по ИИН
     *
     * @return bool|array
     */
    public function get_one_by_iin(int $iin)
    {
        $query = $this->pdo->select()
        ->from($this::$table_name)
        ->where(new Conditional('iin', '=', $iin));
        $stmt = $query->execute();
        return $stmt->fetch();
    }

    /**
     * Вывести инфо о пользователе по email
     *
     * @return bool|array
     */
    public function get_one_by_email(string $email)
    {
        $email .= '@mediana.kz';
        $query = $this->pdo->select()
        ->from($this::$table_name)
        ->where(new Conditional('email', '=', $email));
        $stmt = $query->execute();
        return $stmt->fetch();
    }

    /**
     * Проверка - зарегистрирован ли данный пользователь
     * @param $data
     * @return bool
     */
    public function is_registered(array $data)
    {   
        $email = $data['email'] . '@mediana.kz';
        $query = $this->pdo
            ->select()
            ->from($this::$table_name)
            ->where(new Conditional('email', '=', $email));
        $stmt = $query->execute();
        $user_data = $stmt->fetch();

        if($user_data == NULL) {
            $this->container->get('flash')->addMessage('error', 'Вы неправильно ввели почтовый адрес');
            return false;
        }elseif($user_data['is_dismissed'] == 1){
            $this->container->get('flash')->addMessage('error', 'У вас больше нет доступа.');
            return false;
        }elseif($user_data['is_registrated'] == 0){
            $this->container->get('flash')->addMessage('error', 'Пожалуйста, подтвердите свой адрес электронной почты по ссылке, которую мы вам отправили.');
            return false;
        }

        if(!password_verify($data['password'], $user_data['password_hash'])){
            $this->container->get('flash')->addMessage('error', 'Вы неправильно ввели пароль');
            return false;
        }

        $this->container->get('flash')->addMessage('success', 'Вы успешно авторизовались');
        $_SESSION['user'] = $user_data;
        return true;
    }

    /**
     * Регистрация нового пользователя
     * @param $data
     * @return bool
     */
    public function register_new_user(array $data){

        $email = $data['email'].'@mediana.kz';

        $prepare_array = [
            'iin' => $data['iin'],
            'name' => (string)$data['name'],
            'surname' => (string)$data['surname'],
            'lastname' => isset($data['lastname']) ? (string)$data['lastname'] : '',
            'email' => $email,

            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'registration_token' => uniqid(),
            'department_id' => isset($data['department_id']) ? $data['iin'] : 1,
        ];

        //Ready array to insert without ''
        $insert_array = array_diff($prepare_array, array(''));

        try{
            $insertStatement = $this->pdo->insert($insert_array)->into($this::$table_name);
            $insertId = $insertStatement->execute();
            return $insert_array['registration_token'];
        }catch(PDOException $e){
            if($e->errorInfo[0] == 23000){
                $this->container->get('flash')->addMessage('error', 'Такой пользователь уже есть в базе.');
                return false;
            }
            throw new PDOException($e);
        }
    }

    /**
     * Подтверждение пароля с почты
     * @param $token
     * @return bool
     */
    public function confirm_email(string $token){

        try{
            $sql = "UPDATE " . $this::$table_name . " SET `registration_token`='', `is_registrated`=1 WHERE `registration_token`='$token'";
            $stmt= $this->pdo->prepare($sql);
            return $stmt->execute();

        }catch(PDOException $e){
            throw new PDOException($e);
        }
    }

    /**
     * Сеттер нового пароля через почту
     * @param $token
     * @return bool
     */
    public function set_new_password(string $email, string $password){

        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        try{
            $sql = "UPDATE " . $this::$table_name . " SET `registration_token`='', `is_registrated`=1, `password_hash`='$password_hash' WHERE `email`='$email'";
            $stmt= $this->pdo->prepare($sql);
            return $stmt->execute();

        }catch(PDOException $e){
            throw new PDOException($e);
        }
    }

    /**
     * Увольнение пользователя
     * @param $iin
     * @return bool
     */
    public function dismiss_user(int $iin){

        $updateStatement = $this->pdo->update(["is_dismissed" => 1, 'is_registrated' => 0])
            ->table($this::$table_name)
            ->where(new Conditional("iin", "=", $iin));

        $affectedRows = $updateStatement->execute();

        $count = $affectedRows->rowCount();

        return $count > 0 ? true : false;
    }
}

?>