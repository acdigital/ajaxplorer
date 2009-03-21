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
 * Description : Main configurations parsing.
 */
global $G_DEFAULT_REPOSITORIES;

global $G_LANGUE;
global $G_AVAILABLE_LANG;
global $G_MESSAGES;
global $G_REPOSITORIES;
global $G_REPOSITORY;
global $G_USE_HTTPS;
global $G_WM_EMAIL;
global $G_MAX_CHAR;
global $G_UPLOAD_MAX_NUMBER;
global $G_UPLOAD_MAX_FILE;
global $G_UPLOAD_MAX_TOTAL;
global $G_ACCESS_DRIVER;


class ConfService
{
	function init($confFile)
	{
		include_once($confFile);
		// INIT AS GLOBAL
		global $G_LANGUE, $G_AVAILABLE_LANG, $G_REPOSITORIES, $G_REPOSITORY, $G_USE_HTTPS,$G_WM_EMAIL,$G_MAX_CHAR, $G_UPLOAD_MAX_NUMBER, $G_UPLOAD_MAX_FILE, $G_UPLOAD_MAX_TOTAL, $G_DEFAULT_REPOSITORIES;
		if(!isset($langue) || $langue=="") {$langue=$default_language;}
		$G_LANGUE = $langue;
		if(isSet($available_languages)){
			$G_AVAILABLE_LANG = $available_languages;
		}else{
			$G_AVAILABLE_LANG = ConfService::listAvailableLanguages();
		}
		$G_USE_HTTPS = $use_https;
		$G_WM_EMAIL = $webmaster_email;
		$G_MAX_CHAR = $max_caracteres;
		$G_UPLOAD_MAX_NUMBER = $upload_max_number;
		$G_UPLOAD_MAX_FILE = Utils::convertBytes($upload_max_size_per_file);
		$G_UPLOAD_MAX_TOTAL = Utils::convertBytes($upload_max_size_total);
		$G_DEFAULT_REPOSITORIES = $REPOSITORIES;
		$G_REPOSITORIES = ConfService::initRepositoriesList($G_DEFAULT_REPOSITORIES);
		ConfService::switchRootDir();
	}

	function switchRootDir($rootDirIndex=-1)
	{
		global $G_REPOSITORY, $G_REPOSITORIES, $G_ACCESS_DRIVER;
		if($rootDirIndex == -1){
			if(isSet($_SESSION['REPO_ID']) && array_key_exists($_SESSION['REPO_ID'], $G_REPOSITORIES))
			{			
				$G_REPOSITORY = $G_REPOSITORIES[$_SESSION['REPO_ID']];
			}
			else 
			{
				$keys = array_keys($G_REPOSITORIES);
				$G_REPOSITORY = $G_REPOSITORIES[$keys[0]];
				$_SESSION['REPO_ID'] = $keys[0];
			}
		}
		else 
		{
			$G_REPOSITORY = $G_REPOSITORIES[$rootDirIndex];			
			$_SESSION['REPO_ID'] = $rootDirIndex;
			if(isSet($G_ACCESS_DRIVER)) unset($G_ACCESS_DRIVER);
		}
		
	}
	
	function getRootDirsList()
	{
		global $G_REPOSITORIES;
		return $G_REPOSITORIES;
	}
	
	function getCurrentRootDirIndex()
	{
		global $G_REPOSITORIES;
		if(isSet($_SESSION['REPO_ID']) &&  isSet($G_REPOSITORIES[$_SESSION['REPO_ID']]))
		{
			return $_SESSION['REPO_ID'];
		}
		$keys = array_keys($G_REPOSITORIES);
		return $keys[0];
	}
	
	function getCurrentRootDirDisplay()
	{
		global $G_REPOSITORY;
		return $G_REPOSITORY->getDisplay();
	}
	
	/**
	 * @param array $repositories
	 * @return array
	 */
	function initRepositoriesList($defaultRepositories)
	{
		$objList =  array();
		foreach($defaultRepositories as $index=>$repository)
		{
			$repo = ConfService::createRepositoryFromArray($index, $repository);
			$repo->setWriteable(false);
			$objList[$index] = $repo;
		}
		$confRepo = ConfService::loadRepoFile();
		$upgrade = false;
		foreach ($confRepo as $index => $repo){
			if($repo->upgradeId()){
				$confRepo[$index] = $repo;
				$upgrade = true;
			}
			$repo->setWriteable(true);
			$objList[$repo->getUniqueId()] = $repo;
		}
		if($upgrade) ConfService::saveRepoFile($confRepo);
		return $objList;
	}
	
