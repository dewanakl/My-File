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

    function __construct($dir)
    {
        $p = pathinfo($_SERVER['PATH_INFO']);
        $basename = $p['basename'];
        $extension = $p['extension'];
        $file = $dir . $basename;

        if (empty($extension) || empty($basename) || (!is_file($file))) {
            header("HTTP/1.1 400 Invalid Request");
            exit('file not found');
        }

        $this->file = fopen($file, "r");
        $this->name = basename($file);
        $this->boundary = md5($file);
        $this->size = filesize($file);
        $this->type = $extension;
    }

    public function process()
    {
        $ranges = NULL;
        $t = 0;

        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_SERVER['HTTP_RANGE']) && $range = stristr(trim($_SERVER['HTTP_RANGE']), 'bytes=')) {
            $range = substr($range, 6);
            $ranges = explode(',', $range);
            $t = count($ranges);
        }

        if (isset($_GET['download'])) {
            $this->download();
        } else {
            $this->view();
        }

        if ($t > 0) {
            header("HTTP/1.1 206 Partial content");
            $t === 1 ? $this->pushSingle($range) : $this->pushMulti($ranges);
        } else {
            header("Content-Length: " . $this->size);
            $this->readFile();
        }

        fclose($this->file);
        flush();
    }

    private function view()
    {
        if ($this->type() != "false") {
            header("Connection: Keep-Alive");
            header("Content-type: " . $this->type());
        } else {
            $this->download();
        }
    }

    private function download()
    {
        header("Accept-Ranges: bytes");
        header("Content-Type: application/octet-stream");
        header("Content-Transfer-Encoding: binary");
        header(sprintf('Content-Disposition: attachment; filename="%s"', $this->name));
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
        $tl = "Content-type: application/octet-stream\r\n";
        $formatRange = "Content-range: bytes %d-%d/%d\r\n\r\n";

        foreach ($ranges as $range) {
            $this->getRange($range, $start, $end);
            $length += strlen("\r\n--$this->boundary\r\n");
            $length += strlen($tl);
            $length += strlen(sprintf($formatRange, $start, $end, $this->size));
            $length += $end - $start + 1;
        }

        $length += strlen("\r\n--$this->boundary--\r\n");
        header("Content-Length: $length");
        header("Content-Type: multipart/x-byteranges; boundary=$this->boundary");

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
            if ($start < 0)
                $start = 0;
        } else {
            if ($end == '' || $end > $fileSize - 1)
                $end = $fileSize - 1;
        }

        if ($start > $end) {
            header("Status: 416 Requested range not satisfiable");
            header("Content-Range: */" . $fileSize);
            exit();
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
            $bytesLeft > $size ? $bytesRead = $size : $bytesRead = $bytesLeft;
            $bytesLeft -= $bytesRead;
            echo fread($this->file, $bytesRead);
            flush();
        }
    }

    private function type()
    {
        switch ($this->type) {
            case "gif":
                return "image/gif";
                break;
            case "png":
                return "image/png";
                break;
            case "jpeg":
                return "image/jpeg";
                break;
            case "jpg":
                return "image/jpeg";
                break;
            case "svg":
                return "image/svg+xml";
                break;
            case "bmp":
                return "image/bmp";
                break;
            case "ico":
                return "image/ico";
                break;
            case "js":
                return "application/javascript";
                break;
            case "css":
                return "text/css";
                break;
            case "pdf":
                return "application/pdf";
                break;
            case "mp3":
                return "audio/mpeg";
                break;
            case "mp4":
                return "video/mp4";
                break;
            case "mkv":
                return "video/mp4";
                break;
            case "woff":
                return "font/woff";
                break;
            case "woff2":
                return "font/woff2";
                break;
            case "ttf":
                return "font/ttf";
                break;
            case "txt":
                return "text/plain";
                break;
            case "csv":
                return "text/csv";
                break;
            default:
                return "false";
                break;
        }
    }
}


$viewDownload = new ViewDownload(dirSave);
$viewDownload->process();
