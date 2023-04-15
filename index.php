<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Almaty');

//DIRECTORIES
define('MAIN_DIR',__DIR__. DIRECTORY_SEPARATOR);
define('VENDOR_DIR',MAIN_DIR . 'vendor'.DIRECTORY_SEPARATOR);
define('TRANSLATE_DIR',MAIN_DIR . 'translate'.DIRECTORY_SEPARATOR);
defined('DS') ?: define('DS', DIRECTORY_SEPARATOR);
if($_SERVER['REMOTE_ADDR'] == "127.0.0.1" || $_SERVER['REMOTE_ADDR'] == "::1"){
    defined('ROOT') ?: define('ROOT', MAIN_DIR);
}else{
    defined('ROOT') ?: define('ROOT', dirname(__DIR__) . DS);
};

error_reporting(E_ALL);
(int)$MESSAGE_COUNTER;

require realpath( dirname(__FILE__) . '/publicapi-php/Cpanel/Util/Autoload.php');

//System
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Factory\AppFactory;
use Psr\Container\ContainerInterface;
use DI\Container;
use Semhoun\Mailer\Mailer;
//Apps
use MyApp\Controller\RegistrationController;
use MyApp\Controller\SecondClientController;
use MyApp\Controller\AuthController;
use MyApp\Controller\ServersController;
use MyApp\Controller\LoginController;
use MyApp\Controller\PermissionController;
use MyApp\Controller\LogsController;
use MyApp\Model\Logs;
use MyApp\Model\Permission;
//Middleware
use MyApp\Middleware\AuthMiddleware;
//Twig
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
//Database
use FaaPz\PDO\Database;
//Flash
use Slim\Flash\Messages;
use Slim\Routing\RouteContext;

require VENDOR_DIR.'autoload.php';

if (file_exists(ROOT . '.env'))
{
    $dotenv = Dotenv\Dotenv::createImmutable(ROOT);
    $dotenv->load(true);
}
else
{
    exit(ROOT.'.env not found');
}

