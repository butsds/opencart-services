<?php
namespace Service;

class RestPaginationController extends RestController
{
    protected $maxPerPage = 1000;

    public function index()
    {
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

            if (!isset($this->input['page'])) {
                throw new \Exception('Page is required');
            }

            $page = (int)$this->input['page'];

            if ($page < 1) {
                throw new \Exception('Page number cannot be less than 1');
            }

            $overridePerPage = false;

            if (isset($this->input['perPage'])) {
                $perPage = (int)$this->input['perPage'];

                if ($perPage > $this->maxPerPage) {
                    $perPage = $this->maxPerPage;
                    $overridePerPage = true;
                } elseif ($perPage < 1) {
                    throw new \Exception('Per page cannot be less than 1');
                }
            } else {
                $perPage = $this->maxPerPage;
                $overridePerPage = true;
            }

            $pagination = [
                'offset' => ($page - 1) * $perPage,
                'limit' => $perPage,
                'page' => $page,
                'perPage' => $perPage,
            ];

            $result = $this->$action($pagination);

            if (empty($result['items']) && $page > 1) {
                $numPages = ceil($result['total'] / $perPage);

                if ($page > $numPages) {
                    $page = $numPages;

                    $pagination['offset'] = ($page - 1) * $perPage;

                    $result = $this->$action($pagination);
                    $result['page'] = $page;
                }
            }

            if ($overridePerPage) {
                $result['perPage'] = $perPage;
            }

            $this->data['data'] = $result;
        } catch (\Exception $e) {
            $this->data['error'] = true;
            $this->data['message'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(Utils::unclean($this->data)));
    }
}