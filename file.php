<?php
// require config
require_once 'config.php';

class ViewDownload
{
    private $file;
    private $name;
    private $boundary;
    private $size = 0;
    private $type;
    private $fdownload = false;

    function __construct($dir)
    {
        $p = pathinfo($_SERVER['PATH_INFO']);
        $d = isset($_GET['download']);

        $file = $dir . $p['basename'];

        if (!is_file($file)) {
            header("HTTP/1.1 400 Invalid Request");
            exit('Invalid Request');
        }

        header("Last-Modified: " . gmdate("D, d M Y H:i:s", filemtime($file)) . " GMT");
        header("Etag: " . md5_file($file));

        if (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == filemtime($file) || trim($_SERVER['HTTP_IF_NONE_MATCH']) == md5_file($file)) {
            Header("HTTP/1.1 304 Not Modified");
            exit;
        }

        $this->file = fopen($file, "r");
        $this->name = basename($file);
        $this->boundary = md5($file);
        $this->size = filesize($file);
        $this->type = $p['extension'];
        $this->fdownload = $d;
    }

    public function process()
    {
        $ranges = NULL;
        $t = 0;
        $typefile = $this->ftype();

        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_SERVER['HTTP_RANGE']) && $range = stristr(trim($_SERVER['HTTP_RANGE']), 'bytes=')) {
            $range = substr($range, 6);
            $ranges = explode(',', $range);
            $t = count($ranges);
        }

        header("Accept-Ranges: bytes");
        header("Content-Type: " . $typefile);

        if ($typefile == "application/octet-stream") {
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

        flush();
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
        $tl = "Content-Type: " . $this->ftype() . "\r\n";
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

    private function ftype()
    {
        if ($this->fdownload) {
            return "application/octet-stream";
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
            if (empty($mime_types[$this->type])) {
                return "application/octet-stream";
            } else {
                return $mime_types[$this->type];
            }
        }
    }
}


$viewDownload = new ViewDownload(dirSave);
$viewDownload->process();