	/**
	 * Create a repository object from a config options array
	 *
	 * @param integer $index
	 * @param Array $repository
	 * @return Repository
	 */
	function createRepositoryFromArray($index, $repository){
		$repo = new Repository($index, $repository["DISPLAY"], $repository["DRIVER"]);
		if(array_key_exists("DRIVER_OPTIONS", $repository) && is_array($repository["DRIVER_OPTIONS"])){
			foreach ($repository["DRIVER_OPTIONS"] as $oName=>$oValue){
				$repo->addOption($oName, $oValue);
			}
		}
		// BACKWARD COMPATIBILITY!
		if(array_key_exists("PATH", $repository)){
			$repo->addOption("PATH", $repository["PATH"]);
			$repo->addOption("CREATE", $repository["CREATE"]);
			$repo->addOption("RECYCLE_BIN", $repository["RECYCLE_BIN"]);
		}
		return $repo;
	}
	
	/**
	 * Add dynamically created repository
	 *
	 * @param Repository $oRepository
	 * @return -1 if error
	 */
	function addRepository($oRepository){
		// update list
		$confRepoList = ConfService::loadRepoFile();
		$confRepoList[] = $oRepository;
		$res = ConfService::saveRepoFile($confRepoList);
		if($res == -1){
			return $res;
		}
		AJXP_Logger::logAction("Create Repository", array("repo_name"=>$oRepository->getDisplay()));
		global $G_DEFAULT_REPOSITORIES, $G_REPOSITORIES;
		$G_REPOSITORIES = ConfService::initRepositoriesList($G_DEFAULT_REPOSITORIES);
	}
	
	function getRepositoryById($repoId){
		$confRepoList = ConfService::loadRepoFile();
		foreach ($confRepoList as $repo){
			if($repo->getUniqueId() == $repoId){
				return $repo;
			}
		}
		return null;
	}
	
	/**
	 * Replace a repository by an update one.
	 *
	 * @param String $oldId
	 * @param Repository $oRepositoryObject
	 * @return mixed
	 */
	function replaceRepository($oldId, $oRepositoryObject){
		$confRepoList = ConfService::loadRepoFile();
		foreach ($confRepoList as $index => $repo){
			if($repo->getUniqueId() == $oldId){
				$confRepoList[$index] = $oRepositoryObject ;
			}
		}
		$res = ConfService::saveRepoFile($confRepoList);
		if($res == -1){
			return $res;
		}
		AJXP_Logger::logAction("Edit Repository", array("repo_name"=>$oRepositoryObject->getDisplay()));
		global $G_DEFAULT_REPOSITORIES, $G_REPOSITORIES;
		$G_REPOSITORIES = ConfService::initRepositoriesList($G_DEFAULT_REPOSITORIES);				
	}
	
	function deleteRepository($repoId){
		global $G_DEFAULT_REPOSITORIES, $G_REPOSITORIES;
		$newList = array();
		$found = false;
		foreach ($G_REPOSITORIES as  $repId => $repo){			
			if($repId == $repoId){
				$found = true;
				continue;
			}
			if(!$repo->isWriteable()) continue;
			$newList[] = $repo;
		}
		$res = ConfService::saveRepoFile($newList);
		if($res == -1 || !$found){
			return -1;
		}
		AJXP_Logger::logAction("Delete Repository", array("repo_id"=>$repoId));
		$G_REPOSITORIES = ConfService::initRepositoriesList($G_DEFAULT_REPOSITORIES);		
	}
		
	function zipEnabled()
	{
		return (function_exists("gzopen")?true:false);		
	}
	
	function getMessages()
	{
		global $G_MESSAGES, $G_LANGUE;		
		if(!isset($G_MESSAGES))
		{			
			require(INSTALL_PATH."/".CLIENT_RESOURCES_FOLDER."/i18n/${G_LANGUE}.php");
			$G_MESSAGES = $mess;
		}
		return $G_MESSAGES;
	}
	
