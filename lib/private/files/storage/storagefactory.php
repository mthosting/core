<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Files\Storage;

use OCP\Files\Storage\IStorageFactory;

class StorageFactory implements IStorageFactory {
	/**
	 * @var callable[] $storageWrappers
	 */
	private $storageWrappers = array();

	/**
	 * [
	 *     $mountPoint => string[]
	 * ]
	 *
	 * @var array
	 */
	private $appliedWrappers = [];

	/**
	 * allow modifier storage behaviour by adding wrappers around storages
	 *
	 * $callback should be a function of type (string $mountPoint, Storage $storage) => Storage
	 *
	 * @param string $wrapperName
	 * @param callable $callback
	 */
	public function addStorageWrapper($wrapperName, $callback) {
		$this->storageWrappers[$wrapperName] = $callback;
	}

	/**
	 * Check if a storage wrapper is registered
	 *
	 * @param string $wrapperName
	 * @return bool
	 */
	public function isRegistered($wrapperName) {
		return isset($this->storageWrappers[$wrapperName]);
	}

	/**
	 * Create an instance of a storage and apply the registered storage wrappers
	 *
	 * @param string|boolean $mountPoint
	 * @param string $class
	 * @param array $arguments
	 * @return \OCP\Files\Storage
	 */
	public function getInstance($mountPoint, $class, $arguments) {
		return $this->wrap($mountPoint, new $class($arguments));
	}

	/**
	 * @param string|boolean $mountPoint
	 * @param \OCP\Files\Storage $storage
	 * @return \OCP\Files\Storage
	 */
	public function wrap($mountPoint, $storage) {
		if (!isset($this->appliedWrappers[$mountPoint])) {
			$this->appliedWrappers[$mountPoint] = [];
		}
		foreach ($this->storageWrappers as $wrapperName => $wrapper) {
			if (array_search($wrapperName, $this->appliedWrappers[$mountPoint]) === false) {
				$storage = $wrapper($mountPoint, $storage);
			}
			$this->appliedWrappers[$mountPoint][] = $wrapperName;
		}
		return $storage;
	}
}
