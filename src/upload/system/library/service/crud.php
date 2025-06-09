<?php
namespace Service;

/**
 *
 * @property Response $response
 * @property Request $request
 * @property DataTable $model
 */
class Crud
{
    protected $model;
    protected $request;
    protected $response;
    protected $limit = 10000;


    public function __construct($model)
    {
        $registry = $model->getRegistry();

        $this->model = $model;
        $this->request = new Request($registry);
        $this->response = new Response($registry);
    }

    public function setLimt(int $limit)
    {
        $this->limit = $limit;

        return $this;
    }

    public function process()
    {
        try {
            if (!$this->request->isPost()) {
                throw new \Exception('Method can be post only!');
            }

            $result = $this->request->post();

            switch ($this->request->post('action')) {
                case 'read':
                    $params = $this->request->post('params', []);

                    $total = $this->model->getTotal($params);

                    $pagination = Pagination::fromArray($params)
                        ->pluckLimit($this->limit)
                        ->setTotal($total)
                    ;

                    $result = $pagination->toArray();

                    $result['items'] = $this->model->get(
                        array_merge($params, $pagination->toOffsetLimit())
                    );

                    break;
                case 'delete':
                    $this->model->delete($this->request->post('data', []));
                    break;
                case 'clone':
                    $ids = $this->model->clone($this->request->post('data', []));

                    $result = $this->model->getByIds($ids);

                    break;
                case 'create':
                    $id = $this->model->create($this->request->post('data'));

                    $result = $this->model->getById($id);

                    break;
                case 'update':
                    $data = $this->request->post('data', []);

                    if (empty($data['ids'])) {
                        throw new \Exception('Ids empty!');
                    }

                    if (empty($data['data'])) {
                        throw new \Exception('Data empty!');
                    }

                    $this->model->update($data['ids'], $data['data']);

                    break;
                default:
                    throw new \Exception('Invalid action!');
            }

            $this->response->json($result);
        } catch (\Exception $e) {
            $this->response->json($e);
        }
    }
}