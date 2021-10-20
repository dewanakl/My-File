<?php
// require config
require_once 'config.php';

class File
{
    private $file;
    private $name;
    private $boundary;
    private $size = 0;
    private $type;

    function __construct($dir)
    {
        $p = pathinfo($_SERVER['PATH_INFO']);
        if (isset($_GET['download'])) {
            $d = true;
        } else {
            $d = false;
        }

        $file = $dir . $p['basename'];

        if (!is_file($file)) {
            header("HTTP/1.1 404 Not Found");
            echo 'Not Found';
            exit;
        }

        $timeFile = filemtime($file);
        $hashFile = md5($file);

        header("Cache-Control: public");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s", $timeFile) . " GMT");
        header("Etag: " . $hashFile);

        if (@strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $timeFile || @trim($_SERVER['HTTP_IF_NONE_MATCH']) == $hashFile) {
            header("HTTP/1.1 304 Not Modified");
            exit;
        }

        $this->file = fopen($file, "r");
        $this->name = basename($file);
        $this->boundary = $hashFile;
        $this->size = filesize($file);
        $this->ftype($p['extension'], $d);
    }

    public function process()
    {
        $ranges = null;
        $t = 0;

        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_SERVER['HTTP_RANGE']) && $range = stristr(trim($_SERVER['HTTP_RANGE']), 'bytes=')) {
            $range = substr($range, 6);
            $ranges = explode(',', $range);
            $t = count($ranges);
        }

        header("Accept-Ranges: bytes");
        header("Content-Type: " . $this->type);

        if ($this->type == "application/octet-stream") {
            header(sprintf('Content-Disposition: attachment; filename="%s"', $this->name));
            header("Content-Transfer-Encoding: binary");
        }

        if ($t > 0) {
            header("HTTP/1.1 206 Partial Content");
            if ($t === 1) {
                $this->pushSingle($range);
            } else {
                $this->pushMulti($ranges);
            }
        } else {
            header("Content-Length: " . $this->size);
            $this->readFile();
        }

        fclose($this->file);
    }

    private function pushSingle($range)
    {
        $start = $end = 0;
        $this->getRange($range, $start, $end);
        header("Content-Length: " . ($end - $start + 1));
        header(sprintf("Content-Range: bytes %d-%d/%d", $start, $end, $this->size));
        fseek($this->file, $start);
        $this->readBuffer($end - $start + 1);
        $this->readFile();
    }

    private function pushMulti($ranges)
    {
        $length = $start = $end = 0;
        $tl = "Content-Type: " . $this->type . "\r\n";
        $formatRange = "Content-Range: bytes %d-%d/%d\r\n\r\n";

        foreach ($ranges as $range) {
            $this->getRange($range, $start, $end);
            $length += strlen("\r\n--$this->boundary\r\n");
            $length += strlen($tl);
            $length += strlen(sprintf($formatRange, $start, $end, $this->size));
            $length += $end - $start + 1;
        }

        $length += strlen("\r\n--$this->boundary--\r\n");
        header("Content-Type: multipart/byteranges; boundary=$this->boundary");
        header("Content-Length: $length");

        foreach ($ranges as $range) {
            $this->getRange($range, $start, $end);
            echo "\r\n--$this->boundary\r\n";
            echo $tl;
            echo sprintf($formatRange, $start, $end, $this->size);
            fseek($this->file, $start);
            $this->readBuffer($end - $start + 1);
        }

        echo "\r\n--$this->boundary--\r\n";
    }

    private function getRange($range, &$start, &$end)
    {
        list($start, $end) = explode('-', $range);
        $fileSize = $this->size;

        if ($start == '') {
            $tmp = $end;
            $end = $fileSize - 1;
            $start = $fileSize - $tmp;
            if ($start < 0) {
                $start = 0;
            }
        } else {
            if ($end == '' || $end > $fileSize - 1) {
                $end = $fileSize - 1;
            }
        }

        if ($start > $end) {
            header("Status: 416 Requested Range Not Satisfiable");
            header("Content-Range: */" . $fileSize);
            exit;
        }

        return array($start, $end);
    }

    private function readFile()
    {
        while (!feof($this->file)) {
            echo fgets($this->file);
            flush();
        }
    }

    private function readBuffer($bytes, $size = 1024)
    {
        $bytesLeft = $bytes;
        while ($bytesLeft > 0 && !feof($this->file)) {
            if ($bytesLeft > $size) {
                $bytesRead = $size;
            } else {
                $bytesRead = $bytesLeft;
            }
            $bytesLeft -= $bytesRead;
            echo fread($this->file, $bytesRead);
            flush();
        }
    }

    private function ftype($typeFile, $download)
    {
        if ($download) {
            $this->type = "application/octet-stream";
        } else {
            $mime_types = array(
                'txt' => 'text/plain',
                'htm' => 'text/plain',
                'html' => 'text/plain',
                'php' => 'text/plain',
                'css' => 'text/css',
                'png' => 'image/png',
                'jpe' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'jpg' => 'image/jpeg',
                'gif' => 'image/gif',
                'bmp' => 'image/bmp',
                'ico' => 'image/ico',
                'tiff' => 'image/tiff',
                'tif' => 'image/tiff',
                'svg' => 'image/svg+xml',
                'svgz' => 'image/svg+xml',
                'mp4' => 'video/mp4',
                'mkv' => 'video/mp4',
                'mp3' => 'audio/mpeg',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'pdf' => 'application/pdf'
            );
            if (empty($mime_types[$typeFile])) {
                $this->type = "application/octet-stream";
            } else {
                $this->type = $mime_types[$typeFile];
            }
        }
    }
}


$myFile = new File(dirSave);
$myFile->process();
