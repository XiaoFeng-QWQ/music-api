<?php
if($_GET['time']+30 < time()){
    print_r("链接失效了！");
}else{
    $jmdata = str_replace('.m3u8','',$_GET['key']);
    $jm= base64_decode($jmdata);
    header('Location: '.$jm);
}
?>