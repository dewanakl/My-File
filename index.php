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
    return str_replace($dangerousCharacters, '', $unsafeFilename);
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
        for ($i = 0; $i < count($_FILES['file']['name']); $i++) {
            $fileName = filename_sanitizer($_FILES['file']['name'][$i]);
            if ($_FILES['file']['size'][$i] <= maxfileSize) {
                if (!file_exists(dirSave . $fileName)) {
                    $temp = $_FILES['file']['tmp_name'][$i];
                    $status = move_uploaded_file($temp, dirSave . $fileName);
                    if ($status) {
                        $showstatus .= $fileName .  "=0|";
                    } else {
                        $showstatus .= $fileName .  "=1|";
                    }
                } else {
                    $showstatus .= $fileName .  "=2|";
                }
            } else {
                $showstatus .= $fileName . "=3|";
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
            echo 'true';
        } else {
            echo "server error !";
        }
    } else {
        echo "file not found !";
    }
    exit;
}

// when rename a file
if (isset($_GET['rename'])) {
    $varOldFile = $_GET['old'];
    $varNewFile = filename_sanitizer($_GET['new']);
    if (isset($varOldFile) && isset($varNewFile) && $varNewFile != '') {
        if (!file_exists(dirSave . $varNewFile)) {
            if (file_exists(dirSave . $varOldFile)) {
                if (rename(dirSave . $varOldFile, dirSave . $varNewFile)) {
                    echo 'true' . rawurlencode($varNewFile);
                } else {
                    echo "server error !";
                }
            } else {
                echo "file not found !";
            }
        } else {
            echo "filename must be different !";
        }
    } else {
        echo "rename failed !";
    }
    exit;
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
        /* smooth scroll */
        html {
            scroll-behavior: smooth;
        }

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
            bottom: 15px;
            right: 25px;
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
            height: 25px;
            border-bottom: 1px solid #bbb;
            cursor: context-menu;
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

        <?php endif ?>

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

        @media screen and (max-width: 530px) {
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

        /* context menu */

        .context-menu {
            display: none;
            position: absolute;
            background-color: #fff;
            border: solid 2px #bbb;
            box-shadow: 2px 2px 3px #aaa;
        }

        .context-menu--active {
            display: block;
        }

        .context-menu__items {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .context-menu__item {
            display: block;
        }

        .context-menu__link {
            display: block;
            padding: 4px 8px;
            color: #000;
            text-decoration: none;
        }

        .context-menu__link:hover {
            color: #fff;
            background-color: #000;
        }
    </style>
</head>

<body>
    <h2>My-<i>File</i> | simple storage</h2>
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
        $arry = array(
            "|" => "\r\n",
            "=0" => " [ upload successful ! ] <span>&#x2705;</span>",
            "=1" => " [ failed to upload ! ] <span>&#x274C;</span>",
            "=2" => " [ file already exists ! ] <span>&#x274C;</span>",
            "=3" => " [ file too big ! ] <span>&#x274C;</span>"
        );
        $replace = strtr(rawurldecode($_COOKIE['infofile']), $arry);
        ?>
        <hr>
        <h3 style="margin-top: 0px; margin-bottom: 0px;">Status :</h3>
        <div style="overflow-x:auto;">
            <pre style="font-family: 'Times New Roman'"><?= $replace ?></pre>
        </div>
    <?php endif ?>
    <hr>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;" id="myTable">
            <tr>
                <th>Name</th>
                <th>Size</th>
                <th>Date</th>
            </tr>
            <?php
            $no = 0;
            $dataNameFile = array();
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
                    <tr class="task" data-id="<?= $no++ ?>">
                        <td><?= htmlspecialchars($file) . $filenew ?></td>
                        <td><?= formatBytes(filesize(dirSave . $file)) ?></td>
                        <td title="Time <?= date("H:i", filemtime(dirSave . $file)) ?>"><?= date("d M Y", filemtime(dirSave . $file)) ?></td>
                    </tr>
                <?php endif ?>
            <?php endforeach ?>
        </table>
    </div>
    <?php if (!($disabledinput)) : ?>
        <div id="Modalupload" class="modal">
            <div class="modal-content">
                <span class="close" id="closeUpload">&times;</span>
                <h2>Upload file</h2>
                <hr style="margin-bottom: 25px;">
                <form id="formUploadFile" method="post" enctype="multipart/form-data">
                    <p>Max file size : <?= (folderSize - $dirSize) > maxfileSize ? formatBytes(maxfileSize) : formatBytes(folderSize - $dirSize) ?></p>
                    <input type="file" onchange="fileValidation()" name="file[]" id="uploadFile" style="width: 50%;" multiple required />
                    <hr style="margin-bottom: 25px; margin-top: 25px;">
                    <input type="submit" onclick="Submit('upload')" style="padding: 12px 24px;" name="upload" value="Upload">
                </form>
            </div>
        </div>
        <div id="Modalurl" class="modal">
            <div class="modal-content">
                <span class="close" id="closeUrl">&times;</span>
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
    <div id="context-menu" class="context-menu">
        <p id="context-name-file" style="cursor: default; margin: 3px; padding: 2px;"></p>
        <hr style="margin: 0px;">
        <div class="context-menu__items">
            <div class="context-menu__item">
                <a id="context-menu-view" href="#" style="display: none;" class="context-menu__link" data-action="View">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z" />
                        <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z" />
                    </svg>
                    View
                </a>
            </div>
            <div class="context-menu__item">
                <a href="#" class="context-menu__link" data-action="Download">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 2a5.53 5.53 0 0 0-3.594 1.342c-.766.66-1.321 1.52-1.464 2.383C1.266 6.095 0 7.555 0 9.318 0 11.366 1.708 13 3.781 13h8.906C14.502 13 16 11.57 16 9.773c0-1.636-1.242-2.969-2.834-3.194C12.923 3.999 10.69 2 8 2zm2.354 6.854-2 2a.5.5 0 0 1-.708 0l-2-2a.5.5 0 1 1 .708-.708L7.5 9.293V5.5a.5.5 0 0 1 1 0v3.793l1.146-1.147a.5.5 0 0 1 .708.708z" />
                    </svg>
                    Download
                </a>
            </div>
            <div class="context-menu__item">
                <a href="#" class="context-menu__link" data-action="Edit">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z" />
                        <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z" />
                    </svg>
                    Rename
                </a>
            </div>
            <div class="context-menu__item">
                <a href="#" class="context-menu__link" data-action="Delete">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M2.5 1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1H3v9a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V4h.5a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H10a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1H2.5zm3 4a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-1 0v-7a.5.5 0 0 1 .5-.5zM8 5a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-1 0v-7A.5.5 0 0 1 8 5zm3 .5v7a.5.5 0 0 1-1 0v-7a.5.5 0 0 1 1 0z" />
                    </svg>
                    Delete
                </a>
            </div>
        </div>
    </div>
    <button type="button" id="topbtn" onclick="topFunction()">TOP</button>
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
        // data name
        <?= "const dataName = " . json_encode($dataNameFile) . ";" ?>

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
            let spanUpload = document.getElementById("closeUpload");
            let spanUrl = document.getElementById("closeUrl");

            spanUpload.onclick = function() {
                modalupload.style.display = "none";
            }

            spanUrl.onclick = function() {
                modalurl.style.display = "none";
            }

            window.onclick = function(event) {
                if (event.target == modalupload) {
                    modalupload.style.display = "none";
                } else if (event.target == modalurl) {
                    modalurl.style.display = "none";
                }
            }

        <?php endif ?>

        // ajax
        function getStatus(url, callback) {
            var xhr = new XMLHttpRequest;
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4) {
                    if (xhr.status == 200) {
                        callback(xhr.responseText);
                    } else {
                        alert("Connection failed !");
                    }
                }
            };
            xhr.open("GET", url);
            xhr.send();
        };

        // remove table row
        function removeRow(id) {
            var tr = document.querySelector('[data-id="' + id + '"]')
            var tbl = tr; // Look up the hierarchy for TABLE
            while (tbl != document && tbl.nodeName != 'TABLE') {
                tbl = tbl.parentNode;
            }
            if (tbl && tbl.nodeName == 'TABLE') {
                while (tr.hasChildNodes()) {
                    tr.removeChild(tr.lastChild);
                }
                tr.parentNode.removeChild(tr);
            }
        }

        // rename
        function Rename(id) {
            let oldnamefile = dataName[id];
            let extfile = "." + oldnamefile.substring(oldnamefile.lastIndexOf(".") + 1);
            let newnamefile = prompt("Enter new name (" + extfile + ")", decodeURIComponent(oldnamefile.substring(0, oldnamefile.lastIndexOf('.'))));
            if (newnamefile != null) {
                if (newnamefile != '' && newnamefile != ' ') {
                    if (newnamefile + extfile != decodeURIComponent(oldnamefile)) {
                        if (confirm("rename this file ? : " + newnamefile + extfile)) {
                            var url = "index.php?rename&old=" + oldnamefile + "&new=" + encodeURIComponent(newnamefile + extfile);
                            getStatus(url, function(statuses) {
                                if (statuses.slice(0, 4) == 'true') {
                                    var tr = document.querySelector('[data-id="' + id + '"]');
                                    var trs = tr.getElementsByTagName("td")[0];
                                    var trspan = tr.querySelector("span");
                                    result = decodeURIComponent(statuses.substring(4));
                                    if (trspan != null) {
                                        result += ' <span style="background: black; color: white; padding: 0 10px 0 10px;">new</span>';
                                    }
                                    trs.innerHTML = result;
                                    dataName[id] = statuses.substring(4);
                                } else {
                                    alert(statuses);
                                }
                            });
                        }
                    } else {
                        alert("no name change !");
                    }
                } else {
                    alert("filename cannot be empty !");
                }
            }
        }

        // delete
        function Delete(id) {
            let file = dataName[id];
            if (confirm("delete this file ? : " + decodeURIComponent(file))) {
                let url = "index.php?delete&file=" + file;
                getStatus(url, function(statuses) {
                    if (statuses == 'true') {
                        removeRow(id);
                    } else {
                        alert(statuses);
                    }
                });
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
                td = tr[i].getElementsByTagName("td")[0];
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
        let mybutton = document.getElementById("topbtn");

        function scrollFunction() {
            if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
                mybutton.style.display = "block";
            } else {
                mybutton.style.display = "none";
            }
        }

        function topFunction() {
            document.body.scrollTop = 0;
            document.documentElement.scrollTop = 0;
        }

        window.onscroll = function() {
            scrollFunction();
        };

        // context menu
        var contextMenuLinkClassName = "context-menu__link";
        var contextMenuActive = "context-menu--active";
        const scope = document.querySelector("body");

        var taskItemClassName = "task";
        var taskItemInContext;

        var menu = document.querySelector("#context-menu");
        var menuState = 0;

        function clickInsideElement(e, className) {
            var el = e.srcElement || e.target;
            if (el.classList.contains(className)) {
                return el;
            } else {
                while (el = el.parentNode) {
                    if (el.classList && el.classList.contains(className)) {
                        return el;
                    }
                }
            }
            return false;
        }

        function positionMenu(e) {
            var posx = 0;
            var posy = 0;

            if (!e) var e = window.event;

            if (e.pageX || e.pageY) {
                posx = e.pageX;
                posy = e.pageY;
            } else if (e.clientX || e.clientY) {
                posx = e.clientX + document.body.scrollLeft + document.documentElement.scrollLeft;
                posy = e.clientY + document.body.scrollTop + document.documentElement.scrollTop;
            }

            mouseX = posx;
            mouseY = posy;

            let {
                left: scopeOffsetX,
                top: scopeOffsetY,
            } = scope.getBoundingClientRect();

            scopeOffsetX = scopeOffsetX < 0 ? 0 : scopeOffsetX;
            scopeOffsetY = scopeOffsetY < 0 ? 0 : scopeOffsetY;

            const scopeX = mouseX - scopeOffsetX;
            const scopeY = mouseY - scopeOffsetY;

            const outOfBoundsOnX =
                scopeX + menu.clientWidth > scope.clientWidth;

            const outOfBoundsOnY =
                scopeY + menu.clientHeight > scope.clientHeight;

            let normalizedX = mouseX;
            let normalizedY = mouseY;

            // ? normalize on X
            if (outOfBoundsOnX) {
                normalizedX =
                    scopeOffsetX + scope.clientWidth - menu.clientWidth;
            }

            // ? normalize on Y
            if (outOfBoundsOnY) {
                normalizedY =
                    scopeOffsetY + scope.clientHeight - menu.clientHeight;
            }
            menu.style.left = normalizedX + "px";
            menu.style.top = normalizedY + "px";
        }

        function toggleMenuOn() {
            if (menuState !== 1) {
                menuState = 1;
                menu.classList.add(contextMenuActive);
            }
        }

        function toggleMenuOff() {
            if (menuState !== 0) {
                menuState = 0;
                menu.classList.remove(contextMenuActive);
            }
        }

        document.addEventListener("contextmenu", function(e) {
            taskItemInContext = clickInsideElement(e, taskItemClassName);
            if (taskItemInContext) {
                const mime = ['txt', 'html', 'php', 'css', 'png', 'jpeg', 'jpg', 'gif', 'bmp', 'ico', 'svg', 'mp4', 'mkv', 'mp3', 'js', 'json', 'pdf'];
                var namefile = decodeURIComponent(dataName[taskItemInContext.getAttribute("data-id")]);
                document.getElementById('context-name-file').innerHTML = namefile;
                var type = namefile.substring(namefile.lastIndexOf(".") + 1)
                if (mime.includes(type.toLowerCase())) {
                    document.getElementById("context-menu-view").style.display = "block";
                } else {
                    document.getElementById("context-menu-view").style.display = "none";
                }
                e.preventDefault();
                toggleMenuOn();
                positionMenu(e);
            } else {
                taskItemInContext = null;
                toggleMenuOff();
            }
        });

        document.addEventListener("click", function(e) {
            var clickeElIsLink = clickInsideElement(e, contextMenuLinkClassName);
            if (clickeElIsLink) {
                e.preventDefault();
                var id = taskItemInContext.getAttribute("data-id");
                switch (clickeElIsLink.getAttribute("data-action")) {
                    case 'View':
                        window.open("file.php/" + dataName[id], "_blank");
                        break;
                    case 'Download':
                        location.href = "file.php/" + dataName[id] + "?download";
                        break;
                    case 'Edit':
                        Rename(id);
                        break;
                    case 'Delete':
                        Delete(id);
                        break;
                }
                toggleMenuOff();
            } else {
                var button = e.which || e.button;
                if (button === 1) {
                    toggleMenuOff();
                }
            }
        });

        window.onkeyup = function(e) {
            if (e.keyCode === 27) {
                toggleMenuOff();
            }
        };

        window.onresize = function(e) {
            toggleMenuOff();
        };
    </script>
</body>

</html>