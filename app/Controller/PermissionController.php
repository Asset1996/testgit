<?php 
declare(strict_types=1);
namespace MyApp\Controller;
//PSR7
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Container\ContainerInterface;
use MyApp\Publicapi\Cpanel\Cpanel_PublicAPI;
use Slim\Views\Twig;
use MyApp\Model\Users;
use MyApp\Model\Servers;
use MyApp\Model\Permission;


class PermissionController{

  private $pdo;
  private $container;
  private $logger;

  public function __construct(ContainerInterface $container){
      $this->container = $container;
      $this->logger = $this->container->get('logger');
      $this->pdo = $this->container->get("pdo");
  }

  public function users(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $flash =$this->container->get('flash');
    $messages = $flash->getMessages();
    $users = new Users($this->pdo, $this->container);
    $users_list = $users->get_list();

    $view = Twig::fromRequest($request);
    return $view->render($response, '/permission/users.html.twig', [
      'users_list' => $users_list,
      'messages' => $messages
    ]);
  }

  public function user_permission_info(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $iin = (int)$args['iin'];
    $user = new Users($this->pdo);
    $user_info = $user->get_one_by_iin($iin);

    $flash = $this->container->get('flash');
    $messages = $flash->getMessages();

    $permission = new Permission($this->pdo);
    $permitted_account_list = $permission->get_list_by_iin($iin);

    // echo '<pre>' . print_r($permitted_account_list, true);exit();

    $view = Twig::fromRequest($request);
    return $view->render($response, '/permission/account_list.html.twig', [
      'account_list' => $permitted_account_list,
      'user_info' => $user_info,
      'messages' => $messages
    ]);
  }

  public function add_permission(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $iin = (int)$args['iin'];
    $user = new Users($this->pdo);
    $user_info = $user->get_one_by_iin($iin);

    $flash =$this->container->get('flash');
    $messages = $flash->getMessages();

    $servers = new Servers($this->pdo);
    $server_list = $servers->get_list();

    // echo '<pre>' . print_r($server_list, true);exit();

    $view = Twig::fromRequest($request);
    return $view->render($response, '/permission/add_permission.html.twig', [
      'server_list' => $server_list,
      'user_info' => $user_info,
      'messages' => $messages
    ]);
  }

  //Админ напрямую без запроса открывает доступ к CPanel аккаунту
  public function get_access(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $data = $request->getParsedBody();
    $exploded = explode(',', $data['cplogin']);

    $data_for_db = [];
    $data_for_db['status'] = 2;
    $data_for_db['user_iin'] = (int)$args['iin'];
    $data_for_db['server_id'] = (int)$data['servername'];
    $data_for_db['account_name'] = (string)$exploded[0];
    $data_for_db['account_username'] = (string)$exploded[1];

    $permission = new Permission($this->pdo, $this->container);
    $result = $permission->add_new_permission($data_for_db);

    if($result == true){
      $this->container->get('flash')->addMessage('success', 'Доступ был успешно открыт.');
      return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/permission/user-permission-info/'.$args['iin'])->withStatus(200);
    }else{
      $this->container->get('flash')->addMessage('error', 'Произошла ошибка при попытке откыть доступ.');
      return $response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(200);
    }

    return $response->withHeader('Content-Type', 'application/html')->withStatus(200);
  }

