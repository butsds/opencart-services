<?php
namespace Service;
use Closure;

class Request
{
    private $request = null;
    private $post = array();

    public function __construct($registry)
    {
        $this->request = $registry->get('request');

        if (
            $this->request->server['CONTENT_TYPE'] === 'application/json'
            && $this->isPost()
        ) {
            $rawData = file_get_contents('php://input');

            if ($rawData) {
                $data = json_decode($rawData, true);

                if ($data === false || !is_array($data)) {
                    throw new \Exception('Invalid JSON');
                }

                $this->post = Utils::clean($data);
            }
        } else {
            $this->post = $this->request->post;
        }
    }

    public function isPost()
    {
        return $this->request->server['REQUEST_METHOD'] === 'POST';
    }

    public function post($key = null, $default = null)
    {
        if ($key === null) {
            return $this->post;
        }

        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }

        if ($default instanceof \Exception) {
            throw $default;
        }

        return $default;
    }

    public function get($key = null, $default = null)
    {
        if ($key === null) {
            return $this->request->get;
        }

        if (array_key_exists($key, $this->request->get)) {
            return $this->request->get[$key];
        }

        if ($default instanceof \Exception) {
            throw $default;
        }

        return $default;
    }

    public function input(string $key, $default = null)
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }

        if (array_key_exists($key, $this->request->get)) {
            return $this->request->get[$key];
        }

        if ($default instanceof \Exception) {
            throw $default;
        }

        return $default;
    }
}