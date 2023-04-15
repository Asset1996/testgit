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
use MyApp\Model\Logs;


class LogsController{

  private $pdo;
  private $container;
  private $logger;

  public function __construct(ContainerInterface $container){
      $this->container = $container;
      $this->logger = $this->container->get('logger');
      $this->pdo = $this->container->get("pdo");
  }

  public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{

    $limit = 50;
    $param = $request->getQueryParams();
    $page = isset($param['page']) && is_numeric($param['page']) ? $param['page'] : 1;

    $logs = new Logs($this->pdo);
    $logs->set_page((int) $page);
    $logs->set_limit((int) $limit);
    $offset = $limit * $page - $limit;
    $logs->set_offset((int) $offset);
    $logs_list = $logs->get_list_with_users();

    $total = $logs->get_total();
    $total_pages = ceil($total / $limit);

    // echo '<pre>' . print_r($total, true);
    // echo '<pre>' . print_r($logs_list, true);
    // exit();

    $view = Twig::fromRequest($request);
    return $view->render($response, '/logs/list.html.twig', [
      'logs_list' => $logs_list,
      'offset' => $offset,
      'total' => $total,
      'total_pages' => $total_pages
    ]);
  }
}