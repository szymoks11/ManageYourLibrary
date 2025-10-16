<?php
require 'phpqrcode/qrlib.php';
$code = $_GET['code']; // LIB0005
$file = "../cards/$code.png";
QRcode::png($code, $file, QR_ECLEVEL_L, 6);
echo "<h3>Karta członka</h3>
<p>Kod: $code</p>
<img src='$file'>";

?>