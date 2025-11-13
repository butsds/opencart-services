<?php

namespace Service;

use Exception;

class Filemanager
{
    protected $root = '';
    protected $currentDirectory = '';
    protected $extensions = [];

    public function __construct($root, $extensions = [])
    {
        $this->root = $root;
        $this->extensions = $extensions;
    }

    public function setDirectory($directory)
    {
        $this->currentDirectory = trim($directory, '/') . '/';

        return $this;
    }

    public function getFullPath($path = '')
    {
        return $this->root . $this->currentDirectory . $path;
    }

    public function getItems($filterData = [])
    {
        $result = [];

        $filename = $filterData['filename'] ?? '';

        $files = $this->getFiles($filename);

        if (empty($files)) {
            return $result;
        }

        $offset = $filterData['offset'] ?? 0;

        if (isset($filterData['limit'])) {
            $files = array_splice($files, $offset, $filterData['limit']);
        }

        foreach ($files as $file) {
            $item = [
                'name' => basename($file),
                'path' => utf8_substr($file, utf8_strlen($this->root)),
                'size' => filesize($file)
            ];

            if (is_dir($file)) {
                $item['type'] = 'directory';
            } else {
                $item['type'] = 'file';
            }

            $result[] = $item;
        }

        return $result;
    }

    public function getTotal($filterData = [])
    {
        return count($this->getFiles($filterData['filename'] ?? ''));
    }

    protected function getFiles($filter_name)
    {
        $directory = $this->getFullPath();

        if (substr(str_replace('\\', '/', realpath($directory) . '/' . $filter_name), 0, strlen($directory)) == str_replace('\\', '/', $directory)) {
            $directories = glob($directory . $filter_name . '*', GLOB_ONLYDIR);

            if (!$directories) {
                $directories = array();
            }

            if (!empty($this->extensions)) {
                $files = glob($directory . $filter_name . '*.{' . implode(',', $this->extensions) . '}', GLOB_BRACE);
            } else {
                $files = glob($directory . $filter_name . '*');
            }

            if (!$files) {
                $files = array();
            }

            return array_merge($directories, $files);
        }

        return [];
    }

    public function downloadImage($targetDir, $url, $filename = '')
    {
        $targetDir = trim($targetDir, '/');

        $fullTarget = $this->getFullPath($targetDir);

        if (!is_dir($fullTarget)) {
            throw new \Exception('Target directory is not exists!');
        }

        // Accept common image content types
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
        ];

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception('Invalid image url!');
        }

        $headers = @get_headers($url, 1);

        $contentType = null;

        if ($headers && isset($headers['Content-Type'])) {
            $contentType = is_array($headers['Content-Type']) ? end($headers['Content-Type']) : $headers['Content-Type'];
        }

        $extension = null;

        if ($contentType && isset($allowedTypes[strtolower($contentType)])) {
            $extension = $allowedTypes[strtolower($contentType)];
        }

        if ($extension === null) {
            $path = parse_url($url, PHP_URL_PATH);

            if ($path) {
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                if ($ext) {
                    $ext = strtolower($ext);
                    // Basic normalization
                    if (in_array($ext, ['jpeg', 'jpg', 'png', 'gif', 'webp', 'bmp'])) {
                        $extension = $ext === 'jpeg' ? 'jpg' : $ext;
                    }
                }
            }
        }

        if ($extension === null) {
            throw new \Exception('Invalid Extension!');
        }

        if (!$filename) {
            // Generate unique filename
            $basename = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_FILENAME) ?: 'image';
            $safeBase = preg_replace('/[^A-Za-z0-9-_]/', '_', $basename);
            $filename = $safeBase . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        }

        $savePath = $fullTarget . '/' . $filename;

        // Try downloading the file
        $contents = @file_get_contents($url);

        if ($contents === false) {
            // Try curl if available
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_FAILONERROR, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                $contents = curl_exec($ch);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($contents === false || $contents === null) {
                    throw new \Exception('Failed to download: ' . ($curlErr ?: 'unknown'));
                }
            } else {
                throw new \Exception('Failed to download and cURL not available');
            }
        }

        $written = @file_put_contents($savePath, $contents);

        if ($written === false) {
            throw new \Exception('Failed to write file');
        }

        // Optionally verify mime type of saved file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected = $finfo ? finfo_file($finfo, $savePath) : null;

        if ($finfo) {
            finfo_close($finfo);
        }

        if ($detected && !in_array($detected, array_keys($allowedTypes))) {
            // Not an allowed image type; remove file
            @unlink($savePath);

            throw new \Exception('Downloaded file is not an allowed image type (' . ($detected ?: 'unknown') . ')');
        }
    }
}
