<?php
// require config
require_once 'config.php';

// check folder permissions
if (!is_writable(paste)) {
    exit("please change file permissions");
}

// write file
if (isset($_POST['text'])) {
    $fp = fopen(paste, "w");
    fwrite($fp, $_POST['text']);
    fclose($fp);
    exit;
}
?>
<!DOCTYPE html>
<html dir="ltr" lang="en" translate="no">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google" content="notranslate">
    <title>Paste-it</title>
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
    </style>
</head>

<body>
    <h2 id="top">Paste-<i>it</i> | simple paste</h2>
    <button style="display: inline; padding: 7px 14px;" onclick="location.href='index.php'">Back</button>
    <p style="display: inline; padding: 7px 14px;" id="message"></p>
    <hr>
    <textarea id="val" oninput="save();" style="max-width:100%; min-height:<?= count(file(paste)) / 5 * 3 ?>cm; height:100%; width:100%;" spellcheck="false"><?= htmlspecialchars(file_get_contents(paste)) ?></textarea>
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
        // message
        var mtimeout;

        function messageClear() {
            clearTimeout(mtimeout);
            mtimeout = setTimeout(function() {
                document.getElementById('message').innerHTML = '';
            }, 4500);
        }
        // ajax
        function savePost() {
            var xhttp = new XMLHttpRequest();
            var data = new FormData();
            var content = document.getElementById('val').value;
            data.append('text', content);
            xhttp.open('POST', 'paste.php');
            xhttp.send(data);
            xhttp.onreadystatechange = function() {
                if (this.status == 200 && this.readyState == 4) {
                    var d = new Date();
                    var h = (d.getHours() < 10 ? '0' : '') + d.getHours();
                    var m = (d.getMinutes() < 10 ? '0' : '') + d.getMinutes();
                    var s = (d.getSeconds() < 10 ? '0' : '') + d.getSeconds();
                    document.getElementById('message').innerHTML = '<span>&#9989;</span> Saved last ' + h + ':' + m + ':' + s;
                    messageClear();
                } else {
                    document.getElementById('message').innerHTML = '<span>&#9940;</span> Connection failed !';
                }
            }
        }
        // auto save
        var timeoutId;

        function save() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(function() {
                savePost();
            }, 1500);
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