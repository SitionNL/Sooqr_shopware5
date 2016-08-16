<?php

namespace Shopware\SitionSooqr\Components;

class Gzip
{
	protected $destination;

	protected $handle = null;

	protected $mode;

	public function __construct($destination, $level = 9)
	{
		$this->destination = $destination;
		$this->setLevel($level);
	}

	public function __destruct()
	{
		$this->close();
	}

	public function isOpen()
	{
		return !is_null($this->handle);
	}

	public function endOfFile()
	{
		return $this->open() && gzeof($this->handle);
	}

	public function getMode()
	{
		return $this->mode;
	}

	public function getHandle()
	{
		return $this->handle;
	}

	public function open()
	{
		return $this->openRead();
	}

	public function openWrite()
	{
		$this->mode = 'write';
		$mode = 'wb' . $this->level;
		return $this->handle = gzopen($this->destination, $this->mode);
	}

	public function openRead()
	{
		$this->mode = 'read';
		$mode = 'rb';
		return $this->handle = gzopen($this->destination, $mode);
	}

	public function close()
	{
		gzclose($this->handle);
		$this->handle = null;
	}

	public function write($str)
	{
		if( !$this->isOpen() )
		{
			$this->openWrite();
		}

		if( $this->getMode() !== 'write' ) throw new Exception("Gzip file should be opened for writing");

		gzwrite($this->handle, $str);
	}

	public function read($length = 1048576)
	{
		if( !$this->isOpen() )
		{
			$this->openRead();
		}

		if( $this->getMode() !== 'read' ) throw new Exception("Gzip file should be opened for reading");
		return gzread($this->handle, $length);
	}

	public function setLevel($level)
	{
		if( !is_numeric($level) || $level < 1 || $level > 9 )
		{
			throw new Exception("Gzip compress level should be a number between 1 and 9");
		}

		$this->level = $level;
	}

	public static function fromFile($path, $dest = null, $level = 9, $chunkSize = 1048576)
	{
		if( is_null($dest) ) $dest = "{$path}.gz";

	    $mode = 'wb' . $level;

	    $return = false;

	    $gzip = new static($dest, $level);

	    if( $gzip->openWrite() !== false ) 
	    {
	        if( ($fileHandle = fopen($source, 'rb')) !== false ) 
	        {
	            while( !feof($fileHandle) ) 
	            {
	            	$gzip->write(fread($fileHandle, $chunkSize));
	            }

	            $return = $dest;

	            fclose($fileHandle);
	        }

	        $gzip->close();
	    }

	    return $return;
	}

	public static function toFile($path, $dest = null, $chunkSize = 1048576)
	{
		if( is_null($dest) ) 
		{
			$dest = (substr($path, -3) === '.gz') ? substr($path, 0, -3) : $path . ".original";
		}

		$return = false;

		$gzip = new static($path);

		if( $gzip->openRead() !== false )
		{
			if( ($fileHandle = fopen($source, 'wb')) !== false ) 
			{
	            while( !$gzip->endOfFile() )
	            {
	            	fwrite($fileHandle, $gzip->read($chunkSize));
	            }

	            $return = $dest;
			}
		}

		return $return;
	}
}