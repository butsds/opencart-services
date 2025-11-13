<?php

namespace Service;

class Pagination
{
    protected $page;
    protected $perPage;
    protected $total = null;

    public function __construct(int $page, int $perPage)
    {
        $this->page = $page < 1 ? 1 : $page;
        $this->perPage = $perPage;
    }

    public static function fromRequest(Request $request)
    {
        $perPage = $request->post('perPage', 100);
        $page = $request->post('page', 1);

        return new Pagination($page, $perPage);
    }

    public static function fromArray(array $data)
    {
        return new Pagination($data['page'] ?? 1, $data['perPage'] ?? 100);
    }

    /**
     * @param int $limit
     * @return $this
     */
    public function pluckLimit(int $limit)
    {
        if ($this->perPage > $limit) {
            $this->perPage = $limit;
        }

        return $this;
    }

    /**
     * @param int $total
     * @return $this
     */
    public function setTotal(int $total)
    {
        $maxPage = ceil($total / $this->perPage);

        if (!$maxPage) {
            $maxPage = 1;
        }

        if ($this->page > $maxPage) {
            $this->page = $maxPage;
        }

        $this->total = $total;

        return $this;
    }

    public function toOffsetLimit()
    {
        return [
            'limit' => $this->perPage,
            'offset' => ($this->page - 1) * $this->perPage
        ];
    }

    public function toArray()
    {
        return [
            'perPage' => $this->perPage,
            'page' => $this->page,
            'total' => $this->total
        ];
    }
}
