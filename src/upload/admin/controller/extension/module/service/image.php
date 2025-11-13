<?php

use Service\RestController;
use Service\Filemanager;
use Service\Pagination;

/**
 * 
 * @property \Loader $load
 * @property \Image $model_tool_image
 * @property \Request $request
 * @property \Language $language
 */
class ControllerExtensionModuleServiceImage extends RestController
{
    protected $limit = 1000;

    protected $allowedExtensions = [
        'jpg',
        'jpeg',
        'gif',
        'png'
    ];

    protected $allowedMime = [
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'image/x-png',
        'image/gif'
    ];

    public function thumbsAction()
    {
        $this->load->model('tool/image');

        $images = $this->serviceRequest->post('images', new \Exception('Images are required'));

        if (!is_array($images)) {
            throw new \Exception('Images must be an array');
        }

        $result = [];

        foreach ($images as $image) {
            $result[$image['key']] = $this->model_tool_image->resize($image['url'], $image['width'], $image['height']);
        }

        return $result;
    }

    public function imagesAction()
    {
        $params = $this->serviceRequest->post();

        $prefix = 'catalog/';

        $directory = $prefix . ($params['directory'] ?? '');

        $filemanager = (new Filemanager(DIR_IMAGE, $this->allowedExtensions))->setDirectory($directory);

        $total = $filemanager->getTotal($params);

        $pagination = Pagination::fromArray($params)
            ->pluckLimit($this->limit)
            ->setTotal($total);

        $result = $pagination->toArray();

        $result['items'] = $filemanager->getItems(
            array_merge($params, $pagination->toOffsetLimit())
        );

        $lenPrefix = utf8_strlen($prefix);

        foreach ($result['items'] as $key => $file) {
            if ($file['type'] === 'directory') {
                $result['items'][$key]['path'] = utf8_substr($file['path'], $lenPrefix);
            }
        }

        return $result;
    }

    public function uploadAction()
    {
        $this->load->language('common/filemanager');

        if (!$this->user->hasPermission('modify', 'common/filemanager')) {
            throw new \Exception($this->language->get('error_permission'));
        }

        $directory = $this->serviceRequest->post('directory', '');

        if ($directory) {
            $directory = DIR_IMAGE . 'catalog/' . $directory;
        } else {
            $directory = DIR_IMAGE . 'catalog';
        }

        if (!is_dir($directory) || substr(str_replace('\\', '/', realpath($directory)), 0, strlen(DIR_IMAGE . 'catalog')) != str_replace('\\', '/', DIR_IMAGE . 'catalog')) {
            throw new \Exception($this->language->get('error_directory'));
        }


        // Check if multiple files are uploaded or just one
        $files = array();

        if (!empty($this->request->files['file']['name']) && is_array($this->request->files['file']['name'])) {
            foreach (array_keys($this->request->files['file']['name']) as $key) {
                $files[] = array(
                    'name'     => $this->request->files['file']['name'][$key],
                    'type'     => $this->request->files['file']['type'][$key],
                    'tmp_name' => $this->request->files['file']['tmp_name'][$key],
                    'error'    => $this->request->files['file']['error'][$key],
                    'size'     => $this->request->files['file']['size'][$key]
                );
            }
        }

        foreach ($files as $file) {
            if (is_file($file['tmp_name'])) {
                // Sanitize the filename

                $filename = basename(html_entity_decode(Service\Utils::ruTranslit($file['name']), ENT_QUOTES, 'UTF-8'));

                // Validate the filename length
                if ((utf8_strlen($filename) < 3) || (utf8_strlen($filename) > 255)) {
                    throw new \Exception($this->language->get('error_filename'));
                }

                if (!in_array(utf8_strtolower(utf8_substr(strrchr($filename, '.'), 1)), $this->allowedExtensions)) {
                    throw new \Exception($this->language->get('error_filetype'));
                }

                if (!in_array($file['type'], $this->allowedMime)) {
                    throw new \Exception($this->language->get('error_filetype'));
                }

                // Return any upload error
                if ($file['error'] != UPLOAD_ERR_OK) {
                    throw new \Exception($this->language->get('error_upload_' . $file['error']));
                }
            } else {
                throw new \Exception($this->language->get('error_upload'));
            }

            move_uploaded_file($file['tmp_name'], $directory . '/' . $filename);
        }

        $this->data['message'] = $this->language->get('text_uploaded');

        return true;
    }


