<?php
class RIFFException extends RuntimeException {}

abstract class RIFFChunk
{
    protected $id;
    protected $size;
    protected $data;

    public function __construct($source)
    {
        if (is_array($source)) {
            $chunkInfo = $source;
        } else {
            $chunkInfo = $this->parseChunk($source);
            if (8 + $chunkInfo['size'] !== strlen($source)) {
                $this->raiseError();
            }
        }
        $this->id = $chunkInfo['id'];
        $this->size = $chunkInfo['size'];
        $this->data = $chunkInfo['data'];
    }

    public function getId()
    {
        return $this->id;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function getData()
    {
        return $this->data;
    }

    public function dump()
    {
        return $this->id . pack('V', $this->size) . $this->data;
    }

    public function dumpToFile($filename)
    {
        file_put_contents($filename, $this->dump());
    }

    protected function parseChunk($source)
    {
        $length = strlen($source);
        if ($length < 9) {
            $this->raiseError();
        }

        $size = unpack('V', substr($source, 4, 4));
        if (8 + $size[1] > $length) {
            $this->raiseError();
        }

        return array(
            'id' => substr($source, 0, 4),
            'size' => $size[1],
            'data' => substr($source, 8, $size[1]),
        );
    }

    protected function raiseError(
        $message = "Not a valid RIFF structure.",
        $code = 0
    ) {
        throw new RIFFException($message, $code);
    }

    protected static function checkId($id)
    {
        if (!preg_match('/^[0-9A-Za-z_]{4}$/', $id)) {
            throw new RIFFException("Not a valid RIFF ID.");
        }
    }
}

class RIFFBinaryChunk extends RIFFChunk
{
    public static function createFromBinary($id, $data)
    {
        self::checkId($id);
        if (function_exists('get_called_class')) {
            $className = get_called_class();
        } else {
            $className = __CLASS__;
        }
        return new $className($id . pack('V', strlen($data)) . $data);
    }
}

class RIFFStringChunk extends RIFFChunk
{
    public function __construct($source)
    {
        parent::__construct($source);
        if (strpos($this->data, chr(0)) !== strlen($this->data) - 1) {
            $this->raiseError();
        }
    }

    public static function createFromString($id, $string)
    {
        self::checkId($id);
        if (function_exists('get_called_class')) {
            $className = get_called_class();
        } else {
            $className = __CLASS__;
        }
        return new $className(
            $id . pack('V', strlen($string) + 1) . $string . chr(0)
        );
    }

    public function getData()
    {
        return substr($this->data, 0, -1);
    }
}

abstract class RIFFListChunk extends RIFFChunk
{
    protected $type;
    protected $chunks = array();

    public function __construct($source)
    {
        parent::__construct($source);
        if (strlen($this->data) < 4) {
            $this->raiseError();
        }
        $this->type = substr($this->data, 0, 4);
        $this->parseSubChunks(substr($this->data, 4));
    }

    public function dump()
    {
        $dump = $this->id . pack('V', $this->size) . $this->type;
        foreach ($this->chunks as $chunk) {
            $dump .= $chunk->dump();
        }
        return $dump;
    }

    public function getAllChunks()
    {
        return $this->chunks;
    }

    protected function getChunk($tag)
    {
        if (array_key_exists($tag, $this->chunks)) {
            return $this->chunks[$tag];
        }
        return null;
    }

    protected function getChunkData($tag)
    {
        $chunk = $this->getChunk($tag);
        if (is_null($chunk)) {
            return null;
        }
        return $chunk->getData();
    }

    protected function parseSubChunks($data)
    {
        $pos = 0;
        $eos = strlen($data);
        while ($pos < $eos) {
            $chunkInfo = $this->parseChunk(substr($data, $pos));
            $this->chunks[$chunkInfo['id']] = $this->newChunk($chunkInfo);
            $pos += 8 + $chunkInfo['size'];
        }
    }

