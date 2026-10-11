<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/flash.php';

logout_user();
set_flash('info', 'Anda telah berhasil keluar dari sistem.');
header("Location: login.php");
exit;