//Container set
$container = new Container();
AppFactory::setContainer($container);
$container->set('pdo', function()
{
    return new Database($_ENV['DB_DSN'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);
});

$container->set('view', function() 
{
    $twig = Twig::create(__DIR__ . '/template',['cache' =>false]);
    return $twig;
});

$container->set('mailer', function() use($container)
{
    $view = $container->get('view');
    $mailer = new Mailer($view, [
        'host'      => $_ENV['SMTP_HOST'],  // SMTP Host
        'port'      => $_ENV['SMTP_PORT'],  // SMTP Port
        'username'  => $_ENV['SMTP_USERNAME'],  // SMTP Username
        'password'  => $_ENV['SMTP_PASSWORD'],  // SMTP Password
        'protocol'  => $_ENV['SMTP_PROTOCOL']   // SSL or TLS
    ]);

    // Set the details of the default sender
    $mailer->setDefaultFrom($_ENV['SMTP_USERNAME'], 'Электронная почта Mediana');
    return $mailer;
});

$container->set('flash', function () use($container){
    $storage = [];
    return new Messages($storage);
});

$container->set('logger', function () use($container){
    return new Logs($container->get('pdo'));
});

// $container->set('new_requests', function () use($container){

//     $permission = new Permission($container->get('pdo'));
//     $new_requests = $permission->get_all_new_requests();

//     return $new_requests;
// });

$app = AppFactory::create();

$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$twig = Twig::create($_ENV['TEMPLATE_DIR'], ['cache' => false]);
session_start();
$twig->getEnvironment()->addGlobal('session', $_SESSION);
$twig->getEnvironment()->addGlobal('version', $_ENV['VERSION']);
// $twig->getEnvironment()->addGlobal('new_requests', $container->get('new_requests'));
$app->add(TwigMiddleware::create($app, $twig));

$app->add(
    function ($request, $next) {
        // Change flash message storage
        $this->get('flash')->__construct($_SESSION);

        return $next->handle($request);
    }
);

$app->get('/secretmediana/', function (Request $request, Response $response, $args) {
    return $response->withBody('Hellloooo');
});

// $app->get('/', function (Request $request, Response $response, $args) {
//     return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/login/')->withStatus(302);
// });
// $app->get('/' . $_ENV['VERSION'], function (Request $request, Response $response, $args) {
//     return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/login/')->withStatus(302);
// });
// $app->get('/' . $_ENV['VERSION'] . '/', function (Request $request, Response $response, $args) {
//     return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/login/')->withStatus(302);
// });

 $app->group('/'.$_ENV['VERSION'], function (RouteCollectorProxy $group) use($container){
    
     $group->group('/register', function (RouteCollectorProxy $group) use($container){

         $group->get('/', RegistrationController::class . ':index')->setName('register_index');

         $group->post('/reg-user', RegistrationController::class . ':reg_user')->setName('register_reg_user');

         $group->get('/email-confirm/{token}', RegistrationController::class . ':email_confirm')->setName('email-confirm');

         //RESET PASSWWORD (Страница для восстановления пароля)
         $group->get('/reset-password-by-email', RegistrationController::class . ':reset_password_by_email')->setName('reset_password_by_email');
         //RESET PASSWWORD (post данные обрабатываются здесь)
         $group->post('/reset-password', RegistrationController::class . ':reset_password')->setName('reset_password');
         //NEW PASSWWORD (Страница для ввода нового пароля)
         $group->get('/new-password-by-email/{token}', RegistrationController::class . ':new_password_by_email')->setName('new_password_by_email');
         //NEW PASSWWORD (ost данные обрабатываются здесь)
         $group->post('/new-password', RegistrationController::class . ':new_password')->setName('new_password');

     });

//     $group->group('/login', function (RouteCollectorProxy $group) use($container){

//         $group->get('/', LoginController::class . ':index')->setName('login_index');

//         $group->post('/in', LoginController::class . ':log_in')->setName('log_in');

//         $group->get('/out', LoginController::class . ':log_out')->setName('log_out');

//     });

//     $group->group('/servers', function (RouteCollectorProxy $group) use($container){

//         $group->get('/', ServersController::class . ':index')->setName('servers_index')->add(AuthMiddleware::class . ':employee');

//         $group->post('/get-access', ServersController::class . ':get_access')->setName('get_access')->add(AuthMiddleware::class . ':employee');

//         $group->post('/get-accounts', ServersController::class . ':get_accounts')->setName('get_accounts')->add(AuthMiddleware::class . ':employee');

//         $group->post('/get-accounts-handbook', ServersController::class . ':get_accounts_handbook')->setName('get_accounts_handbook')->add(AuthMiddleware::class . ':employee');

//         $group->post('/search-account', ServersController::class . ':search_account')->setName('search_account')->add(AuthMiddleware::class . ':employee');

//         $group->get('/handbook', ServersController::class . ':handbook')->setName('handbook')->add(AuthMiddleware::class . ':employee');

//         $group->get('/add', ServersController::class . ':add_server')->setName('add_server')->add(AuthMiddleware::class . ':super');

//         $group->post('/add-server', ServersController::class . ':add_server_into_db')->setName('add_server_into_db')->add(AuthMiddleware::class . ':super');

//         $group->get('/delete/{id:[0-9]+}', ServersController::class . ':delete_server')->setName('delete_server')->add(AuthMiddleware::class . ':super');

//         $group->get('/list', ServersController::class . ':list')->setName('servers_list')->add(AuthMiddleware::class . ':super');

//     });

//     $group->group('/permission', function (RouteCollectorProxy $group) use($container){

//         $group->get('/users', PermissionController::class . ':users')->setName('users')->add(AuthMiddleware::class . ':super');

//         $group->get('/user-permission-info/{iin:[0-9]+}', PermissionController::class . ':user_permission_info')->setName('user_permission_info')->add(AuthMiddleware::class . ':super');

//         $group->get('/add-permission/{iin:[0-9]+}', PermissionController::class . ':add_permission')->setName('add_permission')->add(AuthMiddleware::class . ':super');

//         $group->post('/get-accounts', PermissionController::class . ':get_accounts')->setName('get_permission_accounts')->add(AuthMiddleware::class . ':super');

//         $group->post('/get-access/{iin:[0-9]+}', PermissionController::class . ':get_access')->setName('get_permission_access')->add(AuthMiddleware::class . ':super');

//         $group->get('/dismiss-user/{iin:[0-9]+}', PermissionController::class . ':dismiss_user')->setName('dismiss_user')->add(AuthMiddleware::class . ':super');

//         $group->get('/request-access/{server_id:[0-9]+}/{account_name}/{account_username}', PermissionController::class . ':request_access')->setName('request_access')->add(AuthMiddleware::class . ':employee');
        
//         //1-Одобрен, 2-Отклонен, 3-доступ снят
//         $group->get('/decision-access/{id:[0-9]+}/{decision:(?:1|2|3)}', PermissionController::class . ':decision_access')->setName('decision_permission_access')->add(AuthMiddleware::class . ':super');

//     });

//     $group->group('/logs', function (RouteCollectorProxy $group) use($container){

//         $group->get('/list', LogsController::class . ':list')->setName('logs_list')->add(AuthMiddleware::class . ':super');

//     });

//     $group->get('/test', ServersController::class . ':test');
 });

// Run app
$app->run();
