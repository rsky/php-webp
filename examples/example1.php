<?php
chdir(dirname(__FILE__));
require_once './RIFF.php';
function webp_read_metadata($filename)
{
    return WebP::createFromFile($filename)->getMetadata();
}

$im = imagecreatefrompng('Lenna.png');
imagewebp($im, 'Lenna.webp', 24, $difference);
var_dump($difference);

$webp = WebP::createFromFile('Lenna.webp');
$webp->setComment('Lenna');
$webp->setCopyright('PLAYBOY');
$webp->dumpToFile('Lenna2.webp');
print_r(webp_read_metadata('Lenna2.webp'));

$im = imagecreatefromwebp('Lenna2.webp');
imagejpeg($im, 'Lenna.jpg');
