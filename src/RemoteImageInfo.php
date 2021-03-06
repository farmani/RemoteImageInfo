<?php
namespace RemoteImageInfo;
/**
 * RemoteImageInfo - Because sometimes you just want the image size!
 * Based on the Ruby Implementation by Steven Sykes (https://github.com/sdsykes/fastimage)
 *
 * Copyright (c) 2015 Ramin Farmani
 * Ramin Farmani, http://farmani.ir
 *
 * MIT Licensed
 * @version 0.1
 */
class RemoteImageInfo
{
    private $strpos = 0;
    private $str;
    private $type;
    private $handle;

    public function __construct($uri = null)
    {
        if ($uri) $this->load($uri);
    }

    /**
     * @param $uri
     */
    public function load($uri)
    {
        if ($this->handle) $this->close();

        try {
            $this->handle = @fopen($uri, 'r');
            if (!$this->handle) {
                echo "File open failed.\n";
            }
        } catch (\Exception $e) {
            echo "File open failed.\n";
        }
    }

    /**
     * @return bool
     */
    public function checkLoad()
    {
        return boolval($this->handle);
    }

    /**
     *
     */
    public function close()
    {
        if ($this->handle) {
            fclose($this->handle);
            $this->handle = null;
            $this->type = null;
            $this->str = null;
        }
    }

    /**
     * @return array|bool
     */
    public function getSize()
    {
        $this->strpos = 0;
        if ($this->getType()) {
            return array_values($this->parseSize());
        }

        return false;
    }

    /**
     * @return bool|string
     */
    public function getType()
    {
        $this->strpos = 0;

        if (!$this->type) {
            switch ($this->getChars(2)) {
                case "BM":
                    return $this->type = 'bmp';
                case "GI":
                    return $this->type = 'gif';
                case chr(0xFF) . chr(0xd8):
                    return $this->type = 'jpeg';
                case chr(0x89) . 'P':
                    return $this->type = 'png';
                default:
                    return false;
            }
        }
        return $this->type;
    }

    /**
     * @return array|null
     */
    private function parseSize()
    {
        $this->strpos = 0;

        switch ($this->type) {
            case 'png':
                return $this->parseSizeForPNG();
            case 'gif':
                return $this->parseSizeForGIF();
            case 'bmp':
                return $this->parseSizeForBMP();
            case 'jpeg':
                return $this->parseSizeForJPEG();
        }

        return null;
    }

    /**
     * @return array
     */
    private function parseSizeForPNG()
    {
        $chars = $this->getChars(25);
        return unpack("N*", substr($chars, 16, 8));
    }

    /**
     * @return array
     */
    private function parseSizeForGIF()
    {
        $chars = $this->getChars(11);
        return unpack("S*", substr($chars, 6, 4));
    }

    /**
     * @return array
     */
    private function parseSizeForBMP()
    {
        $chars = $this->getChars(29);
        $chars = substr($chars, 14, 14);
        $type = unpack('C', $chars);

        return (reset($type) == 40) ? unpack('L*', substr($chars, 4)) : unpack('L*', substr($chars, 4, 8));
    }

    /**
     * @return array
     */
    private function parseSizeForJPEG()
    {
        $state = null;
        $i = 0;
        while (true) {
            switch ($state) {
                default:
                    $this->getChars(2);
                    $state = 'started';
                    break;

                case 'started':
                    $b = $this->getByte();
                    if ($b === false) return false;

                    $state = $b == 0xFF ? 'sof' : 'started';
                    break;

                case 'sof':
                    $b = $this->getByte();
                    if (in_array($b, range(0xe0, 0xef))) {
                        $state = 'skipframe';
                    } elseif (in_array($b, array_merge(range(0xC0, 0xC3), range(0xC5, 0xC7), range(0xC9, 0xCB), range(0xCD, 0xCF)))) {
                        $state = 'readsize';
                    } elseif ($b == 0xFF) {
                        $state = 'sof';
                    } else {
                        $state = 'skipframe';
                    }
                    break;

                case 'skipframe':
                    $skip = $this->readInt($this->getChars(2)) - 2;
                    $state = 'doskip';
                    break;

                case 'doskip':
                    if($skip == 0) {
                        return array(0,0);
                    }
                    $this->getChars($skip);
                    $state = 'started';
                    break;

                case 'readsize':
                    $c = $this->getChars(7);

                    return array($this->readInt(substr($c, 5, 2)), $this->readInt(substr($c, 3, 2)));
            }
        }
    }

    /**
     * @param $n
     * @return bool|string
     */
    private function getChars($n)
    {
        $response = null;

        // do we need more data?
        if ($this->strpos + $n - 1 >= strlen($this->str)) {
            $end = ($this->strpos + $n);
            while (strlen($this->str) < $end && $response !== false) {
                // read more from the file handle
                $need = $end - ftell($this->handle);
                if($need == 0) return false;
                if ($response = fread($this->handle, $need)) {
                    $this->str .= $response;
                } else {
                    return false;
                }
            }
        }

        $result = substr($this->str, $this->strpos, $n);
        $this->strpos += $n;

        return $result;
    }

    /**
     * @return bool|mixed
     */
    private function getByte()
    {
        $c = $this->getChars(1);
        if($c === false) return false;
        $b = unpack("C", $c);

        if($b === false) return false;
        return reset($b);
    }

    /**
     * @param $str
     * @return int
     */
    private function readInt($str)
    {
        $size = unpack("C*", $str);

        if(isset($size[1]) && isset($size[2]))
            return ($size[1] << 8) + $size[2];
        return 0;
    }

    /**
     * 
     */
    public function __destruct()
    {
        $this->close();
    }
}
