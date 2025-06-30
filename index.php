<?php
session_start();
if(!file_exists("install.lock")){
    header("Location: install/index.php");
    exit;
}

// 直接跳转到管理界面
header("Location: admin.php");
exit;
?>
