<?php
class RIFFException extends RuntimeException {}

/**
 * Base class of RIFF chunks
 */
abstract class RIFFChunk
{
    protected $id;
    protected $size;
    protected $data;

    public function __construct($source)
    {
        if (is_array($source)) {
            if (!array_key_exists('id', $source)
                || !array_key_exists('size', $source)
                || !array_key_exists('data', $source)
            ) {
                throw new InvalidArgumentException(
                    'Argument 1 must contain id, size and data'
                );
            }
            if (!is_string($source['id'])) {
                 throw new InvalidArgumentException('Chunk id must be a string');
            }
            if (!is_int($source['size'])) {
                throw new InvalidArgumentException('Chunk size must be an integer');
            }
            if (!is_string($source['data'])) {
                throw new InvalidArgumentException('Chunk data must be a string');
            }
            if (!$this->checkTag($source['id'])) {
                throw new RIFFException('Invalid chunk id');
            }
            if ($source['size'] !== strlen($source['data'])) {
                $this->raiseError('Chunk size does not equal to actual data size');
            }
            $chunkInfo = $source;
        } elseif (is_string($source)) {
            $chunkInfo = $this->parseChunk($source);
            if (8 + $chunkInfo['size'] !== strlen($source)) {
                $this->raiseError('Chunk size does not equal to actual data size');
            }
        } else {
            throw new InvalidArgumentException(sprintf(
                '%s::__construct() expects argument 1 to be a string or an array',
                get_class($this)
            ));
        }

        $this->id = $chunkInfo['id'];
        $this->size = $chunkInfo['size'];
        $this->data = $chunkInfo['data'];
    }

    public function getHashKey()
    {
        return $this->id;
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

    public function getRawData()
    {
        return $this->data;
    }

    public function dump()
    {
        return self::pack($this->id, $this->size, $this->getRawData());
    }

    public function dumpToFile($filename)
    {
        file_put_contents($filename, $this->dump());
    }

    protected function parseChunk($source)
    {
        $length = strlen($source);
        if ($length < 8) {
            $this->raiseError('Broken chunk');
        }

        $arr = unpack('V', substr($source, 4, 4));
        $size = $arr[1];
        if (8 + $size > $length) {
            $this->raiseError('Too short chunk data');
        }

        return array(
            'id' => substr($source, 0, 4),
            'size' => $size,
            'data' => substr($source, 8, $size),
        );
    }

    protected function checkTag($id)
    {
        if (strlen($id) === 4
            && preg_match('/^[0-9A-Za-z][0-9A-Za-z_ ]+$/', $id)
        ) {
            return true;
        }
        return false;
    }

    protected function raiseError($message, $code = 0)
    {
        throw new RIFFException($message, $code);
    }

    protected static function pack($id, $size, $data)
    {
        return $id . pack('V', $size) . $data;
    }
}

/**
 * Generic binary data chunk structure class
 */
class RIFFBinaryChunk extends RIFFChunk
{
    public static function createFromBinary($id, $data)
    {
        $size = strlen($data);
        return new static(compact('id', 'size', 'data'));
    }
}

/**
 * Generic null terminated string chunk structure class
 */
class RIFFStringChunk extends RIFFBinaryChunk
{
    public function __construct($source)
    {
        parent::__construct($source);
        if (strpos($this->data, chr(0)) !== strlen($this->data) - 1) {
            $this->raiseError('Data is not null terminated');
        }
    }

    public static function createFromString($id, $str)
    {
        return static::createFromBinary($id, $str . chr(0));
    }

    public function getData()
    {
        return substr($this->data, 0, -1);
    }
}

/**
 * Base class of RIFF chunks which can contain sub-chunks (immutable)
 */
abstract class RIFFListChunk extends RIFFChunk
{
    protected $chunks = array();

    public function __construct($source)
    {
        parent::__construct($source);
        $type = substr($this->data, 0, 4);
        if (!$this->checkTag($type)) {
            $this->raiseError('Invalid chunk type');
        }
        $this->parseSubChunks(substr($this->data, 4));
        $this->data = $type;
    }

    public function getHashKey()
    {
        return $this->id . '/' . $this->data;
    }

    public function getType()
    {
        return $this->data;
    }

    public function getData()
    {
        return $this->chunks;
    }

    public function getRawData()
    {
        $data = $this->data;
        foreach ($this->chunks as $chunk) {
            $data .= $chunk->dump();
        }
        return $data;
    }

