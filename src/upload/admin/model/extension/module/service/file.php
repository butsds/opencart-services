<?php
class ModelExtensionModuleServiceFile extends Model
{
    public function getFileSize(string $file): array
    {
        $size = filesize($file);

        $suffix = array(
            'B',
            'KB',
            'MB',
            'GB',
            'TB',
            'PB',
            'EB',
            'ZB',
            'YB'
        );

        $i = 0;

        while (($size / 1024) > 1) {
            $size = $size / 1024;
            $i++;
        }

        return [
            'size' => $size,
            'formatted' => round(substr($size, 0, strpos($size, '.') + 4), 2) . ' ' . $suffix[$i]
        ];
    }

    public function getLines(string $file, array $pagination): array
    {
        $result = [];

        $start = $pagination['offset'];
        $limit = $pagination['limit'];

        $file = new SplFileObject($file, 'r');
        $file->seek($start);

        $counter = 0;

        while (!$file->eof() && $counter < $limit) {
            $line = $file->fgets();

            if ($line === false) {
                break;
            }

            $result[] = $line;
            $counter++;
        }

        return $result;
    }

    public function getTotalLines(string $file): int
    {
        $file = new SplFileObject($file, 'r');
        $file->seek(PHP_INT_MAX);

        return $file->key() + 1;
    }

    public function archive(string $file, string $archivePath)
    {
        $zip = new \ZipArchive();

        $zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFile($file, basename($file));

        $zip->close();
    }
}