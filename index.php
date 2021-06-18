<?php
// folder to save files
$dirSave = "file/";
// function to convert size
function formatBytes($size, $precision = 1)
{
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}
// function to calculate folder size
function dirSize($directory)
{
    $size = 0;
    $files = glob($directory . '*');
    foreach ($files as $path) {
        if (is_file($path)) {
            $size += filesize($path);
        }
    }
    return $size;
}
// when uploading files
if (isset($_POST['submit'])) {
    $showstatus = array();
    if (dirSize($dirSave) >= 524288000) {
        echo "<script>alert('full storage !'); window.location = 'index.php';</script>";
    } else {
        $amount = count($_FILES['file']['name']);
        for ($i = 0; $i < $amount; $i++) {
            $fileName = $_FILES['file']['name'][$i];
            if ($_FILES['file']['size'][$i] > 52428800) {
                array_push($showstatus, $fileName . " = file too big !");
            } else {
                if (!file_exists($dirSave . $fileName)) {
                    $temp = $_FILES['file']['tmp_name'][$i];
                    $status = move_uploaded_file($temp, $dirSave . $fileName);
                    if ($status) {
                        array_push($showstatus, $fileName . " = upload successful !");
                    } else {
                        array_push($showstatus, $fileName . " = failed to upload !");
                    }
                } else {
                    array_push($showstatus, $fileName . " = file already exists !");
                }
            }
        }
    }
    $info = "";
    for ($x = 0; $x <= count($showstatus) - 1; $x++) {
        $info = $info . "[" . $showstatus[$x] . "] ";
    }
    echo '<script>alert("' . $info . '"); window.location = "index.php";</script>';
    // when deleting a file
} else if ($_GET['action'] == "delete") {
    if (isset($_GET['file'])) {
        $file_path = $dirSave . $_GET['file'];
        if (!empty($file_path) && file_exists($file_path)) {
            unlink($file_path);
            header("Location: index.php");
        } else {
            echo "<script>alert('file not found !'); window.location = 'index.php';</script>";
        }
    } else {
        echo "<script>alert('failed to delete !'); window.location = 'index.php';</script>";
    }
    // when downloading a file
} else if ($_GET['action'] == "download") {
    if (isset($_GET['file'])) {
        $file_path = $dirSave . $_GET['file'];
        if (!empty($file_path) && file_exists($file_path)) {
            header("Pragma: public");
            header("Expired: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Connection: Keep-Alive");
            header("Content-Control: public");
            header("Content-Description: File Transfer");
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename=" . basename($file_path));
            header("Content-Transfer-Encoding: binary");
            header("Content-Length: " . filesize($file_path));
            flush();
            readfile($file_path);
            exit();
        } else {
            echo "<script>alert('file not found !'); window.location = 'index.php';</script>";
        }
    } else {
        echo "<script>alert('failed to download !'); window.location = 'index.php';</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>My-File</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/myfile/icon.png">
    <style>
        th {
            background-color: #aaaaaa;
        }

        td {
            text-align: left;
        }

        tr:nth-child(even) {
            background-color: #dddddd;
        }

        .loader {
            border: 8px solid #f3f3f3;
            border-radius: 50%;
            border-top: 8px solid #3498db;
            width: 23px;
            height: 23px;
            -webkit-animation: spin 2s linear infinite;
            animation: spin 2s linear infinite;
        }

        @-webkit-keyframes spin {
            0% {
                -webkit-transform: rotate(0deg);
            }

            100% {
                -webkit-transform: rotate(360deg);
            }
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <?php
    // show information
    $size = dirSize($dirSave);
    if ($size >= 524288000) {
        $usage = 'FULL STORAGE !';
        $disabledinput = 'disabled';
        $showtabel = 'width: 100%;';
    } else if ($size == 0) {
        $usage = '0 / 500MB';
        $disabledinput = '';
        $showtabel = 'display: none;';
    } else {
        $usage = formatBytes($size) . ' / 500MB';
        $disabledinput = '';
        $showtabel = 'width: 100%;';
    }
    ?>
    <h2>My-<i>File</i> | simple storage</h2>
    <form id="formupload" style="display: block;" action="index.php" method="post" enctype="multipart/form-data">
        <input type="file" onchange="Filevalidation()" name="file[]" id="uploadFile" multiple required <?= $disabledinput ?>>
        <input type="submit" onclick="Submit()" name="submit" id="submit" value="Upload" <?= $disabledinput ?>>
        <br>
        *maximum upload file size : 50MB
    </form>
    <div id="loading" style="display: none;" class="loader"></div>
    <p id="uploading" style="display: none;">uploading...</p>
    <p id="usage" style="display: block;">current storage usage : <?= $usage ?></p>
    <hr>
    <table style="<?= $showtabel ?>">
        <tr>
            <th>No.</th>
            <th>Name</th>
            <th>Size</th>
            <th>Date</th>
            <th>Action</th>
        </tr>
        <?php
        // show data
        $files = glob($dirSave . '*');
        $no = 1;
        foreach ($files as $file) {
            if (is_file($file)) {
                $file = substr($file, 5);
                echo "<tr>";
                echo "<td>" . $no++ . "</td>";
                echo "<td>" . substr($file, 0, 35) . "..." . "</td>";
                echo "<td>" . formatBytes(filesize($dirSave . $file)) . "</td>";
                echo "<td>" . date("d-M-Y H:i:s", filemtime($dirSave . $file)) . "</td>";
                $file = "'" . rawurlencode($file) . "'";
                echo "<td><button onclick=Action(" . $file . ",'0');>Download</button> <button onclick=Action(" . $file . ",'1');>Delete</button></td>";
                echo "</tr>";
            }
        }
        ?>
    </table>
    <script>
        Filevalidation = () => {
            var uf = document.getElementById('uploadFile');
            if (uf.files.length > 0) {
                var total = 0;
                for (var i = 0; i <= uf.files.length - 1; i++) {
                    total += uf.files.item(i).size;
                }
                if (total > 52428800) {
                    alert("file too big !");
                    window.location = "index.php";
                } else if (total > (524288000 - <?= $size ?>)) {
                    alert("no space !");
                    window.location = "index.php";
                }
            }
        }

        function Submit() {
            if (document.getElementById("uploadFile").files.length > 0) {
                document.getElementById("formupload").style.display = "none";
                document.getElementById("usage").style.display = "none";
                document.getElementById("loading").style.display = "block";
                document.getElementById("uploading").style.display = "block";
            }
        }

        function Action(file, id) {
            if (parseInt(id) == 0) {
                window.location = "index.php?action=download&file=" + file;
            } else {
                if (confirm("delete this file? : " + decodeURIComponent(file).slice(0, 35))) {
                    window.location = "index.php?action=delete&file=" + file;
                }
            }
        }
    </script>
</body>

</html>