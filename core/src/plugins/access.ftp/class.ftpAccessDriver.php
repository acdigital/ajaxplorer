<?php
/**
 * @package info.ajaxplorer.plugins
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
 * Description : FTP access
 */
class ftpAccessDriver extends fsAccessDriver {
	
	function initRepository(){
		if(is_array($this->pluginConf)){
			$this->driverConf = $this->pluginConf;
		}else{
			$this->driverConf = array();
		}
		$create = $this->repository->getOption("CREATE");
		$wrapperData = $this->detectStreamWrapper(true);
		$this->wrapperClassName = $wrapperData["classname"];
		$this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();
		$recycle = $this->repository->getOption("RECYCLE_BIN");
		if($recycle != ""){
			RecycleBinManager::init($this->urlBase, "/".$recycle);
		}
	}

	function uploadActions($action, $httpVars, $filesVars){
		switch ($action){
			case "trigger_remote_copy":
				if(!$this->hasFilesToCopy()) break;
				$toCopy = $this->getFileNameToCopy();
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::triggerBgAction("next_to_remote", array(), "Copying file ".$toCopy." to ftp server");
				AJXP_XMLWriter::close();
				exit(1);
			break;
			case "next_to_remote":
				if(!$this->hasFilesToCopy()) break;
				$fData = $this->getNextFileToCopy();
				$nextFile = '';
				if($this->hasFilesToCopy()){
					$nextFile = $this->getFileNameToCopy();
				}
				AJXP_Logger::debug("Base64 : ", array("from"=>$fData["destination"], "to"=>base64_decode($fData['destination'])));
				$destPath = $this->urlBase.base64_decode($fData['destination'])."/".$fData['name'];
				$destPath = SystemTextEncoding::fromPostedFileName($destPath);
				AJXP_Logger::debug("Copying file to server", array("from"=>$fData["tmp_name"], "to"=>$destPath, "name"=>$fData["name"]));
				try {
					$fp = fopen($destPath, "w");
					$fSource = fopen($fData["tmp_name"], "r");
					while(!feof($fSource)){
						fwrite($fp, fread($fSource, 4096));
					}
					fclose($fp);
					@unlink($fData["tmp_name"]);
				}catch (Exception $e){
					AJXP_Logger::debug("Error during ftp copy", array($e->getMessage(), $e->getTrace()));
				}
				AJXP_XMLWriter::header();
				if($nextFile!=''){
					AJXP_XMLWriter::triggerBgAction("next_to_remote", array(), "Copying file ".$nextFile." to remote server");
				}else{
					AJXP_XMLWriter::triggerBgAction("reload_node", array(), "Upload done, reloading client.");
				}
				AJXP_XMLWriter::close();
				exit(1);
			break;
			case "upload":
				$rep_source = AJXP_Utils::securePath("/".$httpVars['dir']);
				AJXP_Logger::debug("Upload : rep_source ", array($rep_source));
				$logMessage = "";
				foreach ($filesVars as $boxName => $boxData)
				{
					if(substr($boxName, 0, 9) != "userfile_")     continue;
					AJXP_Logger::debug("Upload : rep_source ", array($rep_source));
					$err = AJXP_Utils::parseFileDataErrors($boxData, $fancyLoader);
					if($err != null)
					{
						$errorCode = $err[0];
						$errorMessage = $err[1];
						break;
					}
					$boxData["destination"] = base64_encode($rep_source);
					$destCopy = AJXP_XMLWriter::replaceAjxpXmlKeywords($this->repository->getOption("TMP_UPLOAD"));
					AJXP_Logger::debug("Upload : tmp upload folder", array($destCopy));
					if(!is_dir($destCopy)){
						if(! @mkdir($destCopy)){
							AJXP_Logger::debug("Upload error : cannot create temporary folder", array($destCopy));
							$errorCode = 413;
							$errorMessage = "Warning, cannot create folder for temporary copy.";
							break;
						}
					}
					if(!is_writeable($destCopy)){
						AJXP_Logger::debug("Upload error: cannot write into temporary folder");
						$errorCode = 414;
						$errorMessage = "Warning, cannot write into temporary folder.";
						break;
					}
					AJXP_Logger::debug("Upload : tmp upload folder", array($destCopy));
					$destName = $destCopy."/".basename($boxData["tmp_name"]);
					if ($destName == $boxData["tmp_name"]) $destName .= "1";
					if(move_uploaded_file($boxData["tmp_name"], $destName)){
						$boxData["tmp_name"] = $destName;
						$this->storeFileToCopy($boxData);
					}else{
						$mess = ConfService::getMessages();
						$errorCode = 411;
						$errorMessage="$mess[33] ".$boxData["name"];
					}
				}
				if(isSet($errorMessage)){
					AJXP_Logger::debug("Return error $errorCode $errorMessage");
					return array("ERROR" => array("CODE" => $errorCode, "MESSAGE" => $errorMessage));
				}else{
					AJXP_Logger::debug("Return success");
					return array("SUCCESS" => true);
				}
				
			break;
			default:
			break;
		}		
		session_write_close();
		exit;

	}

    function storeFileToCopy($fileData){
            $user = AuthService::getLoggedUser();
            $files = $user->getTemporaryData("tmp_upload");
            AJXP_Logger::debug("Saving user temporary data", array($fileData));
            $files[] = $fileData;
            $user->saveTemporaryData("tmp_upload", $files);
    }

    function getFileNameToCopy(){
            $user = AuthService::getLoggedUser();
            $files = $user->getTemporaryData("tmp_upload");
            return $files[0]["name"];
    }

    function getNextFileToCopy(){
            if(!$this->hasFilesToCopy()) return "";
            $user = AuthService::getLoggedUser();
            $files = $user->getTemporaryData("tmp_upload");
            $fData = $files[0];
            array_shift($files);
            $user->saveTemporaryData("tmp_upload", $files);
            return $fData;
    }

    function hasFilesToCopy(){
            $user = AuthService::getLoggedUser();
            $files = $user->getTemporaryData("tmp_upload");
            return (count($files)?true:false);
    }
	
	
}
?>