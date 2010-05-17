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
 * Description : Class for handling pdf preview, etc... Rely on the StreamWrappers, ImageMagick and GhostScript
 */
class PdfPreviewer extends AJXP_Plugin {

	public function switchAction($action, $httpVars, $filesVars){
		
		if(!isSet($this->actions[$action])) return false;
    	
		$repository = ConfService::getRepository();
		if(!$repository->detectStreamWrapper(true)){
			return false;
		}
		if(!is_array($this->pluginConf) || !isSet($this->pluginConf["IMAGE_MAGICK_CONVERT"])){
			return false;
		}
    	$destStreamURL = "ajxp.".$repository->getAccessType()."://".$repository->getId();
		    	
		if($action == "pdf_data_proxy"){
			$extractAll = false;
			if(isSet($httpVars["all"])) $extractAll = true;			
			
			$file = AJXP_Utils::securePath(SystemTextEncoding::fromUTF8($httpVars["file"]));
			$fp = fopen($destStreamURL."/".$file, "r");
			$tmpFileName = sys_get_temp_dir()."/ajxp_tmp_".md5(time()).".pdf";
			$tmpFile = fopen($tmpFileName, "w");
			register_shutdown_function("unlink", $tmpFileName);
			while(!feof($fp)) {
				stream_copy_to_stream($fp, $tmpFile, 4096);
			}
			fclose($tmpFile);
			fclose($fp);
			$out = array();
			$return = 0;
			$tmpFileThumb = str_replace(".pdf", ".jpg", $tmpFileName);
			if(!$extractAll)register_shutdown_function("unlink", $tmpFileThumb);
			chdir(sys_get_temp_dir());
			$pageLimit = ($extractAll?"":"[0]");
			$params = ($extractAll?"-quality ".$this->pluginConf["IM_VIEWER_QUALITY"]:"-resize 250 -quality ".$this->pluginConf["IM_THUMB_QUALITY"]);
			$cmd = $this->pluginConf["IMAGE_MAGICK_CONVERT"]." ".basename($tmpFileName).$pageLimit." ".$params." ".basename($tmpFileThumb);
			session_write_close(); // Be sure to give the hand back
			exec($cmd, $out, $return);
			if(is_array($out) && count($out)){
				throw new AJXP_Exception(implode("\n", $out));
			}
			if($extractAll){
				$prefix = str_replace(".pdf", "", $tmpFileName);
				$files = $this->listExtractedJpg($prefix);
				header("Content-Type: application/json");
				print(json_encode($files));
				exit(1);
			}else{
				header("Content-Type: image/jpeg; name=\"".basename($file)."\"");
				header("Content-Length: ".filesize($tmpFileThumb));
				header('Cache-Control: public');
				readfile($tmpFileThumb);
				exit(1);
			}			
		}else if($action == "get_extracted_page" && isSet($httpVars["file"])){
			$file = sys_get_temp_dir()."/".$httpVars["file"];
			if(!is_file($file)) return ;
			header("Content-Type: image/jpeg; name=\"".basename($file)."\"");
			header("Content-Length: ".filesize($file));
			header('Cache-Control: public');
			readfile($file);
			exit(1);			
		}else if($action == "delete_pdf_data" && isSet($httpVars["file"])){
			$files = $this->listExtractedJpg(sys_get_temp_dir()."/".$httpVars["file"]);
			foreach ($files as $file){
				if(is_file(sys_get_temp_dir()."/".$file["file"])) unlink(sys_get_temp_dir()."/".$file["file"]);
			}
		}
	}
	
	protected function listExtractedJpg($prefix){
		$files = array();
		$index = 0;
		while(is_file($prefix."-".$index.".jpg")){
			$extract = $prefix."-".$index.".jpg";
			list($width, $height, $type, $attr) = @getimagesize($extract);
			$files[] = array("file" => basename($extract), "width"=>$width, "height"=>$height);
			$index ++;
		}
		if(is_file($prefix.".jpg")){
			$extract = $prefix.".jpg";
			list($width, $height, $type, $attr) = @getimagesize($extract);
			$files[] = array("file" => basename($extract), "width"=>$width, "height"=>$height);
		}
		return $files;
	}
	
}
?>