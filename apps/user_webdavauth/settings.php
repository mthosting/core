<?php
/**
 * ownCloud - user_webdavauth
 *
 * @author Frank Karlitschek
 * @copyright 2012 Frank Karlitschek frank@owncloud.org
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

OC_Util::checkAdminUser();

if($_POST) {
	// CSRF check
	OCP\JSON::callCheck();

	if(isset($_POST['webdav_url'])) {
		\OC::$server->getConfig()->setSystemValue('user_webdavauth_url', $_POST['webdav_url']);
	}
}

// fill template
$tmpl = new OC_Template('user_webdavauth', 'settings');
$tmpl->assign('webdav_url', \OC::$server->getConfig()->getSystemValue('user_webdavauth_url'));

return $tmpl->fetchPage();
