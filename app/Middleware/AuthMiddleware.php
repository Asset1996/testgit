<?php
namespace MyApp\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;
use MyApp\Exception\UnauthorizedException;
use MyApp\Exception\ForbiddenException;
use Psr\Container\ContainerInterface;

class AuthMiddleware
{
	private $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

	/**
	 *
	 * @param  ServerRequest  $request PSR-7 request
	 * @param  RequestHandler $handler PSR-15 request handler
	 *
	 * @return Response
	 */
	public function super(Request $request, RequestHandler $handler): Response
	{
		if(!isset($_SESSION['user'])){
			$this->container->get('flash')->addMessage('error', 'Вы не авторизовались.');
			$response = new Response();
			return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/login/')->withStatus(200);
		}
		if((int)$_SESSION['user']['role'] !== 1){
			$this->container->get('flash')->addMessage('error', 'У вас нет прав к запрашиваемой странице.');
			$response = new Response();
			return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/servers/')->withStatus(200);
		}
		return $handler->handle($request);
	}

	/**
	 *
	 * @param  ServerRequest  $request PSR-7 request
	 * @param  RequestHandler $handler PSR-15 request handler
	 *
	 * @return Response
	 */
	public function employee(Request $request, RequestHandler $handler): Response
	{
		if(!isset($_SESSION['user'])){
			$this->container->get('flash')->addMessage('error', 'Вы не авторизовались.');
			$response = new Response();
			return $response->withHeader('Location', '/' . $_ENV['VERSION'] . '/login/')->withStatus(200);
		}
		return $handler->handle($request);
	}
}