    abstract protected function newChunk(array $chunkInfo);
}

abstract class RIFFMutableListChunk extends RIFFListChunk
{
    protected function setChunk(RIFFChunk $chunk)
    {
        $tag = $chunk->getId();
        $oldChunk = $this->getChunk($tag);
        if (!is_null($oldChunk)) {
            $this->size -= 8 + $oldChunk->getSize();
        }
        $this->chunks[$tag] = $chunk;
        $this->size += 8 + $chunk->getSize();
    }

    protected function deleteChunk($tag)
    {
        $chunk = $this->getChunk($tag);
        if (!is_null($chunk)) {
            $this->length -= 8 + $chunk->getSize();
            unset($this->chunks[$tag]);
        }
    }
}

abstract class RIFF extends RIFFMutableListChunk
{
    const TAG_RIFF = 'RIFF';
    const TAG_ICMT = 'ICMT';
    const TAG_ICOP = 'ICOP';
    const TAG_IART = 'IART';
    const TAG_INAM = 'INAM';

    public static function createFromFile($filename)
    {
        $className = get_called_class();
        return new $className(file_get_contents($filename));
    }

    public function __construct($source)
    {
        parent::__construct($source);
        if ($this->id !== self::TAG_RIFF) {
            $this->raiseError();
        }
    }
}

class WebP extends RIFF
{
    const TAG_WEBP = 'WEBP';
    const TAG_VP8  = 'VP8 ';

    public static function createFromVP8Image($data)
    {
        $size = strlen($data);
        $webp = RIFF::TAG_RIFF
              . pack('V', $size + 12)
              . self::TAG_WEBP
              . self::TAG_VP8
              . pack('V', $size)
              . $data;
        return new WebP($webp);
    }

    public function __construct($source)
    {
        parent::__construct($source);
        if ($this->type !== self::TAG_WEBP) {
            $this->raiseError();
        }
        if (substr($this->data, 4, 4) !== self::TAG_VP8) {
            $this->raiseError();
        }
    }

    protected function newChunk(array $chunkInfo)
    {
        switch ($chunkInfo['id']) {
            case self::TAG_VP8;
                return new RIFFBinaryChunk($chunkInfo);
            case RIFF::TAG_ICMT:
            case RIFF::TAG_ICOP:
            case RIFF::TAG_IART:
            case RIFF::TAG_INAM:
                return new RIFFStringChunk($chunkInfo);
        }
        $this->raiseError();
    }

    public function getVP8Image()
    {
        return $this->getChunkData(RIFF::TAG_VP8);
    }

    public function getComment()
    {
        return $this->getChunkData(RIFF::TAG_ICMT);
    }

    public function getCopyright()
    {
        return $this->getChunkData(RIFF::TAG_ICOP);
    }

    public function getArtist()
    {
        return $this->getChunkData(RIFF::TAG_IART);
    }

    public function getTitle()
    {
        return $this->getChunkData(RIFF::TAG_INAM);
    }

    public function setComment($str)
    {
        $this->setMetadata(RIFF::TAG_ICMT, $str);
    }

    public function setCopyright($str)
    {
        $this->setMetadata(RIFF::TAG_ICOP, $str);
    }

    public function setArtist($str)
    {
        $this->setMetadata(RIFF::TAG_IART, $str);
    }

    public function setTitle($str)
    {
        $this->setMetadata(RIFF::TAG_INAM, $str);
    }

    public function clearMetadata()
    {
        $this->deleteChunk(RIFF::TAG_ICMT);
        $this->deleteChunk(RIFF::TAG_ICOP);
        $this->deleteChunk(RIFF::TAG_IART);
        $this->deleteChunk(RIFF::TAG_INAM);
    }

    private function setMetadata($tag, $str)
    {
        if (is_null($str)) {
            $this->deleteChunk($tag);
        } else {
            $this->setChunk(RIFFStringChunk::createFromString($tag, $str));
        }
    }
}

function webp_read_metadata($filename)
{
    $metadata = array();
    $webp = WebP::createFromFile($filename);
    foreach ($webp->getAllChunks() as $tag => $chunk) {
        if ($tag !== WebP::TAG_VP8) {
            $metadata[$tag] = $chunk->getData();
        }
    }
    return $metadata;
}
