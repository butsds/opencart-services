<?php

namespace Service;

/**
 *
 * @property Request $serviceRequest
 * @property Response $serviceResponse
 */
class Controller extends \Controller
{
    protected $serviceRequest = null;
    protected $serviceResponse = null;

    public function __construct($registry)
    {
        parent::__construct($registry);

        $this->serviceRequest = new Request($registry);
        $this->serviceResponse = new Response($registry);
    }
}
