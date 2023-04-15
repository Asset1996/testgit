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

class RegistrationController{

  private $pdo;
  private $container;

  public function __construct(ContainerInterface $container){
      $this->container = $container;
      $this->pdo = $this->container->get("pdo");
  }

  public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $flash =$this->container->get('flash');
    $messages = $flash->getMessages();

    $view = Twig::fromRequest($request);
    $departments = new SprDepartments($this->pdo);
    $departments_list = $departments->get_list();

    $users = new Users($this->pdo);
    $users_list = $users->get_list();

    return $view->render($response, '/register/index.html.twig', [
      'departments_list' => $departments_list, 
      'messages' => $messages
    ]);
  }

  public function reg_user(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $data = $request->getParsedBody();
    $user = new Users($this->pdo, $this->container);
    $new_user_token = $user->register_new_user($data);

    if(is_string($new_user_token)){
      $receiver_address = $data['email'].'@mediana.kz';
      $fio = $data["surname"] . ' ' . $data["name"];
      $confirm_url = $_ENV['MAIN_URL'] . '/' . $_ENV['VERSION'] . '/register/email-confirm/' . $new_user_token;
      try{
        $this->container->get('mailer')->sendMessage('email/mail_confirm.twig', ['data' => $data, 'confirm_url' => $confirm_url], 
          function($message) use($receiver_address, $fio) {
            $message->setTo($receiver_address, $fio);
            $message->setSubject('Ссылка на подтверждение было отправлено');
          }
        );
        $logger = $this->container->get('logger');
        $logger->set_action_id(2);
        $logger->set_action_description('Успешная регистрация на сайте');
        $logger->set_user_iin((int)$data['iin']);
        $logger->set_action();
      }catch(\Swift_TransportException $e){
        $this->container->get('flash')->addMessage('error', 'Вы неправильно ввели адрес электронной почты. Такого адреса не существует.');
        return $response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(200);  
      }
      $this->container->get('flash')->addMessage('success', 'Регистрация прошла успешно. На вашу почту отправлено письмо для подтверждения.');
    }
  
    return $response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(200);
  }

  public function email_confirm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{
    $token = $args['token'];
    $user = new Users($this->pdo);
    $confirm = $user->confirm_email($token);
    if($confirm == TRUE){
      $this->container->get('flash')->addMessage('success', 'Электронная почта успешно подтверждена. Можете войти в кабинет.');
      return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/login/')->withStatus(200);
    }else{
      $this->container->get('flash')->addMessage('error', 'Пройзошла ошибка при подтверждении вашей электронной почты');
      return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/register/')->withStatus(200);
    }
  }

  /**
   * Страница восстановления пароля
	 *
	 * @param  ServerRequestInterface
	 * @param  ResponseInterface
   * @param  args
	 *
	 * @return ResponseInterface
	 */
  public function reset_password_by_email(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $flash =$this->container->get('flash');
    $messages = $flash->getMessages();

    $view = Twig::fromRequest($request);

    return $view->render($response, '/register/reset_password_by_email.html.twig', [
      'messages' => $messages
    ]);
  }

  /**
   * Страница восстановления пароля (обработка пост данных и отправка сообщения на почту)
	 *
	 * @param  ServerRequestInterface
	 * @param  ResponseInterface
   * @param  args
	 *
	 * @return ResponseInterface
	 */
  public function reset_password(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $data = $request->getParsedBody();
    $email = $data['email'];
    $user = new Users($this->pdo, $this->container);
    $user_info = $user->get_one_by_email($email);
    if(empty($user_info)) {
      $this->container->get('flash')->addMessage('error', 'Вы неправильно ввели адрес электронной почты. Такого адреса не существует.');
      return $response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(200);
    }
    $reset_token = uniqid();
    $confirm_url = $_ENV['MAIN_URL'] . '/' . $_ENV['VERSION'] . '/register/new-password-by-email/' . $reset_token;
    try{
      $receiver_address = $user_info['email'];
      $fio = $user_info["surname"] . ' ' . $user_info["name"];
      $this->container->get('mailer')->sendMessage('email/mail_confirm.twig', ['data' => $data, 'confirm_url' => $confirm_url], 
        function($message) use($receiver_address, $fio) {
          $message->setTo($receiver_address, $fio);
          $message->setSubject('Ссылка на подтверждение было отправлено');
        }
      );
      // $logger = $this->container->get('logger');
      // $logger->set_action_id(2);
      // $logger->set_action_description('Успешная регистрация на сайте');
      // $logger->set_user_iin((int)$data['iin']);
      // $logger->set_action();
    }catch(\Swift_TransportException $e){
      $this->container->get('flash')->addMessage('error', 'Вы неправильно ввели адрес электронной почты. Такого адреса не существует.');
      return $response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(200);  
    }
    $_SESSION['new_user_token'] = $reset_token;
    $_SESSION['new_user_email'] = $receiver_address;
    $this->container->get('flash')->addMessage('success', 'На вашу почту отправлено письмо со ссылкой на страницу, где вы можете задать новый пароль.');
    return $response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(200);
  }

  /**
   * Страница для ввода нового пароля
	 *
	 * @param  ServerRequestInterface
	 * @param  ResponseInterface
   * @param  args
	 *
	 * @return ResponseInterface
	 */
  public function new_password_by_email(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    if($_SESSION['new_user_token'] !== $args['token']){
      $this->container->get('flash')->addMessage('error', 'Токен не соответсвует.');
      return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/login/')->withStatus(200);
    }else{
      unset($_SESSION['new_user_token']);
    }

    $flash =$this->container->get('flash');
    $messages = $flash->getMessages();

    $view = Twig::fromRequest($request);

    return $view->render($response, '/register/new_password.html.twig', [
      'messages' => $messages
    ]);
  }

  /**
   * Создание нового пароля для пользователя
	 *
	 * @param  ServerRequestInterface
	 * @param  ResponseInterface
   * @param  args
	 *
	 * @return ResponseInterface
	 */
  public function new_password(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $data = $request->getParsedBody();
    if(!array_key_exists('password', $data)){
      $this->container->get('flash')->addMessage('error', 'Вы не ввели новый пароль.');
      return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/login/')->withStatus(200);
    }

    if($_SESSION['new_user_email'] == NULL){
      $this->container->get('flash')->addMessage('error', 'Произошла ошибка.');
      return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/login/')->withStatus(200);
    }else{
      $user = new Users($this->pdo, $this->container);
      if($user->set_new_password($_SESSION['new_user_email'], $data['password'])){
        unset($_SESSION['new_user_email']);
        $this->container->get('flash')->addMessage('success', 'Новый пароль успешно установлен.');
        return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/login/')->withStatus(200);
      }else{
        unset($_SESSION['new_user_email']);
        $this->container->get('flash')->addMessage('error', 'Пройзошла ошибка.');
        return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/login/')->withStatus(200);
      };
      
    }
  }
}