<?php
/**
 * @author Frank Karlitschek
 * @copyright 2012 Frank Karlitschek frank@owncloud.org
 * @copyright 2014 Lukas Reschke lukas@owncloud.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\user_webdavauth;

use OC\HTTPHelper;
use OCP\IConfig;
use OCP\IDb;
use OCP\ILogger;
use OCP\IUserManager;

class USER_WEBDAVAUTH implements \OCP\UserInterface {
	/** @var string */
	private $tableName = '*PREFIX*webdav_user_mapping';
	/** @var IConfig */
	private $config;
	/** @var IDb */
	private $db;
	/** @var HTTPHelper */
	private $httpHelper;
	/** @var ILogger */
	private $logger;
	/** @var IUserManager */
	private $userManager;
	/** @var string */
	private $serverRoot;

	/**
	 * @param IConfig $config
	 * @param IDb $db
	 * @param HTTPHelper $httpHelper
	 * @param ILogger $logger
	 * @param IUserManager $userManager
	 * @param string $serverRoot
	 */
	public function __construct(IConfig $config,
								IDb $db,
								HTTPHelper $httpHelper,
								ILogger $logger,
								IUserManager $userManager,
								$serverRoot) {
		$this->config = $config;
		$this->db = $db;
		$this->httpHelper = $httpHelper;
		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->serverRoot = $serverRoot;
	}

	/**
	 * Check if backend implements actions
	 *
	 * @param int $actions bitwise-or'ed actions
	 * @return boolean
	 */
	public function implementsActions($actions) {
		return (bool)((\OC_User_Backend::CHECK_PASSWORD
				| \OC_User_Backend::GET_DISPLAYNAME
				| \OC_User_Backend::SET_DISPLAYNAME
				| \OC_User_Backend::COUNT_USERS
				| \OC_User_Backend::GET_HOME)
			& $actions);
	}

	/**
	 * Builds the auth url from the specified endpoint
	 *
	 * @param string $endPointUrl
	 * @param string $uid
	 * @param string $password
	 * @return string|false
	 */
	private function createAuthUrl($endPointUrl, $uid, $password) {
		$arr = explode('://', strtolower($endPointUrl), 2);
		if(empty($arr) || count($arr) !== 2) {
			return false;
		}
		list($webdavauth_protocol, $webdavauth_url_path) = $arr;

		if($webdavauth_protocol !== 'http' && $webdavauth_protocol !== 'https') {
			return false;
		}

		return $webdavauth_protocol.'://'.urlencode($uid).':'.urlencode($password).'@'.$webdavauth_url_path;
	}

	/**
	 * Check if the password is correct
	 *
	 * @param string $uid The username
	 * @param string $password The password
	 * @return boolean|false
	 */
	public function checkPassword($uid, $password) {
		$endPointUrl = $this->config->getSystemValue('user_webdavauth_url');
		$headers = $this->httpHelper->getHeaders($this->createAuthUrl($endPointUrl, $uid, $password));
		if($headers === false) {
			$this->logger->error('Not possible to connect to WebDAV endpoint: ' . $endPointUrl,
				array('app' => 'user_webdavauth'));
			return false;
		}

		$uid = strtolower($uid);

		$returnCode = substr($headers[0], 9, 3);
		if(substr($returnCode, 0, 1) === '2') {
			// If user already exists in another backend don't login
			$this->userManager->removeBackend($this);
			if($this->userManager->userExists($uid)) {
				return false;
			}

			// If the user does not exists in this backend create it
			if(!$this->userExists($uid)) {
				$query = $this->db->prepareQuery('INSERT INTO `'.$this->tableName.'` (`uid`) VALUES (?)');
				$query->bindValue(1, $uid);
				$query->execute();
			}

			return $uid;
		}

		return false;
	}

	/**
	 * get the user's home directory
	 *
	 * @param string $uid the username
	 * @return string|false
	 */
	public function getHome($uid) {
		if ($this->userExists($uid)) {
			return $this->config->getSystemValue('datadirectory', $this->serverRoot . '/data') . '/' . $uid;
		}

		return false;
	}

	/**
	 * Set display name
	 *
	 * @param string $uid The username
	 * @param string $displayName The new display name
	 * @return bool
	 */
	public function setDisplayName($uid, $displayName) {
		if ($this->userExists($uid)) {
			$query = $this->db->prepareQuery('UPDATE `'.$this->tableName.'` ' .
				'SET `displayname` = ? WHERE LOWER(`uid`) = LOWER(?)');
			$query->bindValue(1, $displayName);
			$query->bindValue(2, $uid);
			$query->execute();
			return true;
		}

		return false;
	}

	/**
	 * Counts the users
	 *
	 * @return int
	 */
	public function countUsers() {
		$query = $this->db->prepareQuery('SELECT COUNT(*) FROM `'.$this->tableName.'`');
		$result = $query->execute();
		return $result->fetchOne();
	}

	/**
	 * delete a user - not implemented within user_webdavauth
	 * FIXME: That should be an optional action, but unfortunately it isn't. - Thus we always return false.
	 *
	 * @param string $uid The username of the user to delete
	 * @return bool
	 */
	public function deleteUser($uid) {
		return false;
	}

	/**
	 * Get a list of all users
	 *
	 * @param string $search
	 * @param null $limit
	 * @param null $offset
	 * @return array All UIDs
	 * @throws \OC\DatabaseException
	 */
	public function getUsers($search = '', $limit = null, $offset = null) {
		$users = array();

		$query = $this->db->prepareQuery('SELECT `uid` FROM `'.$this->tableName.'`'
			. ' WHERE LOWER(`uid`) LIKE LOWER(?) ORDER BY `uid` ASC', $limit, $offset);
		$query->bindValue(1, '%'.$search.'%');
		$query->bindValue(2, '%'.$search.'%');
		$result = $query->execute();
		while($row = $result->fetchRow()) {
			$users[] = $row['uid'];
		}
		return $users;
	}

	/**
	 * check if a user exists
	 *
	 * @param string $uid the username
	 * @return boolean
	 */
	public function userExists($uid) {
		$query = $this->db->prepareQuery('SELECT COUNT(*) FROM `'.$this->tableName.'`'
			. ' WHERE LOWER(`uid`) = LOWER(?)');
		$query->bindValue(1, $uid);
		$result = $query->execute();
		$existing = $result->fetchOne();
		if($existing === '1') {
			return true;
		}

		return false;
	}

	/**
	 * get display name of the user
	 *
	 * @param string $uid user ID of the user
	 * @return string display name
	 */
	public function getDisplayName($uid) {
		$query = $this->db->prepareQuery('SELECT `displayname` FROM `'.$this->tableName.'`'
			. ' WHERE LOWER(`uid`) = LOWER(?)');
		$query->bindValue(1, $uid);
		$result = $query->execute();
		return $result->fetchOne();
	}

	/**
	 * Get a list of all display names
	 *
	 * @param string $search
	 * @param null $limit
	 * @param null $offset
	 * @return array Array of displaynames (value) and the corresponding UIDs (key)
	 * @throws \OC\DatabaseException
	 */
	public function getDisplayNames($search = '', $limit = null, $offset = null) {
		$users = array();

		$query = $this->db->prepareQuery('SELECT `uid`, `displayname` FROM `'.$this->tableName.'`'
			. ' WHERE LOWER(`displayname`) LIKE LOWER(?) OR '
			. 'LOWER(`uid`) LIKE LOWER(?) ORDER BY `uid` ASC', $limit, $offset);
		$query->bindValue(1, '%'.$search.'%');
		$query->bindValue(2, '%'.$search.'%');
		$result = $query->execute();
		while($row = $result->fetchRow()) {
			$users[$row['uid']] = $row['displayname'];
		}

		return $users;
	}

	/**
	 * Check if a user list is available or not
	 *
	 * @return boolean if users can be listed or not
	 */
	public function hasUserListings() {
		return true;
	}
}
