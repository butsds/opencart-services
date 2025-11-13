<?php

use Service\RestController;

class ControllerExtensionModuleServiceLog extends RestController
{
    protected function getLogPath()
    {
        $name = $this->serviceRequest->get('name', new \Exception('Log name is required'));

        if (!is_string($name)) {
            throw new \Exception('Log name must be a string');
        }

        $matches = [];

        preg_match('/^[a-z_]+$/', $name, $matches);

        if (empty($matches[0])) {
            throw new \Exception('Log name must [a-z_]+');
        }

        return DIR_LOGS . $name . '.log';
    }

    protected function getAction()
    {
        $result = [
            'total' => 0,
            'items' => [],
            'filesize' => '0 B'
        ];

        $path = $this->getLogPath();

        $page = (int) $this->serviceRequest->post('page', new \Exception('Page is required'));

        if ($page < 1) {
            throw new \Exception('Page number cannot be less than 1');
        }

        if (is_file($path)) {
            $result['path'] = $path;

            $maxPerPage = 1000;

            $perPage = $this->serviceRequest->post('perPage', $maxPerPage);

            if ($perPage > $maxPerPage) {
                $perPage = $result['perPage'] = $maxPerPage;
            }

            $this->load->model('extension/module/service/file');

            $pagination = [
                'offset' => ($page - 1) * $perPage,
                'limit' => $perPage,
                'page' => $page,
                'perPage' => $perPage,
            ];

            $result['total'] = $this->model_extension_module_service_file->getTotalLines($path);
            $result['filesize'] = $this->model_extension_module_service_file->getFileSize($path)['formatted'];

            if ($result['total'] > 0) {
                $numPages = ceil($result['total'] / $perPage);

                if ($page > $numPages) {
                    $result['page'] = $page = $numPages;
                    $pagination['offset'] = ($page - 1) * $perPage;
                }

                $result['items'] = $this->model_extension_module_service_file->getLines($path, $pagination);
            }
        }

        return $result;
    }

    protected function clearAction()
    {
        if (!$this->user->hasPermission('modify', 'tool/log')) {
            throw new \Exception($this->language->get('error_permission'));
        }

        file_put_contents($this->getLogPath(), '');

        $this->data['message'] = $this->language->get('text_success');

        return true;
    }

    protected function archiveAction()
    {
        if (!$this->user->hasPermission('modify', 'tool/log')) {
            throw new \Exception($this->language->get('error_permission'));
        }

        $file = $this->getLogPath();

        $this->load->model('extension/module/service/file');

        if (!is_file($file)) {
            throw new \Exception('Log file does not exist');
        }

        $this->load->model('extension/module/service/file');

        $this->model_extension_module_service_file->archive($file, $file . '.' . date("Y-m-d_H_i_s", time()) . '.zip');

        file_put_contents($file, '');

        $this->data['message'] = $this->language->get('text_success');

        return true;
    }

    protected function downloadAction()
    {
        if (!$this->user->hasPermission('access', 'tool/log')) {
            throw new \Exception($this->language->get('error_permission'));
        }

        $file = $this->getLogPath();

        if (!is_file($file)) {
            throw new \Exception('Log file does not exist');
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));

        if (ob_get_level()) {
            ob_end_clean();
        }

        readfile($file, 'rb');

        exit();
    }
}
