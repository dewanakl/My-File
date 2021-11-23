<?php
// require config
require_once 'config.php';

// check folder permissions
if (!is_writable(dirSave)) {
    exit("please change folder permissions");
}

// check curl
if (!function_exists("curl_init")) {
    exit("requires PHP's cURL extension");
}

// function to convert size
function formatBytes($size, $precision = 1)
{
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// rename filename
function filename_sanitizer($unsafeFilename)
{
    // windows characters
    $dangerousCharacters = array("\\", "/", ":", "*", "?", "\"", "<", ">", "|");
    $safe_filename = str_replace($dangerousCharacters, '', $unsafeFilename);
    return $safe_filename;
}

// function show alert info
function alertInfo($msg)
{
    exit("<script>alert('{$msg}'); window.location = 'index.php';</script>");
}

// calculate folder size
$dirSize = array_sum(array_map("filesize", glob(dirSave . "*")));

// when uploading files
if (isset($_POST['upload'])) {
    $showstatus = "";
    if ($dirSize <= folderSize) {
        $amount = count($_FILES['file']['name']);
        for ($i = 0; $i < $amount; $i++) {
            $fileName = filename_sanitizer($_FILES['file']['name'][$i]);
            if ($_FILES['file']['size'][$i] <= maxfileSize) {
                if (!file_exists(dirSave . $fileName)) {
                    $temp = $_FILES['file']['tmp_name'][$i];
                    $status = move_uploaded_file($temp, dirSave . $fileName);
                    if ($status) {
                        $showstatus .= $fileName .  " =0 | ";
                    } else {
                        $showstatus .= $fileName .  " =1 | ";
                    }
                } else {
                    $showstatus .= $fileName .  " =2 | ";
                }
            } else {
                $showstatus .= $fileName . " =3 | ";
            }
        }
    } else {
        alertInfo("full storage !");
    }
    exit("<script>document.cookie = 'infofile = " . rawurlencode($showstatus) . "'; window.location = 'index.php';</script>");
}

// when deleting a file
if (isset($_GET['delete'])) {
    $varFile = $_GET['file'];
    if (isset($varFile) && file_exists(dirSave . $varFile)) {
        if (unlink(dirSave . $varFile)) {
            exit("<script>document.cookie = 'infofile = " . rawurlencode($varFile . " =4 | ") . "'; window.location = 'index.php';</script>");
        } else {
            alertInfo("server error !");
        }
    } else {
        alertInfo("failed to delete !");
    }
}

// when rename a file
if (isset($_GET['rename'])) {
    $varOldFile = $_GET['old'];
    $varNewFile = filename_sanitizer($_GET['new']);
    if (isset($varOldFile) && isset($varNewFile) && $varNewFile != '') {
        if (!file_exists(dirSave . $varNewFile)) {
            if (file_exists(dirSave . $varOldFile)) {
                if (rename(dirSave . $varOldFile, dirSave . $varNewFile)) {
                    exit("<script>document.cookie = 'infofile = " . rawurlencode($varNewFile . " =5 | ") . "'; window.location = 'index.php';</script>");
                } else {
                    alertInfo("server error !");
                }
            } else {
                alertInfo("file not found !");
            }
        } else {
            alertInfo("filename must be different !");
        }
    } else {
        alertInfo("rename failed !");
    }
}

// when from url
if (isset($_POST['url'])) {
    $file_url = filter_var($_POST['link'], FILTER_SANITIZE_URL);
    if ((empty($file_url)) || (filter_var($file_url, FILTER_VALIDATE_URL) === false)) {
        alertInfo("Invalid URL !");
    }
    $file_name = basename(parse_url($file_url, PHP_URL_PATH));
    $file_ext = strtolower(pathinfo($file_url, PATHINFO_EXTENSION));
    if (empty($file_name)) {
        alertInfo("Invalid file name !");
    } else {
        $file_name = filename_sanitizer($file_name);
    }
    if (strpos($file_ext, '?') !== false) {
        $file_ext = substr($file_ext, 0, strpos($file_ext, '?'));
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $file_url);
    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $raw = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if (!empty($curl_error) || $http_status != 200) {
        $showstatus = $curl_error .  " [ falied ] <span>&#x274C;</span>";
        exit("<script>document.cookie = 'infofile = " . rawurlencode($showstatus) . "'; window.location = 'index.php';</script>");
    }

    $saveto = dirSave . $file_name;

    if (file_exists($saveto)) {
        $showstatus = $file_name .  " [ file exists ] <span>&#x274C;</span>";
        exit("<script>document.cookie = 'infofile = " . rawurlencode($showstatus) . "'; window.location = 'index.php';</script>");
    }

    if (file_put_contents($saveto, $raw)) {
        $showstatus = $file_name .  " [ successful ] <span>&#x2705;</span>";
        exit("<script>document.cookie = 'infofile = " . rawurlencode($showstatus) . "'; window.location = 'index.php';</script>");
    } else {
        $showstatus = $file_name .  " [ falied ] <span>&#x274C;</span>";
        exit("<script>document.cookie = 'infofile = " . rawurlencode($showstatus) . "'; window.location = 'index.php';</script>");
    }
}

// show information
if ($dirSize == 0) {
    $usage = '0 / ' . formatBytes(folderSize) . ' | 0%';
    $disabledinput = false;
} else if ($dirSize < (folderSize - (folderSize * 0.0005))) { // 0.05% of storage
    $usage = formatBytes($dirSize) . ' / ' . formatBytes(folderSize) . ' | ' . round(($dirSize / folderSize) * 100, 0) . '%';
    $disabledinput = false;
} else {
    $usage = 'FULL STORAGE !';
    $disabledinput = true;
}
?>
<!DOCTYPE html>
<html dir="ltr" lang="en" translate="no">

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
        #topbtn {
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

        #topbtn:hover {
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

        <?php if (!($disabledinput)) : ?>

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

        /* modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            padding-top: 100px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0, 0, 0);
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
        }

        .close {
            color: #aaaaaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }

        /* search */
        .search {
            float: right;
        }

        .btnsearch {
            display: none;
        }

        #mySearch {
            margin-right: auto;
            padding: 7px 14px;
        }

        #mySearch::-webkit-search-cancel-button {
            position: relative;
            right: 0px;
            cursor: pointer;
        }

        @media screen and (max-width: 500px) {
            .search {
                float: none;
                margin-top: 5px;
                display: none;
            }

            .btnsearch {
                display: inline;
                padding: 7px 7px;
            }
        }

        <?php endif ?>
    </style>
