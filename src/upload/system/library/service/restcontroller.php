<?php
namespace Service;

class RestController extends \Controller
{
    protected $data = [];
    protected $input = [];

    public static function getRequestInput(bool $clean = true)
    {
        $rawData = file_get_contents('php://input');

        if (!$rawData) {
            return [];
        }

        $data = json_decode($rawData, true);

        if ($data === false || !is_array($data)) {
            throw new \Exception('Invalid JSON');
        }

        if ($clean) {
            return Utils::clean($data);
        }

        return $data;
    }

    public function index()
    {
        if ($this->request->server['CONTENT_TYPE'] === 'application/json') {
            $this->data['error'] = false;

            $this->input = self::getRequestInput();

            $action = ($this->input['action'] ?? 'index') . 'Action';

            try {
                if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
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
        } else {
            $action = ($this->request->get['action'] ?? 'index') . 'Action';

            if (method_exists($this, $action)) {
                $this->$action();
            }
        }
    }
}