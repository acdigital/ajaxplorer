<?php
/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2010 Charles du Jeu
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
 * Description : Remote file downloader
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class HttpDownloader extends AJXP_Plugin{
	
	public function switchAction($action, $httpVars, $fileVars){		
		//AJXP_Logger::logAction("DL file", $httpVars);
				
		$repository = ConfService::getRepository();
		if(!$repository->detectStreamWrapper(false)){
			return false;
		}
		$plugin = AJXP_PluginsService::findPlugin("access", $repository->getAccessType());
		$streamData = $plugin->detectStreamWrapper(true);		
		$dir = AJXP_Utils::decodeSecureMagic($httpVars["dir"]);
    	$destStreamURL = $streamData["protocol"]."://".$repository->getId().$dir."/";
    	if(isSet($httpVars["file"])){
			$parts = parse_url($httpVars["file"]);
    		$getPath = $parts["path"];
			$basename = basename($getPath);
    	}		
    	if(isSet($httpVars["dlfile"])){
    	    		$dlFile = $streamData["protocol"]."://".$repository->getId().AJXP_Utils::decodeSecureMagic($httpVars["dlfile"]);
    				$realFile = file_get_contents($dlFile);
    				if(empty($realFile)) throw new Exception("cannot find file $dlFile for download");
					$parts = parse_url($realFile);
		    		$getPath = $parts["path"];
					$basename = basename($getPath);    				
    	}
    	
    	switch ($action){
    		case "external_download":
				if(!defined('STDIN')){
					$ajxpPath = AJXP_INSTALL_PATH;
					if(function_exists('mcrypt_create_iv')){
						$token = md5(time());
						$iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
						$user = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256,  md5($token."\1CDAFx¨op#"), AuthService::getLoggedUser()->getId(), MCRYPT_MODE_ECB, $iv));					
						$cmd = "php ".AJXP_INSTALL_PATH.DIRECTORY_SEPARATOR."cmd.php -u=$user -t=$token -a=external_download -r=".$repository->getId();
					}else{
						$cmd = "php ".AJXP_INSTALL_PATH.DIRECTORY_SEPARATOR."cmd.php -u=admin -p=admin -a=external_download -r=".$repository->getId();						
					}
					if(isSet($httpVars["file"])) $cmd .= " --file=".$httpVars["file"]." --dir=".$httpVars["dir"];
					else $cmd .= " --dlfile=".$httpVars["dlfile"]." --dir=".$httpVars["dir"];
					if(isset($httpVars["delete_dlfile"]) && $httpVars["delete_dlfile"] == "true"){
						$cmd .= " --delete_dlfile=true";
					}
					if (strstr(strtolower(PHP_OS), 'windows')!==false){
						$tmpBat = implode(DIRECTORY_SEPARATOR, array(AJXP_INSTALL_PATH, "server","tmp", md5(time()).".bat"));
						$cmd .= "\n DEL $tmpBat";
						AJXP_Logger::debug("Writing file $cmd to $tmpBat");
						file_put_contents($tmpBat, $cmd);
						pclose(popen("start /b ".$tmpBat, 'r'));
					}else{
						$tmpOutput = implode(DIRECTORY_SEPARATOR, array(AJXP_INSTALL_PATH, "server","tmp", md5(time()).".output"));						
						$process = new UnixProcess($cmd, $tmpOutput);
						$hiddenFilename = $destStreamURL.".".$basename.".pid";
						AJXP_Logger::debug(print_r($process, true)."--- ".$basename."---".$hiddenFilename);
						@file_put_contents($hiddenFilename, $process->getPid());
					}
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::triggerBgAction("reload_node", array(), "Triggering DL ");
					AJXP_XMLWriter::close();
					exit();			
				}
				    					    	
				require_once AJXP_INSTALL_PATH."/server/classes/class.HttpClient.php";
				$mess = ConfService::getMessages();		
				session_write_close();
				
				$client = new HttpClient($parts["host"]);		
				$collectHeaders = array(
					"ajxp-last-redirection" => "",
					"content-disposition"	=> "",
					"content-length"		=> ""
				);
				$client->setHeadersOnly(true, $collectHeaders);
				$client->setMaxRedirects(8);
				$client->setDebug(true);
				$client->get($getPath);
				
				$pidHiddenFileName = $destStreamURL.".".$basename.".pid";
				if(is_file($pidHiddenFileName)){
					$pid = file_get_contents($pidHiddenFileName);
					@unlink($pidHiddenFileName);
				}
				
				AJXP_Logger::debug("COLLECTED HEADERS", $client->collectHeaders);
				$collectHeaders = $client->collectHeaders;
				$totalSize = -1;
		    	if(!empty($collectHeaders["content-disposition"]) && strstr($collectHeaders["content-disposition"], "filename")!== false){
		    		$basename = trim(array_pop(explode("filename=", $collectHeaders["content-disposition"])));
		    		$basename = str_replace("\"", "", $basename); // Remove quotes
		    	}
		    	if(!empty($collectHeaders["content-length"])){
		    		$totalSize = intval($collectHeaders["content-length"]);
		    		AJXP_Logger::debug("Should download $totalSize bytes!");
		    	}
				$qData = false;
				if(!empty($collectHeaders["ajxp-last-redirection"])){
					$newParsed = parse_url($collectHeaders["ajxp-last-redirection"]);
					$client->host = $newParsed["host"];
					$getPath = $newParsed["path"];
					if(isset($newParsed["query"])){
						$qData = parse_url($newParsed["query"]);
					}
				}
				$tmpFilename = $destStreamURL.$basename.".dlpart";
				$hiddenFilename = $destStreamURL.".".$basename.".ser";		
				$filename = $destStreamURL.$basename;
				
				$dlData = array(
					"sourceUrl" => $getPath,
					"totalSize" => $totalSize
				);
				if(isSet($pid)){
					$dlData["pid"] = $pid;
				}
				file_put_contents($hiddenFilename, serialize($dlData));
				
				$client->redirect_count = 0;
				$client->setHeadersOnly(false);
				
				$destStream = fopen($tmpFilename, "w");
				if($destStream !== false){
		
					$client->writeContentToStream($destStream);			
					$client->get($getPath, $qData);			
					fclose($destStream);
							
				}
				rename($tmpFilename, $filename);
				unlink($hiddenFilename);
				if(isset($dlFile) && isSet($httpVars["delete_dlfile"]) && is_file($dlFile)){
					unlink($dlFile);
				}	
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::triggerBgAction("reload_node", array(), $mess["httpdownloader.8"]);
				AJXP_XMLWriter::close();
				exit();
    		break;
    		case "update_dl_data":
    			$file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
    			header("text/plain");
    			if(is_file($destStreamURL.$file)){ 	   			
    				echo filesize($destStreamURL.$file);
    			}else{
    				echo "stop";
    			}
    			exit;
    		break;
    		case "stop_dl":
				$newName = ".".str_replace(".dlpart", ".ser", $basename);
    			$hiddenFilename = $destStreamURL.$newName;
    			$data = @unserialize(@file_get_contents($hiddenFilename));
    			header("text/plain");
    			AJXP_Logger::debug("Getting $hiddenFilename",$data);
    			if(isSet($data["pid"])){
    				$process = new UnixProcess();
    				$process->setPid($data["pid"]);
    				$process->stop();
    				unlink($hiddenFilename);
    				unlink($destStreamURL.$basename);
    				echo 'stop';
    			}else{
    				echo 'failed';
    			}
    			exit();
    		break;
    		default:
    		break;
    	}
    	
		return true;    			
    	
	}
	
	/**
	 * @param AJXP_Node $ajxpNode
	 */
	public function detectDLParts(&$ajxpNode){
		if(!preg_match("/\.dlpart$/i",$ajxpNode->getUrl())){
			return;
		}
		$basename = basename($ajxpNode->getUrl());
		$newName = ".".str_replace(".dlpart", ".ser", $basename);
		$hidFile = str_replace($basename, $newName, $ajxpNode->getUrl());
		if(is_file($hidFile)){
			$data = unserialize(file_get_contents($hidFile));
			if($data["totalSize"] != -1){
				$ajxpNode->target_bytesize = $data["totalSize"];
				$ajxpNode->target_filesize = AJXP_Utils::roundSize($data["totalSize"]);
				$ajxpNode->process_stoppable = (isSet($data["pid"])?"true":"false");
			}
		}		
	}
}

?>