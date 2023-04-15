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
use MyApp\Model\SprDepartments;
use MyApp\Model\Servers;
use MyApp\Model\Permission;


class ServersController{

  private $pdo;
  private $container;
  private $logger;

  public function __construct(ContainerInterface $container){
      $this->container = $container;
      $this->logger = $this->container->get('logger');
      $this->pdo = $this->container->get("pdo");
  }

  public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{
    $flash =$this->container->get('flash');
    $messages = $flash->getMessages();

    $servers = new Servers($this->pdo);
    $servers_list = $servers->get_list_has_permission();

    $view = Twig::fromRequest($request);
    return $view->render($response, '/servers/index.html.twig', [
      'servers' => $servers_list,
      'messages' => $messages
    ]);
  }

  public function get_access(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{
    
    $logger = $this->logger;
    $logger->set_action_id(7);
    $data = $request->getParsedBody();

    $servers = new Servers($this->pdo);
    $server_data = $servers->get_one((int)$data['server_id']);

    $whmusername = $server_data['login'];
    $whmpassword = $server_data['password'];
    $servername = $server_data['title'];
    $domain_info = explode(',', $data['account_name']);
    $cpanel_user = $domain_info[0];
    $domain_name = $domain_info[1];

    $query = "https://" . $servername . ":2087/json-api/create_user_session?api.version=1&user=$cpanel_user&service=cpaneld";

    $curl = curl_init();                                     // Create Curl Object.
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);       // Allow self-signed certificates...
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);       // and certificates that don't match the hostname.
    curl_setopt($curl, CURLOPT_HEADER, false);               // Do not include header in output
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);        // Return contents of transfer on curl_exec.
    $header[0] = "Authorization: Basic " . base64_encode($whmusername . ":" . $whmpassword) . "\n\r";
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);         // Set the username and password.
    curl_setopt($curl, CURLOPT_URL, $query);                 // Execute the query.
    $result = curl_exec($curl);

    //Unknown error
    if ($result == false) {
        $response->getBody()->write('<div class="access_denied">Возникла ошибка. Пожалуйста, повторите попытку.</div>');
        $logger->set_action_description('Ошибка: возникла неизвестная ошибка при попытке получить временный сеанс доступа к домену: ' . $domain_name . ', в качестве пользователя: ' . $cpanel_user . ', на сервере: ' . $servername);
        return $response->withHeader('Content-Type', 'application/html')->withStatus(200);
    }

    $decoded_response = json_decode($result, true);

    //Access denied
    if (isset($decoded_response['cpanelresult'])){
        if($decoded_response['cpanelresult']['data']['result'] == 0)
            $response->getBody()->write('<div class="access_denied">Доступ запрещен</div>');
            $logger->set_action_description('Ошибка: доступ запрещен при попытке получить временный сеанс доступа к домену: ' . $domain_name . ', в качестве пользователя: ' . $cpanel_user . ', на сервере: ' . $servername);
            return $response->withHeader('Content-Type', 'application/html')->withStatus(200);
    }

    //Invalid username
    if ($decoded_response['metadata']['result'] == 0) {
      if($decoded_response['metadata']['result'] !== null){
        $response->getBody()->write('<div class="access_denied">' . $decoded_response['metadata']['reason'] . '</div>');
      }else{
        $response->getBody()->write('<div class="access_denied">Пользователь не найден</div>');
      }
      $logger->set_action_description('Ошибка: пользователь не найден при попытке получить временный сеанс доступа к домену: ' . $domain_name . ', в качестве пользователя: ' . $cpanel_user . ', на сервере: ' . $servername);
      return $response->withHeader('Content-Type', 'application/html')->withStatus(200);
    }

    curl_close($curl);
    if(isset($decoded_response['data']['url'])){
        $logger->set_action_description('Доступ разрешен к домену: ' . $domain_name . ', в качестве пользователя: ' . $cpanel_user . ', на сервере: ' . $servername);
        $response->getBody()->write("<div class='access_granted'>Доступ разрешен в качестве пользователя <strong>" . $cpanel_user . "</strong>. Переходите по <a href=" . $decoded_response['data']['url'] . " target='blank'>Ссылке</a>.");
    }

    $logger->set_user_iin((int)$_SESSION['user']['iin']);
    $logger->set_action();

    return $response->withHeader('Content-Type', 'application/html')->withStatus(200);
  }

  public function get_accounts(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $data = $request->getParsedBody();
    $server_id = (int)$data['id'];
    $iin = (int)$data['iin'];

    $permission = new Permission($this->pdo);
    $accounts = $permission->get_list_by_iin_and_server_id_only_approved($iin, $server_id);

    $text = "";
    foreach($accounts as $account){
      if($account['max']['status'] == 1){
        $text .= "<option value='" . $account['max']['account_username'] . "," . $account['max']['account_name'] . "'>" . $account['max']['account_name'] . "</option>";
      }
    }

    $response->getBody()->write($text);
    return $response->withHeader('Content-Type', 'application/html')->withStatus(200);;
  }

  public function handbook(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $flash =$this->container->get('flash');
    $messages = $flash->getMessages();

    $servers = new Servers($this->pdo);
    $servers_list = $servers->get_list();

    $view = Twig::fromRequest($request);
    return $view->render($response, '/servers/handbook.html.twig', [
      'servers' => $servers_list,
      'messages' => $messages
    ]);

  }

  //VIA API
  public function get_accounts_handbook(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $data = $request->getParsedBody();

    $server_id = (int)$data['id'];
    $servers = new Servers($this->pdo);
    $server_data = $servers->get_one($server_id);

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
    
    $text = "
    <div class='accordion accordion-flush' id='accordionFlushExample'>
      <div class='accordion-item'>
        <h2 class='accordion-header' id='flush-headingOne'>
          <button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' data-bs-target='#flush-collapseOne' aria-expanded='false' aria-controls='flush-collapseOne'>
            Server description
          </button>
        </h2>
        <div id='flush-collapseOne' class='accordion-collapse collapse' aria-labelledby='flush-headingOne' data-bs-parent='#accordionFlushExample'>
          <div class='accordion-body'>
            " . $server_data['description'] . "
          </div>
        </div>
      </div>
    </div>
      <table class='table table-striped'>
        <thead>
          <tr>
            <th scope='col'>#</th>
            <th scope='col'>Домен</th>
            <th scope='col'>Пользователь</th>
            <th scope='col'>Запросить доступ</th>
          </tr>
        </thead>
      <tbody>";
    $i = 1;
    
    
    foreach($accounts as $account){
       
      $text .= "
        <tr>
          <th scope='row'>" . $i . "</th>
          <td>". $account['domain'] . "</td>
          <td>" . $account['user'] . "</td>
          <td><a href='/" . $_ENV['VERSION'] . "/permission/request-access/" . $server_id . "/" . $account['domain'] . "/" . $account['user'] . "' class='btn btn-info'>Запросить</a></td>
        </tr>";
      ++$i;
    }
    $text .= "</tbody></table>";

    $response->getBody()->write($text);
    return $response->withHeader('Content-Type', 'application/html')->withStatus(200);
  }

  public function search_account(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $data = $request->getParsedBody();

    $keyword = (string)$data['keyword'];

    $server_id = (int)$data['server_id'];
    $servers = new Servers($this->pdo);
    $server_data = $servers->get_one($server_id);

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
 
    foreach($accounts as $key => $account){
      if(!str_starts_with($account['domain'], $keyword)){
        unset($accounts[$key]);
      }
    }

    $text = "
    <div class='accordion accordion-flush' id='accordionFlushExample'>
      <div class='accordion-item'>
        <h2 class='accordion-header' id='flush-headingOne'>
          <button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' data-bs-target='#flush-collapseOne' aria-expanded='false' aria-controls='flush-collapseOne'>
            Server description
          </button>
        </h2>
        <div id='flush-collapseOne' class='accordion-collapse collapse' aria-labelledby='flush-headingOne' data-bs-parent='#accordionFlushExample'>
          <div class='accordion-body'>
            " . $server_data['description'] . "
          </div>
        </div>
      </div>
    </div>
      <table class='table table-striped'>
        <thead>
          <tr>
            <th scope='col'>#</th>
            <th scope='col'>Домен</th>
            <th scope='col'>Пользователь</th>
            <th scope='col'>Запросить доступ</th>
          </tr>
        </thead>
      <tbody>";
    $i = 1;
    
    
    foreach($accounts as $account){
      $text .= "
        <tr>
          <th scope='row'>" . $i . "</th>
          <td>". $account['domain'] . "</td>
          <td>" . $account['user'] . "</td>
          <td><a href='/" . $_ENV['VERSION'] . "/permission/request-access/" . $server_id . "/" . $account['domain'] . "/" . $account['user'] . "' class='btn btn-info'>Запросить</a></td>
        </tr>";
      ++$i;
    }
    $text .= "</tbody></table>";

    $response->getBody()->write($text);
    return $response->withHeader('Content-Type', 'application/html')->withStatus(200);
  }

  public function add_server(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $flash =$this->container->get('flash');
    $messages = $flash->getMessages();

    $view = Twig::fromRequest($request);
    return $view->render($response, '/servers/add_server.html.twig', [
      'messages' => $messages
    ]);
  }

  public function delete_server(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $logger = $this->logger;
    $logger->set_action_id(13);
    $logger->set_user_iin((int)$_SESSION['user']['iin']);

    $id = (int)$args['id'];
    $server = new Servers($this->pdo);
    $server_info = $server->get_one($id);

    if($server->delete_server($id) == TRUE){
      $this->container->get('flash')->addMessage('success', 'Сервер успешно удален');
      $logger->set_action_description('Сервер ' . $server_info['title'] . ' успешно удален');
    }else{
      $this->container->get('flash')->addMessage('error', 'Сервер не был удален');
      $logger->set_action_description('Произведена попытка удаления сервера');
    };

    $logger->set_action();
    return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/servers/list')->withStatus(200);
  }

  public function add_server_into_db(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $logger = $this->logger;
    $logger->set_action_id(12);
    $logger->set_user_iin((int)$_SESSION['user']['iin']);
    $data = $request->getParsedBody();

    $servers = new Servers($this->pdo);
    $result = $servers->add_new_server($data);
    if($result == TRUE){
      $text = "<div class='access_granted'>Сервер <strong>" . $data['title'] . "</strong> успешно создан</div>";
      $logger->set_action_description('Сервер ' . $data['title'] . ' успешно создан');
    }else{
      $text = "<div class='access_denied'>Произошла ошибка</div>";
      $logger->set_action_description('Произошла при попытке добавить сервер в базу');
    };
    
    $logger->set_action();
    $response->getBody()->write($text);
    return $response->withHeader('Content-Type', 'application/html')->withStatus(200);
  }

  public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $servers = new Servers($this->pdo);
    $servers_list = $servers->get_list();

    $flash =$this->container->get('flash');
    $messages = $flash->getMessages();

    $view = Twig::fromRequest($request);

    return $view->render($response, '/servers/list.html.twig', [
      'servers_list' => $servers_list,
      'messages' => $messages
    ]);
  }

  public function test(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    echo 'hhhhhh';exit();

    $view = Twig::fromRequest($request);
    return $view->render($response, '/servers/add_server.html.twig', [
      'messages' => $messages
    ]);
  }
}