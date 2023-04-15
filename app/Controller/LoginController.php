<?php 
declare(strict_types=1);
namespace MyApp\Controller;
//PSR7
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Container\ContainerInterface;
use Slim\Views\Twig;
use MyApp\Model\Users;
use MyApp\Model\SprDepartments;

class LoginController{

  private $pdo;
  private $container;

  public function __construct(ContainerInterface $container){
      $this->container = $container;
      $this->pdo = $this->container->get("pdo");
  }

  public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{
    
    if(isset($_SESSION['user'])){
        return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/servers/')->withStatus(200);
    }

    $flash =$this->container->get('flash');
    $messages = $flash->getMessages();

    $view = Twig::fromRequest($request);

    return $view->render($response, '/login/index.html.twig', [
      'messages' => $messages
    ]);
  }

  public function log_in(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{
    
    $data = $request->getParsedBody();
    $user = new Users($this->pdo, $this->container);

    if($user->is_registered($data)){
      
      $log = $this->container->get('logger');
      $log->set_action_id(4);
      $log->set_action_description('Успешная авторизация на сайте');
      $log->set_user_iin((int)$_SESSION['user']['iin']);
      $log->set_action();
      return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/servers/')->withStatus(200);
    }
    return $response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(200);
  }

  public function log_out(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{
    
    if(!isset($_SESSION['user'])){
        return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/servers/')->withStatus(200);
    }
    $logger = $this->container->get('logger');
    $logger->set_action_id(5);
    $logger->set_action_description('Успешный выход из сайта');
    $logger->set_user_iin((int)$_SESSION['user']['iin']);
    $logger->set_action();
    unset($_SESSION['user']);

    return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/login/')->withStatus(200);
  }
}