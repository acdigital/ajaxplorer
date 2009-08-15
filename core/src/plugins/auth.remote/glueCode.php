<?php
/**
 * @package info.ajaxplorer
 * 
 * Copyright 2007-2009 Cyril Russo
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
 * Description : Abstract representation of an access to an authentication system (ajxp, ldap, etc).
 */
 
/** Take care when using this file. It can't be included anywhere, as it's doing global scope pollution.
  *    Typically, this is used as glue code from your CMS frontend and AJXP code. 
  *    This example file switches sessions (close CMS session, open AJXP session, modify AJXP's 
  *    session value so the users actions are performed as if they were done locally by AJXP, and then 
  *    reopen your CMS session).
  *    This is typically used by Wordpress as the plugin mechanism is hook based.
  *
  *    The idea is: this script is require()'d by the CMS script. 
  *
  *
  *
  *
  *
  *
  *
  *
  */
  
// Only /index.php can include us
if (basename(__FILE__) != basename($_SERVER['SCRIPT_FILENAME']) && $_SERVER['PHP_SELF'] == '/index.php')
{
    if (!$CURRENTPATH) $CURRENTPATH=dirname(__FILE__);
    require_once("$CURRENTPATH/../../server/classes/class.AJXP_Logger.php");
//    require_once();
    require_once("$CURRENTPATH/../../server/classes/class.AbstractDriver.php");
    require_once("$CURRENTPATH/../../server/classes/class.Utils.php");
    require_once("$CURRENTPATH/../../server/classes/class.Repository.php");
//    include_once("$CURRENTPATH/class.remoteAuthDriver.php");
    if (!class_exists("SessionSwitcher")) require_once("$CURRENTPATH/sessionSwitcher.php");
    require_once("$CURRENTPATH/../../server/classes/class.ConfService.php");
    require_once("$CURRENTPATH/../../server/classes/class.AuthService.php");
    define ("CLIENT_RESOURCES_FOLDER", "$CURRENTPATH/../../client");
    ConfService::init("$CURRENTPATH/../../server/conf/conf.php"); 
    require_once("$CURRENTPATH/../../plugins/conf.serial/class.AJXP_User.php");
    
    global $plugInAction;
    switch($plugInAction)
    {
    case 'login':
        global $login, $result;
        if (is_array($login))
        {
            $newSession = new SessionSwitcher("AjaXplorer");
            $result = AuthService::logUser($login["name"], $login["password"], true) == 1;
        }
        break;
    case 'logout':
        global $result;
        $newSession = new SessionSwitcher("AjaXplorer");
        global $_SESSION;
        $_SESSION = array();
        $result = TRUE;
        break;
    case 'addUser':
        global $user, $result;
        if (is_array($user))
        {
            $newSession = new SessionSwitcher("AjaXplorer");
            AuthService::createUser($user["name"], $user["password"], false);
            $result = TRUE;
        }
        break;
    case 'delUser':
        global $userName, $result;
        if (strlen($userName))
        {
            $newSession = new SessionSwitcher("AjaXplorer");
            AuthService::deleteUser($userName);
            $result = TRUE;
        }
        break;
    case 'updateUser':
        global $user, $result;
        if (is_array($user))
        {
            $newSession = new SessionSwitcher("AjaXplorer");
            if (AuthService::updatePassword($user["name"], $user["password"]))
            {
                //@TODO Change this to match your CMS code
                if ($user["right"] == "admin")
                {
                    $userObj = getLoggedUser();
                    if ($user["name"] == $userObj->getId())
                        AuthService::updateAdminRights($userObj);
                }
                $result = TRUE;
            }
            else $result = FALSE;
        }
        break;
    case 'installDB':
        global $user, $reset, $result;
        $result = TRUE;
        break;            
    default:
        global $result;
        $result = FALSE;
    }
}  
  
  
?>