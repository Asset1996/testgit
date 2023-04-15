<?php
namespace MyApp\Exception;
use Slim\Exception\HttpUnauthorizedException;
use Psr\Http\Message\ServerRequestInterface;

class UnauthorizedException extends HttpUnauthorizedException
{
    public $custom_code;
    public $request;
    /**
     * @param ServerRequestInterface $request
     * @param string                 $message
     * @param int                    $code
     * @param Throwable|null         $previous
     */
    public function __construct(ServerRequestInterface $request, string $message = '', int $code = 0, ?Throwable $previous = null) {
        parent::__construct($request, $message, $previous);
        $this->custom_code = $code;
        $this->request = $request;
    }
}