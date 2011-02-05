--TEST--
imagecreatefromwebp() function
--SKIPIF--
<?php
if (!extension_loaded('webp') || !file_exists('examples/Lenna.webp')) {
    die('skip ');
}
?>
--FILE--
<?php
$im = imagecreatefromwebp('examples/Lenna.webp');
if (is_resource($im)) {
    echo 'OK';
}
imagetruecolortopalette($im, true, 256);
imagegif($im, 'examples/Lenna.gif');
?>
--EXPECT--
OK
