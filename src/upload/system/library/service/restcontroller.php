<?php

namespace Service;

/**
 * 
 * @property \Response $response
 */
class RestController extends Controller
{
    protected $data = [];

    public function index()
    {
        $this->data['error'] = false;

        $action = $this->serviceRequest->post('action', 'index') . 'Action';

        try {
            if (!$this->serviceRequest->isPost()) {
                throw new \Exception('Method can be post only!');
            }

            if (!method_exists($this, $action)) {
                throw new \Exception('Invalid action ' . $action);
            }

            $this->data['data'] = $this->$action();
        } catch (\Exception $e) {
            $this->data['error'] = true;
            $this->data['message'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(Utils::unclean($this->data)));
    }
}
