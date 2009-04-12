<?php
/**
 * @package info.ajaxplorer
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : Users management for authentification.
 */
class AuthService
{
	function usersEnabled()
	{
		return ENABLE_USERS;
	}
	
	function changePasswordEnabled()
	{
		return (AUTH_MODE == "ajaxplorer");
	}
	
	function generateSeed(){
		$seed = md5(time());
		$_SESSION["AJXP_CURRENT_SEED"] = $seed;
		return $seed;
	}
	
	function encodeCookiePass($user, $pass = null){
		if($pass == null){
			$authDriver = ConfService::getAuthDriverImpl();
			$users = $authDriver->listUsers();
			$pass = $users[$user];
		}
		return md5($user.":".$pass.":ajxp");
	}
		
	function getLoggedUser()
	{
		if(isSet($_SESSION["AJXP_USER"])) return $_SESSION["AJXP_USER"];
		return null;
	}
	
	function preLogUser($remoteSessionId = "")
	{
		if(AuthService::getLoggedUser() != null) return ;
		$authDriver = ConfService::getAuthDriverImpl();
		$authDriver->preLogUser($remoteSessionId);
		return ;
		/*
		// TODO : CREATE APPROPRIATE AUTH DRIVERS
		if(AUTH_MODE == "local_http")
		{
			$localHttpLogin = $_SERVER["REMOTE_USER"];
			if(isSet($localHttpLogin) && AuthService::userExists($localHttpLogin))
			{
				AuthService::logUser($localHttpLogin, "", true);
			}
		}
		else if(AUTH_MODE == "remote" && $remoteSessionId != "")
		{
			require_once("class.HttpClient.php");
			$client = new HttpClient(AUTH_MODE_REMOTE_SERVER, AUTH_MODE_REMOTE_PORT);
			$client->setDebug(false);
			if(AUTH_MODE_REMOTE_USER != ""){
				$client->setAuthorization(AUTH_MODE_REMOTE_USER, AUTH_MODE_REMOTE_PASSWORD);
			}						
			$client->setCookies(array((AUTH_MODE_REMOTE_SESSION_NAME ? AUTH_MODE_REMOTE_SESSION_NAME : "PHPSESSID") => $remoteSessionId));
			$result = $client->get(AUTH_MODE_REMOTE_URL, array("session_id"=>$remoteSessionId));			
			if($result)
			{
				$user = $client->getContent();
				if(AuthService::userExists($user)) AuthService::logUser($user, "", true);
			}
		}
		else if(AUTH_MODE == "wordpress"){
			global $current_user;
			wp_get_current_user();
			if($current_user->user_login == '' || $current_user->wp_user_level < 8 || !function_exists('ajxp_content')){
				die("You are not allowed to see this page!");
			}
			AuthService::logUser($current_user->user_login, "", true);
		}
		*/
	}
	
	function logUser($user_id, $pwd, $bypass_pwd = false, $cookieLogin = false, $returnSeed="")
	{
		$authDriver = ConfService::getAuthDriverImpl();
		if($user_id == null)
		{
			if(isSet($_SESSION["AJXP_USER"])) return 1; 
			if(ALLOW_GUEST_BROWSING)
			{
				if(!$authDriver->userExists("guest"))
				{
					AuthService::createUser("guest", "");
					$guest = new AJXP_User("guest");
					$guest->save();
				}
				AuthService::logUser("guest", null);
				return 1;
			}
			return 0;
		}
		// CHECK USER PASSWORD HERE!
		if(!$authDriver->userExists($user_id)) return 0;
		if(!$bypass_pwd){
			if(!AuthService::checkPassword($user_id, $pwd, $cookieLogin, $returnSeed)){
				return -1;
			}
		}
		$user = new AJXP_User($user_id);
		if($user->isAdmin())
		{
			$user = AuthService::updateAdminRights($user);
		}
		$_SESSION["AJXP_USER"] = $user;
		AJXP_Logger::logAction("Log In");
		return 1;
	}
	
	function updateUser($userObject)
	{
		$_SESSION["AJXP_USER"] = $userObject;
	}
	
	function disconnect()
	{
		if(isSet($_SESSION["AJXP_USER"])){
			AJXP_Logger::logAction("Log Out");
			unset($_SESSION["AJXP_USER"]);
		}
	}
	
	function getDefaultRootId()
	{
		$loggedUser = AuthService::getLoggedUser();
		if($loggedUser == null) return 0;
		foreach (array_keys(ConfService::getRootDirsList()) as $rootDirIndex)
		{			
			if($loggedUser->canRead($rootDirIndex."")) return $rootDirIndex;
		}
		return 0;
	}
	
	/**
	* @param AJXP_User $adminUser
	*/
	function updateAdminRights($adminUser)
	{
		foreach (array_keys(ConfService::getRootDirsList()) as $rootDirIndex)
		{			
			$adminUser->setRight($rootDirIndex, "rw");
		}
		$adminUser->save();
		return $adminUser;
	}
	
	function userExists($userId)
	{
		$authDriver = ConfService::getAuthDriverImpl();
		return $authDriver->userExists($userId);
	}
	
	function encodePassword($pass){
		return md5($pass);
	}
	
	function checkPassword($userId, $userPass, $encodedPass = false, $returnSeed = "")
	{
		if($userId == "guest") return true;		
		$authDriver = ConfService::getAuthDriverImpl();
		return $authDriver->checkPassword($userId, $userPass, $encodedPass, $returnSeed);
	}
	
	function updatePassword($userId, $userPass)
	{
		$authDriver = ConfService::getAuthDriverImpl();
		$authDriver->changePassword($userId, $userPass);
		AJXP_Logger::logAction("Update Password", array("user_id"=>$userId));
		return true;
	}
	
	function createUser($userId, $userPass, $isAdmin=false)
	{
		$authDriver = ConfService::getAuthDriverImpl();
		$authDriver->createUser($userId, AuthService::encodePassword($userPass));
		if($isAdmin){
			$user = new AJXP_User($userId);
			$user->setAdmin(true);			
			$user->save();
		}
		AJXP_Logger::logAction("Create User", array("user_id"=>$userId));
		return null;
	}
	
	function countAdminUsers(){
		$auth = ConfService::getAuthDriverImpl();	
		$count = 0;
		$users = $auth->listUsers();
		foreach (array_keys($users) as $userId){
			$userObject = new AJXP_User($userId);
			$userObject->load();			
			if($userObject->isAdmin()) $count++;
		}
		if(!$count && $auth->userExists("admin")){
			return -1;
		}		
		return $count;
	}
		
	function deleteUser($userId)
	{
		$authDriver = ConfService::getAuthDriverImpl();
		$confDriver = ConfService::getConfStorageImpl();
		$authDriver->deleteUser($userId);
		$confDriver->deleteUser($userId);
		
		AJXP_Logger::logAction("Delete User", array("user_id"=>$userId));
		return true;
	}
	
	function listUsers()
	{
		$authDriver = ConfService::getAuthDriverImpl();		
		$allUsers = array();
		$users = $authDriver->listUsers();
		foreach (array_keys($users) as $userId)
		{
			if(($userId == "guest" && !ALLOW_GUEST_BROWSING) || $userId == "ajxp.admin.users") continue;
			$allUsers[$userId] = new AJXP_User($userId);
		}
		return $allUsers;
	}
	
}

?>
