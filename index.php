<?php
// require config
require_once 'config.php';

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
    $showstatus = "";
    if (dirSize(dirSave) <= folderSize) {
        $amount = count($_FILES['file']['name']);
        for ($i = 0; $i < $amount; $i++) {
            $fileName = $_FILES['file']['name'][$i];
            if ($_FILES['file']['size'][$i] <= maxfileSize) {
                if (!file_exists(dirSave . $fileName)) {
                    $temp = $_FILES['file']['tmp_name'][$i];
                    $status = move_uploaded_file($temp, dirSave . $fileName);
                    if ($status) {
                        $showstatus .= substr($fileName, 0, 15) .  " =0" . " | ";
                    } else {
                        $showstatus .= substr($fileName, 0, 15) .  " =1" . " | ";
                    }
                } else {
                    $showstatus .= substr($fileName, 0, 15) .  " =2" . " | ";
                }
            } else {
                $showstatus .= substr($fileName, 0, 15) . " =3" . " | ";
            }
        }
    } else {
        exit("<script>alert('full storage !'); window.location = 'index.php';</script>");
    }
    exit("<script>document.cookie = 'infoupload = " . rawurlencode($showstatus) . "'; window.location = 'index.php';</script>");
    // when deleting a file
} else if (isset($_GET['delete'])) {
    if (isset($_GET['file'])) {
        $file_path = dirSave . $_GET['file'];
        if (!empty($file_path) && file_exists($file_path)) {
            if (unlink($file_path)) {
                header("Location: index.php");
                exit;
            } else {
                exit("<script>alert('error !'); window.location = 'index.php';</script>");
            }
        } else {
            exit("<script>alert('file not found !'); window.location = 'index.php';</script>");
        }
    } else {
        exit("<script>alert('failed to delete !'); window.location = 'index.php';</script>");
    }
}

// show information
$size = dirSize(dirSave);
$formatfolderSize = formatBytes(folderSize);
$formatmaxfileSize = formatBytes(maxfileSize);
if ($size == 0) {
    $usage = '0 / ' . $formatfolderSize . ' | 0%';
    $disabledinput = '';
    $showtabel = 'display: none;';
} else if ($size < (folderSize - (folderSize * 0.0005))) { // 0.05% of storage
    $percent = round(($size / folderSize) * 100, 0);
    $usage = formatBytes($size) . ' / ' . $formatfolderSize . ' | ' . $percent . '%';
    $disabledinput = '';
    $showtabel = 'width: 100%;';
} else {
    $usage = 'FULL STORAGE !';
    $disabledinput = 'disabled';
    $showtabel = 'width: 100%;';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google" content="notranslate">
    <title>My-File</title>
    <link rel="icon" type="image/png" href="icon.png">
    <style>
        /* scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #fff;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* top */
        #myBtn {
            display: none;
            position: fixed;
            bottom: 10px;
            right: 20px;
            z-index: 1000;
            font-size: 13px;
            border: none;
            outline: none;
            background-color: #888;
            color: white;
            cursor: pointer;
            padding: 13px;
            border-radius: 4px;
        }

        #myBtn:hover {
            background-color: #555;
        }

        /* table */
        th {
            background-color: #aaaaaa;
        }

        td {
            text-align: left;
        }

        tr:nth-child(even) {
            background-color: #eeeeee;
        }

        tr:hover {
            background-color: #cccccc;
        }

        /* loader */
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
    <script>
        // validasi
        function fileValidation() {
            var uf = document.getElementById('uploadFile');
            if (uf.files.length > 0) {
                var total = 0;
                for (var i = 0; i <= uf.files.length - 1; i++) {
                    total += uf.files.item(i).size;
                }
                if (total > <?= maxfileSize ?>) {
                    alert("file too big !");
                    window.location = "index.php";
                } else if (total > <?= folderSize - $size ?>) {
                    alert("no space !");
                    window.location = "index.php";
                }
            }
        }
        // add file
        function Submit() {
            if (document.getElementById("uploadFile").files.length > 0) {
                document.getElementById("formupload").style.display = "none";
                document.getElementById("usage").style.display = "none";
                document.getElementById("loading").style.display = "block";
                document.getElementById("uploading").style.display = "block";
            }
        }
        // delete
        function Delete(file, id) {
            if (confirm("delete this file? : " + decodeURIComponent(file).slice(0, 35))) {
                location.href = "index.php?delete&file=" + file + "#" + (id - 1);
            }
        }
        // top
        window.onscroll = function() {
            if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
                document.getElementById("myBtn").style.display = "block";
            } else {
                document.getElementById("myBtn").style.display = "none";
            }
        };
    </script>