	function listAvailableLanguages(){
		// Cache in session!
		if(isSet($_SESSION["AJXP_LANGUAGES"]) && !isSet($_GET["refresh_langs"])){
			return $_SESSION["AJXP_LANGUAGES"];
		}
		$langDir = INSTALL_PATH."/".CLIENT_RESOURCES_FOLDER."/i18n";
		$languages = array();
		if($dh = opendir($langDir)){
			while (($file = readdir($dh)) !== false) {
				$matches = array();
				if(preg_match("/(.*)\.php/", $file, $matches) == 1){
					$fRadical = $matches[1];
					include($langDir."/".$fRadical.".php");
					$langName = isSet($mess["languageLabel"])?$mess["languageLabel"]:"Not Found";
					$languages[$fRadical] = $langName;
				}
			}
			closedir($dh);
		}
		if(count($languages)){
			$_SESSION["AJXP_LANGUAGES"] = $languages;
		}
		return $languages;
	}

	function getConf($varName)	
	{
		global $G_LANGUE,$G_AVAILABLE_LANG,$G_MESSAGES,$G_USE_HTTPS,$G_WM_EMAIL,$G_MAX_CHAR, $G_UPLOAD_MAX_NUMBER, $G_UPLOAD_MAX_TOTAL, $G_UPLOAD_MAX_FILE;
		$globVarName = "G_".$varName;
		return $$globVarName;
	}
	
	function setLanguage($lang)
	{
		global $G_LANGUE, $G_AVAILABLE_LANG;
		if(array_key_exists($lang, $G_AVAILABLE_LANG))
		{
			$G_LANGUE = $lang;
		}
	}
	
	function getLanguage()
	{
		global $G_LANGUE;
		return $G_LANGUE;
	}
		
	/**
	 * @return Repository
	 */
	function getRepository()
	{
		global $G_REPOSITORY;
		return $G_REPOSITORY;
	}
	
	/**
	 * Returns the repository access driver
	 *
	 * @return AbstractDriver
	 */
	function getRepositoryDriver()
	{
		global $G_ACCESS_DRIVER;
		if(isSet($G_ACCESS_DRIVER) && is_a($G_ACCESS_DRIVER, "AbstractDriver")){			
			return $G_ACCESS_DRIVER;
		}
        ConfService::switchRootDir();
		$crtRepository = ConfService::getRepository();
		$accessType = $crtRepository->getAccessType();
		$driverName = $accessType."Driver";
		$path = INSTALL_PATH."/plugins/ajxp.".$accessType;
		$filePath = $path."/class.".$driverName.".php";
		$xmlPath = $path."/".$accessType."Actions.xml";
		if(is_file($filePath)){
			include_once($filePath);
			if(class_exists($driverName)){
				$G_ACCESS_DRIVER = new $driverName($accessType, $xmlPath, $crtRepository);
				$res = $G_ACCESS_DRIVER->initRepository();
				if($res!=null && is_a($res, "AJXP_Exception")){
					$G_ACCESS_DRIVER = null;
					return $res;
				}				
				return $G_ACCESS_DRIVER;
			}
		}
		
	}
	
	function availableDriversToXML(){
		$manifests = array();
		$base = INSTALL_PATH."/plugins";
		$xmlString = "";
		if($fp = opendir($base)){
			while (($subdir = readdir($fp))!==false) {
				$manifName = $base."/".$subdir."/manifest.xml";
				if(is_file($manifName) && is_readable($manifName)){
					$lines = file($manifName);
					array_shift($lines);// Remove first line (xml declaration);					
					$xmlString .= implode("", $lines);
				}
			}
			closedir($fp);
		}
		return str_replace("\t", "", str_replace("\n", "", $xmlString));
	}
	
	function loadRepoFile(){
		$result = array();
		if(is_file(INSTALL_PATH."/server/conf/repo.ser"))
		{
			$fileLines = file(INSTALL_PATH."/server/conf/repo.ser");
			$result = unserialize($fileLines[0]);
		}
		return $result;
	}
	
	function saveRepoFile($value){		
		if(!is_writeable(INSTALL_PATH."/server/conf")) return -1;
		$fp = @fopen(INSTALL_PATH."/server/conf/repo.ser", "w");
		fwrite($fp, serialize($value));
		fclose($fp);
	}
	
	
}


?>