</head>

<body>
    <h2 id="top">My-<i>File</i> | simple storage</h2>
    <p style="display: block;">Current usage : <?= $usage ?></p>
    <?php if (!($disabledinput)) : ?>
        <button style="display: inline; padding: 7px 14px;" onclick="modalupload.style.display = 'block'">Upload</button>
        <button style="display: inline; padding: 7px 14px;" onclick="modalurl.style.display = 'block'">From url</button>
    <?php endif ?>
    <button style="display: inline; padding: 7px 14px;" onclick="window.location = 'paste.php'">Paste it</button>
    <button class="btnsearch" onclick="Sbox()">Search</button>
    <div class="search" id="searchbox">
        <p style="display: inline; margin: 0px;">Search : </p>
        <input type="search" id="mySearch" oninput="Search()" placeholder="Search for names..">
    </div>
    <?php if (isset($_COOKIE['infofile'])) : ?>
        <?php
        // show info upload
        $replace1 = str_replace(" | ", "\r\n", rawurldecode($_COOKIE['infofile']));
        $replace2 = str_replace("=0", "[ upload successful ! ] <span>&#x2705;</span>", $replace1);
        $replace3 = str_replace("=1", "[ failed to upload ! ] <span>&#x274C;</span>", $replace2);
        $replace4 = str_replace("=2", "[ file already exists ! ] <span>&#x274C;</span>", $replace3);
        $replace5 = str_replace("=3", "[ file too big ! ] <span>&#x274C;</span>", $replace4);
        $replace6 = str_replace("=4", "[ delete ] <span>&#x2705;</span>", $replace5);
        $replace = str_replace("=5", "[ rename ] <span>&#x2705;</span>", $replace6);
        ?>
        <hr>
        <h3 style="margin-top: 0px; margin-bottom: 0px;">Status :</h3>
        <div style="overflow-x:auto;">
            <pre style="font-family: 'Times New Roman'"><?= $replace ?></pre>
        </div>
    <?php endif ?>
    <hr>
    <div style="overflow-x:auto;">
        <table style="width: 100%;" id="myTable">
            <tr>
                <th>No</th>
                <th>Name</th>
                <th>Size</th>
                <th>Date</th>
                <th>Time</th>
                <th>Action</th>
            </tr>
            <?php
            $no = 1;
            $dataNameFile = [];
            ?>
            <?php foreach (glob(dirSave . '*') as $file) : ?>
                <?php if (is_file($file)) : ?>
                    <?php
                    $file = substr($file, strlen(dirSave));
                    if ((strtotime(date("d-M-Y H:i:s")) - strtotime(date("d-M-Y H:i:s", filemtime(dirSave . $file)))) <= (newFile * 60)) {
                        $filenew = ' <span style="background: black; color: white; padding: 0 10px 0 10px;">new</span>';
                    } else {
                        $filenew = "";
                    }
                    array_push($dataNameFile, rawurlencode($file));
                    ?>
                    <tr>
                        <td><?= $no ?></td>
                        <td><?= htmlspecialchars($file) . $filenew ?></td>
                        <td><?= formatBytes(filesize(dirSave . $file)) ?></td>
                        <td><?= date("d-M-Y", filemtime(dirSave . $file)) ?></td>
                        <td><?= date("H:i:s", filemtime(dirSave . $file)) ?></td>
                        <td>
                            <button onclick="window.open('file.php/' + dataName[<?= $no - 1 ?>], '_blank')">View</button>
                            <button onclick="window.location = 'file.php/' + dataName[<?= $no - 1 ?>] + '?download'">Download</button>
                            <button onclick="Rename(dataName[<?= $no - 1 ?>])">Rename</button>
                            <button onclick="Delete(dataName[<?= $no++ - 1 ?>])">Delete</button>
                        </td>
                    </tr>
                <?php endif ?>
            <?php endforeach ?>
        </table>
    </div>
    <?php if (!($disabledinput)) : ?>
        <div id="Modalupload" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Upload file</h2>
                <hr style="margin-bottom: 25px;">
                <form id="formUploadFile" method="post" enctype="multipart/form-data">
                    <p>Max file size : <?= formatBytes(maxfileSize) ?></p>
                    <input type="file" onchange="fileValidation()" name="file[]" id="uploadFile" style="width: 50%;" multiple required />
                    <hr style="margin-bottom: 25px; margin-top: 25px;">
                    <input type="submit" onclick="Submit('upload')" style="padding: 12px 24px;" name="upload" value="Upload">
                </form>
            </div>
        </div>
        <div id="Modalurl" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Save from url</h2>
                <hr style="margin-bottom: 25px;">
                <form method="post">
                    <p>Enter URL :</p>
                    <input type="url" name="link" style="width: 75%;">
                    <hr style="margin-bottom: 25px; margin-top: 25px;">
                    <input type="submit" onclick="Submit('url')" style="padding: 12px 24px;" name="url" value="Save" />
                </form>
            </div>
        </div>
        <div id="Modalloader" class="modal">
            <div class="modal-content">
                <div class="loader"></div>
                <h3>Loading...</h3>
                <h4>please don't close this page !</h4>
            </div>
        </div>
    <?php endif ?>
    <a id="topbtn" href="#top" style="text-decoration:none; display: none;">TOP</a>
    <hr>
    <footer>
        <p style="text-align: center;">
            Created with
            <svg width="16" height="16" fill="red" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M8 1.314C12.438-3.248 23.534 4.735 8 15-7.534 4.736 3.562-3.248 8 1.314z" />
            </svg>
            by
            <a href="https://www.instagram.com/dewana_kl/">DewanaKL</a>
        </p>
    </footer>
    <script>
        <?php if (isset($replace)) : ?>
            // delete cookie
            document.cookie = 'infofile=; Max-Age=0;';
        <?php endif ?>
        let dataName = <?= json_encode($dataNameFile) ?>;
        <?php if (!($disabledinput)) : ?>
            // validasi
            function fileValidation() {
                let uf = document.getElementById('uploadFile');
                let form = document.getElementById("formUploadFile");
                if (uf.files.length > 0) {
                    let total = 0;
                    for (let i = 0; i <= uf.files.length - 1; i++) {
                        total += uf.files.item(i).size;
                    }
                    if (total > <?= maxfileSize ?>) {
                        form.reset();
                        alert("file too big !");
                    } else if (total > <?= folderSize - $dirSize ?>) {
                        form.reset();
                        alert("no space !");
                    }
                }
            }

            // add file
            function Submit(prm) {
                if (prm == 'upload') {
                    if (document.getElementById("uploadFile").files.length > 0) {
                        document.getElementById("Modalupload").style.display = "none";
                        document.getElementById("Modalloader").style.display = "block";
                    }
                } else if (prm == 'url') {
                    document.getElementById("Modalurl").style.display = "none";
                    document.getElementById("Modalloader").style.display = "block";
                }
            }

            // modal
            let modalupload = document.getElementById("Modalupload");
            let modalurl = document.getElementById("Modalurl");
            let span1 = document.getElementsByClassName("close")[0];
            let span2 = document.getElementsByClassName("close")[1];

            span1.onclick = function() {
                modalupload.style.display = "none";
            }
            span2.onclick = function() {
                modalurl.style.display = "none";
            }

            window.onclick = function(event) {
                if (event.target == modalupload) {
                    modalupload.style.display = "none";
                }
                if (event.target == modalurl) {
                    modalurl.style.display = "none";
                }
            }

        <?php endif ?>

        // delete
        function Delete(file) {
            if (confirm("delete this file ? : " + decodeURIComponent(file))) {
                location.href = "index.php?delete&file=" + file;
            }
        }

        // rename
        function Rename(oldnamefile) {
            let extfile = "." + oldnamefile.substring(oldnamefile.lastIndexOf(".") + 1);
            let newnamefile = prompt("Enter new name (" + extfile + ")", decodeURIComponent(oldnamefile.substring(0, oldnamefile.lastIndexOf('.'))));
            if (newnamefile != null) {
                if (newnamefile != '') {
                    if (newnamefile + extfile != decodeURIComponent(oldnamefile)) {
                        if (confirm("rename this file ? : " + newnamefile + extfile)) {
                            location.href = "index.php?rename&old=" + oldnamefile + "&new=" + encodeURIComponent(newnamefile + extfile);
                        }
                    } else {
                        alert("no name change !");
                    }
                } else {
                    alert("filename cannot be empty !");
                }
            }
        }

        // search
        function Search() {
            let input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("mySearch");
            filter = input.value.toUpperCase();
            table = document.getElementById("myTable");
            tr = table.getElementsByTagName("tr");
            for (i = 0; i < tr.length; i++) {
                td = tr[i].getElementsByTagName("td")[1];
                if (td) {
                    txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }

        // search box
        function Sbox() {
            if (document.getElementById('searchbox').style.display == 'block') {
                document.getElementById('searchbox').style.display = 'none';
            } else {
                document.getElementById('searchbox').style.display = 'block';
            }
        }

        // top
        window.onscroll = function() {
            if (document.body.scrollTop > 40 || document.documentElement.scrollTop > 40) {
                document.getElementById("topbtn").style.display = "block";
            } else {
                document.getElementById("topbtn").style.display = "none";
            }
        };
    </script>
</body>

</html>