</head>

<body>
    <h2 id="top">My-<i>File</i> | simple storage</h2>
    <form id="formupload" style="display: block;" action="index.php" method="post" enctype="multipart/form-data">
        <input type="file" onchange="fileValidation()" name="file[]" id="uploadFile" multiple required <?= $disabledinput ?>>
        <input type="submit" onclick="Submit()" name="submit" id="submit" value="Upload" <?= $disabledinput ?>>
        <br>
        *maximum upload file size : <?= $formatmaxfileSize ?>
    </form>
    <div id="loading" style="display: none;" class="loader"></div>
    <p id="uploading" style="display: none;">Uploading...</p>
    <p id="usage" style="display: block;">Current usage : <?= $usage ?></p>
    <hr>
    <?php if (isset($_COOKIE['infoupload'])) : ?>
        <?php
        // show info upload
        $replace1 = str_replace(" | ", "\r\n", rawurldecode($_COOKIE['infoupload']));
        $replace2 = str_replace("=0", "[ upload successful ! ] <span>&#x2705;</span>", $replace1);
        $replace3 = str_replace("=1", "[ failed to upload ! ] <span>&#x274C;</span>", $replace2);
        $replace4 = str_replace("=2", "[ file already exists ! ] <span>&#x274C;</span>", $replace3);
        $replace = str_replace("=3", "[ file too big ! ] <span>&#x274C;</span>", $replace4);
        ?>
        <div style="position: relative; text-align: left;">
            <pre style="font-family: 'Times New Roman'"><?= $replace ?></pre>
        </div>
        <hr>
    <?php endif; ?>
    <table style="<?= $showtabel ?>">
        <tr>
            <th>No</th>
            <th>Name</th>
            <th>Size</th>
            <th>Date</th>
            <th>Time</th>
            <th>Action</th>
        </tr>
        <?php
        // show data
        $files = glob(dirSave . '*');
        $no = 1;
        ?>
        <?php foreach ($files as $file) : ?>
            <?php if (is_file($file)) : ?>
                <?php
                $file = substr($file, strlen(dirSave));
                $showname = substr($file, 0, 35);
                if (strlen($file) >= 35) {
                    $showname .= '...';
                }
                ?>
                <tr id="<?= $no ?>">
                    <td><?= $no ?></td>
                    <td><?= $showname ?></td>
                    <td><?= formatBytes(filesize(dirSave . $file)) ?></td>
                    <td><?= date("d-M-Y", filemtime(dirSave . $file)) ?></td>
                    <td><?= date("H:i:s", filemtime(dirSave . $file)) ?></td>
                    <td>
                        <button onclick="location.href='file.php/<?= rawurlencode($file) ?>?download'">Download</button>
                        <button onclick="window.open('file.php/<?= rawurlencode($file) ?>', '_blank')">View</button>
                        <button onclick="Delete('<?= rawurlencode($file) ?>', <?= $no++ ?>)">Delete</button>
                    </td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </table>
    <a id="myBtn" href="#top" style="text-decoration:none;">TOP</a>
    <footer style="text-align: center;">
        <p>
            Created with
            <svg width="16" height="16" fill="red" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M8 1.314C12.438-3.248 23.534 4.735 8 15-7.534 4.736 3.562-3.248 8 1.314z" />
            </svg>
            by
            <a href="https://www.instagram.com/dewana_kl/">DewanaKL</a>
        </p>
    </footer>
    <?php if (isset($replace)) : ?>
        <script>
            document.cookie = 'infoupload=; Max-Age=0;';
        </script>
    <?php endif; ?>
</body>

</html>