    public function deleteAction()
    {
        $this->load->language('common/filemanager');

        // Check user has permission
        if (!$this->user->hasPermission('modify', 'common/filemanager')) {
            throw new \Exception($this->language->get('error_permission'));
        }

        $paths = $this->serviceRequest->post('path', []);

        // Loop through each path to run validations
        foreach ($paths as $key => $path) {
            if (utf8_substr($path, 0, 8) !== 'catalog/') {
                $paths[$key] = $path = 'catalog/' . $path;
            }

            // Check path exsists
            if ($path == DIR_IMAGE || substr(str_replace('\\', '/', realpath(DIR_IMAGE . $path)), 0, strlen(DIR_IMAGE)) != str_replace('\\', '/', DIR_IMAGE)) {
                throw new \Exception($this->language->get('error_delete'));
            }
        }

        foreach ($paths as $path) {
            $path = rtrim(DIR_IMAGE . $path, '/');

            // If path is just a file delete it
            if (is_file($path)) {
                unlink($path);

                // If path is a directory beging deleting each file and sub folder
            } elseif (is_dir($path)) {
                $files = array();

                // Make path into an array
                $path = array($path);

                // While the path array is still populated keep looping through
                while (count($path) != 0) {
                    $next = array_shift($path);

                    foreach (glob($next) as $file) {
                        // If directory add to path array
                        if (is_dir($file)) {
                            $path[] = $file . '/*';
                        }

                        // Add the file to the files to be deleted array
                        $files[] = $file;
                    }
                }

                // Reverse sort the file array
                rsort($files);

                foreach ($files as $file) {
                    // If file just delete
                    if (is_file($file)) {
                        unlink($file);

                        // If directory use the remove directory function
                    } elseif (is_dir($file)) {
                        rmdir($file);
                    }
                }
            }
        }

        $this->data['message'] = $this->language->get('text_delete');

        return true;
    }

    public function downloadAction()
    {
        $this->load->language('common/filemanager');

        // Check user has permission
        if (!$this->user->hasPermission('modify', 'common/filemanager')) {
            throw new \Exception($this->language->get('error_permission'));
        }

        $directory = $this->serviceRequest->post('directory', new \Exception('Empty Directory!'));

        $link = $this->serviceRequest->post('link', new \Exception('Empty Link!'));

        (new Filemanager(DIR_IMAGE . 'catalog/'))->downloadImage($directory, $link);

        $this->data['message'] = $this->language->get('text_upload');

        return true;
    }

    public function messagesAction()
    {
        $this->load->language('common/filemanager');

        $messages = $this->language->all();

        unset($messages['backup']);

        return $messages;
    }

    public function folderAction()
    {
        $this->load->language('common/filemanager');

        // Check user has permission
        if (!$this->user->hasPermission('modify', 'common/filemanager')) {
            throw new \Exception($this->language->get('error_permission'));
        }

        $directory = $this->serviceRequest->post('directory', '');

        if ($directory) {
            $directory = DIR_IMAGE . 'catalog/' . $directory;
        } else {
            $directory = DIR_IMAGE . 'catalog';
        }

        $directory = rtrim($directory, '/');

        if (!is_dir($directory) || substr(str_replace('\\', '/', realpath($directory) . '/'), 0, strlen(DIR_IMAGE)) != str_replace('\\', '/', DIR_IMAGE)) {
            throw new \Exception($this->language->get('error_directory'));
        }

        $folder = $this->serviceRequest->post('folder', '');

        $folder = basename(html_entity_decode(Service\Utils::ruTranslit($folder), ENT_QUOTES, 'UTF-8'));

        // Validate the filename length
        if ((utf8_strlen($folder) < 3) || (utf8_strlen($folder) > 128)) {
            throw new \Exception($this->language->get('error_folder'));
        }

        // Check if directory already exists or not
        if (is_dir($directory . '/' . $folder)) {
            throw new \Exception($this->language->get('error_exists'));
        }

        mkdir($directory . '/' . $folder, 0777);
        chmod($directory . '/' . $folder, 0777);

        @touch($directory . '/' . $folder . '/' . 'index.html');

        $this->data['message'] = $this->language->get('text_directory');

        return true;
    }
}