    protected function getChunk($tag)
    {
        if (array_key_exists($tag, $this->chunks)) {
            return $this->chunks[$tag];
        }
        foreach ($this->chunks as $chunk) {
            if ($chunk instanceof RIFFListChunk && $chunk->getType() === $tag) {
                return $chunk;
            }
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
            $chunk = $this->newChunk($this->parseChunk(substr($data, $pos)));
            $this->chunks[$chunk->getHashKey()] = $chunk;
            $pos += 8 + $chunk->getSize();
        }
    }

    abstract protected function newChunk(array $chunkInfo);
}

/**
 * Base class of RIFF chunks which can contain sub-chunks (mutable)
 */
abstract class RIFFMutableListChunk extends RIFFListChunk
{
    protected function setChunk(RIFFChunk $chunk)
    {
        $key = $chunk->getHashKey();
        if (array_key_exists($key, $this->chunks)) {
            $this->size -= 8 + $this->chunks[$key]->getSize();
        }
        $this->chunks[$key] = $chunk;
        $this->size += 8 + $chunk->getSize();
    }

    protected function deleteChunk($tag)
    {
        $chunk = $this->getChunk($tag);
        if (!is_null($chunk)) {
            $this->length -= 8 + $chunk->getSize();
            unset($this->chunks[$chunk->getHashKey()]);
        }
    }
}

/**
 * Generic RIFF LIST chunk structure class
 */
class RIFFList extends RIFFMutableListChunk
{
    const TAG_LIST = 'LIST';

    public function __construct($source)
    {
        parent::__construct($source);
        if ($this->id !== self::TAG_LIST) {
            $this->raiseError(
                "Chunk id must be '" . self::TAG_LIST . "'"
            );
        }
    }

    protected function newChunk(array $chunkInfo)
    {
        return new RIFFBinaryChunk($chunkInfo);
    }
}

/**
 * RIFF INFO chunk structure class
 */
class RIFFInfo extends RIFFList
{
    const TAG_INFO = 'INFO';
    const TAG_IARL = 'IARL';
    const TAG_IART = 'IART';
    const TAG_ICMS = 'ICMS';
    const TAG_ICMT = 'ICMT';
    const TAG_ICOP = 'ICOP';
    const TAG_ICRD = 'ICRD';
    const TAG_ICRP = 'ICRP';
    const TAG_IDIM = 'IDIM';
    const TAG_IDPI = 'IDPI';
    const TAG_IENG = 'IENG';
    const TAG_IGNR = 'IGNR';
    const TAG_IKEY = 'IKEY';
    const TAG_ILGT = 'ILGT';
    const TAG_IMED = 'IMED';
    const TAG_INAM = 'INAM';
    const TAG_IPLT = 'IPLT';
    const TAG_IPRD = 'IPRD';
    const TAG_ISBJ = 'ISBJ';
    const TAG_ISFT = 'ISFT';
    const TAG_ISHP = 'ISHP';
    const TAG_ISRC = 'ISRC';
    const TAG_ISRF = 'ISRF';
    const TAG_ITCH = 'ITCH';

    public function __construct($source)
    {
        parent::__construct($source);
        if ($this->getType() !== self::TAG_INFO) {
            $this->raiseError(
                "Chunk type must be '" . self::TAG_INFO . "'"
            );
        }
    }

    protected function checkTag($id)
    {
        switch ($id) {
            case self::TAG_IARL:
            case self::TAG_IART:
            case self::TAG_ICMS:
            case self::TAG_ICMT:
            case self::TAG_ICOP:
            case self::TAG_ICRD:
            case self::TAG_ICRP:
            case self::TAG_IDIM:
            case self::TAG_IDPI:
            case self::TAG_IENG:
            case self::TAG_IGNR:
            case self::TAG_IKEY:
            case self::TAG_ILGT:
            case self::TAG_IMED:
            case self::TAG_INAM:
            case self::TAG_IPLT:
            case self::TAG_IPRD:
            case self::TAG_ISBJ:
            case self::TAG_ISFT:
            case self::TAG_ISHP:
            case self::TAG_ISRC:
            case self::TAG_ISRF:
            case self::TAG_ITCH:
                return true;
            default:
                return false;
        }
    }

    protected function newChunk(array $chunkInfo)
    {
        $id = $chunkInfo['id'];
        if ($this->checkTag($id)) {
            return new RIFFStringChunk($chunkInfo);
        }
        /*
        if (parent::checkTag($id)) {
            return new RIFFBinaryChunk($chunkInfo);
        }
        */
        $this->raiseError('Undefined tag for INFO chunk');
    }

