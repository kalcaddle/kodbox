<?php
//Hide X-Powered-By PHP 
header_remove("X-Powered-By");
header("Location: /");
exit;
include 'index.php'; ?>
