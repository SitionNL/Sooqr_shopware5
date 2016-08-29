<?php

namespace Shopware\SitionSooqr\Components;

class Locking
{
	// path to lockfile
	protected $path;

	// handle of lock file
	protected $handle = null;

	public function __construct($path)
	{
		$this->path = $path;

		// make sure lock file exists
		$this->createFile();
	}

	public function createFile()
	{
		return touch($this->path);
	}

	public function tryGetLock()
	{
		$this->handle = fopen($this->path, "w+");

		if( flock($this->handle, LOCK_EX | LOCK_NB) ) // do an exclusive lock
		{
			return true;
		}
		else
		{
			fclose($this->handle);
			$this->handle = null;
			return false;
		}
	}

	public function removeLock()
	{
		flock($this->handle, LOCK_UN); // release the lock
		fclose($this->handle);
		$this->handle = null;
	}

	public function hasLock()
	{
		return !is_null($this->handle);
	}

	/**
	 * @param  integer $wait    Sleep time in seconds between locking attempts
	 * @param  integer $timeout Total time before timing out
	 * @return bool             Returns when a lock is acquired
	 */
	public function waitTillAcquired($wait = 30, $timeout = 3600)
	{
		$total = 0;

		while( !$this->tryGetLock() )
		{
			sleep($wait);

			if( !is_null($timeout) )
			{
				$total += $wait;
				if( $total > $timeout ) return false;
			}
		}

		return true;
	}

	public function doActions(callable $cb, $wait = 30, $timeout = null)
	{
		$this->waitTillAcquired($wait, $timeout);

		call_user_func($cb);

		$this->removeLock();
	}
}