  //VIA API
  public function get_accounts(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{
    
    $data = $request->getParsedBody();

    $servers = new Servers($this->pdo);
    $server_data = $servers->get_one((int)$data['id']);

    $config = array(
      'service' => array(
          'whm' => array(
              'config'    => array(
                  'host' => $server_data['title'],
                  'user' => $server_data['login'],
                  'password' => $server_data['password']
              ),
          ),
      ),
    );

    $cp = \Cpanel_PublicAPI::getInstance($config);

    $accounts = $cp->whm_api('listaccts',array('search'=>'XXXXXXXXX','searchtype'=>'owner'));
     
    $accounts = $accounts->getResponse('array')['acct'];

    $text = "";
    foreach($accounts as $account){
      $text .= "<option value='" . $account['domain'] . ',' . $account['user'] . "'>" . $account['domain'] . "</option>";
    }

    $response->getBody()->write($text);
    return $response->withHeader('Content-Type', 'application/html')->withStatus(200);;
  }

  //Увольнение пользователя
  public function dismiss_user(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $iin = (int)$args['iin'];

    if((int)$_SESSION['user']['iin'] == $iin){
      $this->container->get('flash')->addMessage('error', 'Вы не можете удалить самого себя!');
      return $response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(200);
    }

    $user = new Users($this->pdo);

    if($user->dismiss_user($iin) == TRUE){
      $this->container->get('flash')->addMessage('success', 'Пользователь был отмечен как "Уволен"');
    }else{
      $this->container->get('flash')->addMessage('error', 'Пользователь НЕ был отмечен как "Уволен"');
    };    

    return $response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(200);
  }

  //Пользователь запрашивает доступ у админа
  public function request_access(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $logger = $this->logger;
    $logger->set_action_id(6);
    $logger->set_user_iin((int)$_SESSION['user']['iin']);

    $data_for_db = [];
    $data_for_db['status'] = 0;
    $data_for_db['user_iin'] = (int)$_SESSION['user']['iin'];
    $data_for_db['server_id'] = (int)$args['server_id'];
    $data_for_db['account_name'] = (string)$args['account_name'];
    $data_for_db['account_username'] = (string)$args['account_username'];

    $permission = new Permission($this->pdo, $this->container);
    $result = $permission->add_new_permission($data_for_db);

    if($result == true){
      $this->container->get('flash')->addMessage('success', 'Запрос на доступ к ' . $args['account_name'] . ' был успешно отправлен.');
      $logger->set_action_description('Был успешно совершен запрос на доступ к ' . $args['account_name']);
      $logger->set_action();
      return $response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(200);
    }else{
      $this->container->get('flash')->addMessage('error', 'Произошла ошибка при попытке запросить доступ.');
      $logger->set_action_description('Произошла ошибка при попытке запросить доступ к ' . $args['account_name']);
      $logger->set_action();
      return $response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(200);
    }
  }

  //Админ отвечает на запрос пользователя - выводит решение - отклонить или одобрить
  public function decision_access(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{
    
    $logger = $this->logger;
    $logger->set_action_id(8);
    $logger->set_user_iin((int)$_SESSION['user']['iin']);

    $id = (int)$args['id'];
    $decision = (int)$args['decision'];
    $permission = new Permission($this->pdo, $this->container);

    if($permission->make_decision($id, $decision) == TRUE){
      $permission_info = $permission->get_one($id);
      if($decision == 1){
        $logger->set_action_description(
          'Доступ успешно открыт к домену: ' . $permission_info['account_name'] . 
          ', под именем пользователя CPanel: ' . $permission_info['account_username'] . 
          ' для пользователя по ИИН: ' . $permission_info['user_iin']
        );
        $this->container->get('flash')->addMessage('success', 'Доступ успешно открыт');
      }elseif($decision == 2){
        $logger->set_action_description(
          'Доступ отклонен к домену: ' . $permission_info['account_name'] . 
          ', под именем пользователя CPanel: ' . $permission_info['account_username'] . 
          ' для пользователя по ИИН: ' . $permission_info['user_iin']
        );
        $this->container->get('flash')->addMessage('success', 'Доступ успешно отклонен');
      }else{
        $logger->set_action_description(
          'Доступ снят с домена: ' . $permission_info['account_name'] . 
          ', под именем пользователя CPanel: ' . $permission_info['account_username'] . 
          ' для пользователя по ИИН: ' . $permission_info['user_iin']
        );
        $this->container->get('flash')->addMessage('success', 'Доступ успешно снят');
      }
    }else{
      $this->container->get('flash')->addMessage('error', 'Запрос не был обработан');
    };

    $logger->set_action();

    return $response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(200);
  }
}