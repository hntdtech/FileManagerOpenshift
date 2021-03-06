<?php

    namespace Librarys\App;

    if (defined('LOADED') == false)
        exit;

    use Librarys\App\AppDirectory;
    use Librarys\File\FileInfo;

    final class AppFileDownload
    {

        private $fileNameAlias;
        private $fileInfo;
        private $fileSize;
        private $isDirectory;

        public function __construct()
        {
            if (function_exists('apache_setenv'))
                @apache_setenv('no-gzip', 1);

            @ini_set('zlib.output_compression', 'Off');
        }

        public function setFileOnAppDirectory()
        {
            $this->fileNameAlias = AppDirectory::getInstance()->getAliasName();
            $this->fileInfo      = new FileInfo(AppDirectory::getInstance()->getDirectory() . SP . AppDirectory::getInstance()->getName());
            $this->fileSize      = $this->fileInfo->getFileSize();
            $this->isDirectory   = $this->fileInfo->isDirectory();

            if ($this->fileNameAlias == null || empty($this->fileNameAlias))
                $this->fileNameAlias = AppDirectory::getInstance()->getName();

            return true;
        }

        public function setFileOnPath($pathFile, $aliasName = null)
        {
            $this->fileInfo = new FileInfo($pathFile);

            if ($this->fileInfo->isFile() == false)
                return false;

            $this->fileSize      = $this->fileInfo->getFileSize();
            $this->isDirectory   = $this->fileInfo->isDirectory();

            if ($aliasName != null)
                $this->fileNameAlias = $aliasName;
            else
                $this->fileNameAlias = $this->fileInfo->getFileName();

            return true;
        }

        public function reponseHeader()
        {
            if ($this->fileInfo->isFile() == false)
                return false;

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $this->fileNameAlias);
            header('Content-Length: ' . $this->fileSize);
            header('Pragma: public');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

            return true;
        }

        public function download()
        {
            if ($this->fileInfo->isFile() == false)
                return false;

            $range = '';

            if(isset($_SERVER['HTTP_RANGE'])) {
                list($size_unit, $rangeOrig) = explode('=', $_SERVER['HTTP_RANGE'], 2);

                if ($size_unit == 'bytes') {
                    // Multiple ranges could be specified at the same time, but for simplicity only serve the first range
                    // http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt

                    $range = explode(',', $rangeOrig);
                    $range = explode('-', $range[0]);

                    @list($range, $extraRanges) = $range;

                   if (is_numeric($range) == false)
                       $range = '0';
                } else {
                    header('HTTP/1.1 416 Requested Range Not Satisfiable');
                    exit;
                }
            } else {
                $range = '-';
            }

            $ranges    = explode('-', $range, 2);
            $seekStart = null;
            $seekEnd   = null;

            if (count($ranges) >= 2)
                list($seekStart, $seekEnd) = $ranges;

            // Set start and end based on range (if set), else set defaults
            // Also check for invalid ranges.

            if (empty($seekEnd) || is_null($seekEnd))
                $seekEnd = $this->fileSize - 1;
            else
                $seekEnd = min(abs(intval($seekEnd)), $this->fileSize - 1);

            if (empty($seekStart) || is_null($seekStart) || $seekEnd < abs(intval($seekStart)))
                $seekStart = 0;
            else
                $seekStart = max(abs(intval($seekStart)), 0);

            // Only send partial content header if downloading a piece of the file (IE workaround)
            if ($seekStart > 0 || $seekEnd < ($this->fileSize - 1)) {
                header('HTTP/1.1 206 Partial Content');
                header('Content-Range: bytes ' . $seekStart . '-' . $seekEnd . '/' . $this->fileSize);
                header('Content-Length: ' . ($seekEnd - $seekStart + 1));
            } else {
              header('Content-Length: ' . $this->fileSize);
            }

            header('Accept-Ranges: bytes');

            $file = FileInfo::fileOpen($this->fileInfo->getFilePath(), 'rb');

            set_time_limit(0);
            FileInfo::fileSeek($file, $seekStart);

            while(FileInfo::fileEndOfFile($file) == false) {
                echo(FileInfo::fileRead($file, 1024 * 8));
                @ob_flush();
                @flush();

                if (connection_status() != 0) {
                    FileInfo::fileClose($file);
                    exit;
                }
            }

            // File save was a success
            FileInfo::fileClose($file);
            exit;
        }

    }

?>