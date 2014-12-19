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

namespace OCA\user_webdavauth\AppInfo;

use OCA\user_webdavauth\USER_WEBDAVAUTH;
use OCP\Util;

$userBackend  = new USER_WEBDAVAUTH(
	\OC::$server->getConfig(),
	\OC::$server->getDb(),
	\OC::$server->getHTTPHelper(),
	\OC::$server->getLogger(),
	\OC::$server->getUserManager(),
	\OC::$server->getServerRoot()
);
\OC_User::useBackend($userBackend);

Util::addTranslations('user_webdavauth');
\OC_APP::registerAdmin('user_webdavauth', 'settings');