    public function setInfo($tag, $str)
    {
        if (is_null($str)) {
            $this->deleteChunk($tag);
        } elseif ($this->checkTag($id)) {
            $this->setChunk(RIFFStringChunk::createFromString($tag, $str));
        /*
        } elseif (parent::checkTag($id)) {
            $this->setChunk(RIFFBinaryChunk::createFromBinary($tag, $str));
        */
        } else {
            $this->raiseError('Undefined tag for INFO chunk');
        }
    }
}

/**
 * Generic RIFF structure class
 */
class RIFF extends RIFFMutableListChunk
{
    const TAG_RIFF = 'RIFF';

    public static function createFromFile($filename)
    {
        return new static(file_get_contents($filename));
    }

    public function __construct($source)
    {
        parent::__construct($source);
        if ($this->id !== self::TAG_RIFF) {
            $this->raiseError(
                "Chunk id must be '" . self::TAG_RIFF . "'"
            );
        }
    }

    protected function newChunk(array $chunkInfo)
    {
        if ($chunkInfo['id'] === RIFFList::TAG_LIST) {
            if (strncmp($chunkInfo['data'], RIFFInfo::TAG_INFO, 4) === 0) {
                return new RIFFInfo($chunkInfo);
            } else {
                return new RIFFList($chunkInfo);
            }
        } else {
            return new RIFFBinaryChunk($chunkInfo);
        }
    }
}

/**
 * WebP image structure class
 */
class WebP extends RIFF
{
    const TAG_WEBP = 'WEBP';
    const TAG_VP8  = 'VP8 ';

    public static function createFromVP8Image($data)
    {
        $size = strlen($data);
        $webp = self::pack(self::TAG_RIFF, $size + 12, self::TAG_WEBP)
              . self::pack(self::TAG_VP8, $size, $data);
        return new WebP($webp);
    }

    public function __construct($source)
    {
        parent::__construct($source);
        if ($this->getType() !== self::TAG_WEBP) {
            $this->raiseError(
                "Chunk type must be '" . self::TAG_WEBP . "'"
            );
        }
        reset($this->chunks);
        if (key($this->chunks) !== self::TAG_VP8) {
            $this->raiseError(
                "First sub chunk must be VP8 image data'"
            );
        }
    }

    protected function newChunk(array $chunkInfo)
    {
        switch ($chunkInfo['id']) {
            case self::TAG_VP8;
                return new RIFFBinaryChunk($chunkInfo);
            case RIFFInfo::TAG_ICMT:
            case RIFFInfo::TAG_ICOP:
            case RIFFInfo::TAG_IART:
            case RIFFInfo::TAG_INAM:
                return new RIFFStringChunk($chunkInfo);
        }
        $this->raiseError('Unsupported tag');
    }

    public function getVP8Image()
    {
        return $this->getChunkData(self::TAG_VP8);
    }

    public function getComment()
    {
        return $this->getChunkData(RIFFInfo::TAG_ICMT);
    }

    public function getCopyright()
    {
        return $this->getChunkData(RIFFInfo::TAG_ICOP);
    }

    public function getArtist()
    {
        return $this->getChunkData(RIFFInfo::TAG_IART);
    }

    public function getTitle()
    {
        return $this->getChunkData(RIFFInfo::TAG_INAM);
    }

    public function setComment($str)
    {
        $this->_setMetadata(RIFFInfo::TAG_ICMT, $str);
    }

    public function setCopyright($str)
    {
        $this->_setMetadata(RIFFInfo::TAG_ICOP, $str);
    }

    public function setArtist($str)
    {
        $this->_setMetadata(RIFFInfo::TAG_IART, $str);
    }

    public function setTitle($str)
    {
        $this->_setMetadata(RIFFInfo::TAG_INAM, $str);
    }

    public function getMetadata()
    {
        $metadata = array();
        foreach ($this->getData() as $tag => $chunk) {
            if ($tag !== self::TAG_VP8) {
                $metadata[$tag] = $chunk->getData();
            }
        }
        return $metadata;
    }

    public function clearMetadata()
    {
        $this->deleteChunk(RIFFInfo::TAG_ICMT);
        $this->deleteChunk(RIFFInfo::TAG_ICOP);
        $this->deleteChunk(RIFFInfo::TAG_IART);
        $this->deleteChunk(RIFFInfo::TAG_INAM);
    }

    private function _setMetadata($tag, $str)
    {
        if (is_null($str)) {
            $this->deleteChunk($tag);
        } else {
            $this->setChunk(RIFFStringChunk::createFromString($tag, $str));
        }
    }
}
