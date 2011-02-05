--TEST--
imagewebp() function
--SKIPIF--
<?php
if (!extension_loaded('webp') || !file_exists('examples/Lenna.png')) {
    die('skip ');
}
?>
--FILE--
<?php
$im = imagecreatefrompng('examples/Lenna.png');
imagewebp($im, 'examples/Lenna.webp', 24, $difference);
echo $difference;
?>
--EXPECTREGEX--
^[0-9]+\.[0-9]+$
