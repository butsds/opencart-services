<?php
namespace service;
class Response
{
    public $message = null;

    private $response = null;

    public function __construct($registry)
    {
        $this->response = $registry->get('response');
    }

    public function json($data)
    {
        if ($data instanceof \Exception) {
            $result = [
                'error' => true,
                'message' => $data->getMessage(),
            ];
        } elseif (is_array($data) || is_object($data)) {
            $result = [
                'error' => false,
                'data' => $data,
            ];

            if ($this->message) {
                $result['message'] = $this->message;
            }
        } else {
            $result = [
                'error' => true,
                'message' => 'Data is not valid',
            ];
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(Utils::unclean($result)));
    }
}