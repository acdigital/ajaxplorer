<?php

class fsDriver extends AbstractDriver 
{
	/**
	* @var Repository
	*/
	var $repository;
	
	function  fsDriver($driverName, $filePath, $repository){
		parent::AbstractDriver($driverName, $filePath, $repository);
	}
	
	function switchAction($action, $httpVars, $fileVars){
		if(!isSet($this->actions[$action])) return;
		$xmlBuffer = "";
		foreach($httpVars as $getName=>$getValue){
			$$getName = Utils::securePath($getValue);
		}
		$selection = new UserSelection();
		$selection->initFromHttpVars($httpVars);
		if(isSet($dir) && $action != "upload") $dir = utf8_decode($dir);
		if(isSet($dest)) $dest = utf8_decode($dest);
		$mess = ConfService::getMessages();
		// FILTER ACTION FOR DELETE
		if(ConfService::useRecycleBin() && $action == "delete" && $dir != "/".ConfService::getRecycleBinDir())
		{
			$action = "move";
			$dest = "/".ConfService::getRecycleBinDir();			
			$dest_node = "AJAXPLORER_RECYCLE_NODE";
		}
		// FILTER ACTION FOR RESTORE
		if(ConfService::useRecycleBin() &&  $action == "restore" && $dir == "/".ConfService::getRecycleBinDir())
		{
			$originalRep = RecycleBinManager::getFileOrigin($selection->getUniqueFile());
			if($originalRep != "")
			{
				$action = "move";
				$dest = $originalRep;
			}
		}
		
		switch($action)
		{			
			//------------------------------------
			//	DOWNLOAD, IMAGE & MP3 PROXYS
			//------------------------------------
			case "download";
				$this->readFile($this->repository->getPath()."/".utf8_decode($file), "force-download");
				exit(0);
			break;
		
			case "image_proxy":
				$this->readFile($this->repository->getPath()."/".utf8_decode($file), "image");
				exit(0);
			break;
			
			case "mp3_proxy":
				$this->readFile($this->repository->getPath()."/".$file, "mp3");
				exit(0);
			break;
			
			//------------------------------------
			//	ONLINE EDIT
			//------------------------------------
			case "edit";	
				$file = utf8_decode($file);
				if(isset($save) && $save==1)
				{
					$code=stripslashes($code);
					$code=str_replace("&lt;","<",$code);
					$fp=fopen($this->repository->getPath()."/$file","w");
					fputs ($fp,$code);
					fclose($fp);
					echo utf8_encode($mess[115]);
				}
				else 
				{
					$this->readFile($this->repository->getPath()."/".$file, "plain");
				}
				exit(0);
			break;
		
			//------------------------------------
			//	COPY / MOVE
			//------------------------------------
			case "copy";
			case "move";
				
				if($selection->isEmpty())
				{
					$errorMessage = $mess[113];
					break;
				}
				$success = $error = array();
				
				$this->copyOrMove($dest, $selection->getFiles(), $error, $success, ($action=="move"?true:false));
				
				if(count($error)){
					$errorMessage = join("\n", $error);
				}
				else {
					$logMessage = join("\n", $success);
				}
				$reload_current_node = true;
				if(isSet($dest_node)) $reload_dest_node = $dest_node;
				$reload_file_list = true;
				
			break;
			
			//------------------------------------
			//	SUPPRIMER / DELETE
			//------------------------------------
			case "delete";
			
				if($selection->isEmpty())
				{
					$errorMessage = $mess[113];
					break;
				}
				$logMessages = array();
				$errorMessage = $this->delete($selection->getFiles(), $logMessages);
				if(count($logMessages))
				{
					$logMessage = join("\n", $logMessages);
				}
				$reload_current_node = true;
				$reload_file_list = true;
				
			break;
		
			//------------------------------------
			//	RENOMMER / RENAME
			//------------------------------------
			case "rename";
			
				$file = utf8_decode($file);
				$filename_new = utf8_decode($filename_new);
				$error = $this->rename($file, $filename_new);
				if($error != null) {
					$errorMessage  = $error;
					break;
				}
				$logMessage="$file $mess[41] $filename_new";
				$reload_current_node = true;
				$reload_file_list = basename($filename_new);
				
			break;
		
			//------------------------------------
			//	CREER UN REPERTOIRE / CREATE DIR
			//------------------------------------
			case "mkdir";
			
				$messtmp="";
				$dirname=Utils::processFileName(utf8_decode($dirname));
				$error = $this->mkDir($dir, $dirname);
				if(isSet($error)){
					$errorMessage = $error; break;
				}
				$reload_file_list = $dirname;
				$messtmp.="$mess[38] $dirname $mess[39] ";
				if($dir=="") {$messtmp.="/";} else {$messtmp.="$dir";}
				$logMessage = $messtmp;
				$reload_current_node = true;
				
			break;
		
			//------------------------------------
			//	CREER UN FICHIER / CREATE FILE
			//------------------------------------
			case "mkfile";
			
				$messtmp="";
				$filename=Utils::processFileName(utf8_decode($filename));	
				$error = $this->createEmptyFile($dir, $filename);
				if(isSet($error)){
					$errorMessage = $error; break;
				}
				$messtmp.="$mess[34] $filename $mess[39] ";
				if($dir=="") {$messtmp.="/";} else {$messtmp.="$dir";}
				$logMessage = $messtmp;
				$reload_file_list = $filename;
		
			break;
			
			//------------------------------------
			//	UPLOAD
			//------------------------------------	
			case "upload":
		
				if($dir!=""){$rep_source="/$dir";}
				else $rep_source = "";
				$destination=$this->repository->getPath().$rep_source;
				if(!$this->isWriteable($destination))
				{
					$errorMessage = "$mess[38] $dir $mess[99].";
					break;
				}	
				$logMessage = "";
				$fancyLoader = false;
				foreach ($fileVars as $boxName => $boxData)
				{
					if($boxName != "Filedata" && substr($boxName, 0, 9) != "userfile_")	continue;
					if($boxName == "Filedata") $fancyLoader = true;
					$err = Utils::parseFileDataErrors($boxData, $fancyLoader);
					if($err != null)
					{
						$errorMessage = $err;
						break;
					}
					$userfile_name = $boxData["name"];
					if($fancyLoader) $userfile_name = utf8_decode($userfile_name);
					$userfile_name=Utils::processFileName($userfile_name);
					if (!move_uploaded_file($boxData["tmp_name"], "$destination/".$userfile_name))
					{
						$errorMessage=($fancyLoader?"411 ":"")."$mess[33] ".$userfile_name;
						break;
					}
					$logMessage.="$mess[34] ".$userfile_name." $mess[35] $dir";
				}
				if($fancyLoader)
				{
					if(isSet($errorMessage)){
						header('HTTP/1.0 '.$errorMessage);
						die('Error '.$errorMessage);
					}else{
						header('HTTP/1.0 200 OK');
						die("200 OK");
					}
				}
				else
				{
					print("<html><script language=\"javascript\">\n");
					if(isSet($errorMessage)){
						print("\n if(parent.ajaxplorer.actionBar.multi_selector)parent.ajaxplorer.actionBar.multi_selector.submitNext('".str_replace("'", "\'", $errorMessage)."');");		
					}else{		
						print("\n if(parent.ajaxplorer.actionBar.multi_selector)parent.ajaxplorer.actionBar.multi_selector.submitNext();");
					}
					print("</script></html>");
				}
				exit;
				
			break;
			
			//------------------------------------
			//	XML LISTING
			//------------------------------------
			case "ls":
			
				if(!isSet($dir) || $dir == "/") $dir = "";
				$searchMode = $fileListMode = $completeMode = false;
				if(isSet($mode)){
					if($mode == "search") $searchMode = true;
					else if($mode == "file_list") $fileListMode = true;
					else if($mode == "complete") $completeMode = true;
				}	
				$nom_rep = $this->initName($dir);
				AJXP_Exception::errorToXml($nom_rep);
				$result = $this->listing($nom_rep, !($searchMode || $fileListMode));
				$reps = $result[0];
				AJXP_XMLWriter::header();
				foreach ($reps as $repIndex => $repName)
				{
					$link = SERVER_ACCESS."?dir=".$dir."/".$repName;
					$link = str_replace("/", "%2F", $link);
					$link = str_replace("&", "&amp;", $link);
					$attributes = "";
					if($searchMode)
					{
						if(is_file($nom_rep."/".$repIndex)) {$attributes = "is_file=\"true\" icon=\"$repName\""; $repName = $repIndex;}
					}
					else if($fileListMode)
					{
						$currentFile = $nom_rep."/".$repIndex;			
						$atts = array();
						$atts[] = "is_file=\"".(is_file($currentFile)?"1":"0")."\"";
						$atts[] = "is_image=\"".Utils::is_image($currentFile)."\"";
						if(Utils::is_image($currentFile))
						{
							list($width, $height, $type, $attr) = @getimagesize($currentFile);
							$atts[] = "image_type=\"".image_type_to_mime_type($type)."\"";
							$atts[] = "image_width=\"$width\"";
							$atts[] = "image_height=\"$height\"";
						}
						$atts[] = "mimestring=\"".Utils::mimetype($currentFile, "type", is_dir($currentFile))."\"";
						$atts[] = "modiftime=\"".$this->date_modif($currentFile)."\"";
						$atts[] = "filesize=\"".Utils::roundSize(filesize($currentFile))."\"";
						$atts[] = "filename=\"".$dir."/".str_replace("&", "&amp;", $repIndex)."\"";
						$atts[] = "icon=\"".(is_file($currentFile)?$repName:"folder.png")."\"";
						
						$attributes = join(" ", $atts);
						$repName = $repIndex;
					}
					else 
					{
						$folderBaseName = str_replace("&", "&amp;", $repName);
						$folderFullName = "$dir/".$folderBaseName;
						$parentFolderName = $dir;
						if(!$completeMode){
							$attributes = "icon=\"".CLIENT_RESOURCES_FOLDER."/images/foldericon.png\"  openicon=\"".CLIENT_RESOURCES_FOLDER."/images/openfoldericon.png\" filename=\"$folderFullName\" src=\"$link\"";
						}
					}
					print(utf8_encode("<tree text=\"".str_replace("&", "&amp;", $repName)."\" $attributes>"));
					print("</tree>");
				}
				if($nom_rep == $this->repository->getPath() && ConfService::useRecycleBin() && !$completeMode)
				{
					if($fileListMode)
					{
						print(utf8_encode("<tree text=\"".str_replace("&", "&amp;", $mess[122])."\" filesize=\"-\" is_file=\"0\" is_recycle=\"1\" mimestring=\"Trashcan\" modiftime=\"".$this->date_modif($this->repository->getPath()."/".ConfService::getRecycleBinDir())."\" filename=\"/".ConfService::getRecycleBinDir()."\" icon=\"trashcan.png\"></tree>"));
					}
					else 
					{
						// ADD RECYCLE BIN TO THE LIST
						print("<tree text=\"$mess[122]\" is_recycle=\"true\" icon=\"".CLIENT_RESOURCES_FOLDER."/images/crystal/mimes/16/trashcan.png\"  openIcon=\"".CLIENT_RESOURCES_FOLDER."/images/crystal/mimes/16/trashcan.png\" filename=\"/".ConfService::getRecycleBinDir()."\"/>");
					}
				}
				AJXP_XMLWriter::close();
				exit(1);
				
			break;		
		}

		if(isset($logMessage) || isset($errorMessage))
		{
			$xmlBuffer .= AJXP_XMLWriter::sendMessage((isSet($logMessage)?$logMessage:null), (isSet($errorMessage)?$errorMessage:null), false);			
		}
		
		if(isset($requireAuth))
		{
			$xmlBuffer .= AJXP_XMLWriter::requireAuth(false);
		}
		
		if(isset($reload_current_node) && $reload_current_node == "true")
		{
			$xmlBuffer .= AJXP_XMLWriter::reloadCurrentNode(false);
		}
		
		if(isset($reload_dest_node) && $reload_dest_node != "")
		{
			$xmlBuffer .= AJXP_XMLWriter::reloadNode($reload_dest_node, false);
		}
		
		if(isset($reload_file_list))
		{
			$xmlBuffer .= AJXP_XMLWriter::reloadFileList($reload_file_list, false);
		}
		
		return $xmlBuffer;
	}
	
	function initName($dir)
	{
		$racine = $this->repository->getPath();
		$mess = ConfService::getMessages();
		if(!isset($dir) || $dir=="" || $dir == "/")
		{
			$nom_rep=$racine;
		}
		else
		{
			$nom_rep="$racine/$dir";
		}
		if(!file_exists($racine))
		{
			return new AJXP_Exception(72);
		}
		if(!is_dir($nom_rep))
		{
			return new AJXP_Exception(100);
		}
		return $nom_rep;
	}
	
	function readFile($filePath, $headerType="plain")
	{
		if($headerType == "plain")
		{
			header("Content-type:text/plain");			
		}
		else if($headerType == "image")
		{
			$size=filesize($filePath);
			header("Content-Type: ".Utils::getImageMimeType(basename($filePath))."; name=\"".basename($filePath)."\"");
			header("Content-Length: ".$size);
			header('Cache-Control: public');			
		}
		else if($headerType == "mp3")
		{
			$size=filesize($filePath);
			header("Content-Type: audio/mp3; name=\"".basename($filePath)."\"");
			header("Content-Length: ".$size);
		}
		else 
		{
			$size=filesize($filePath);
			header("Content-Type: application/force-download; name=\"".basename($filePath)."\"");
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".$size);
			header("Content-Disposition: attachment; filename=\"".basename($filePath)."\"");
			header("Expires: 0");
			header("Cache-Control: no-cache, must-revalidate");
			header("Pragma: no-cache");
			// For SSL websites there is a bug with IE see article KB 323308
			// therefore we must reset the Cache-Control and Pragma Header
			if (ConfService::getConf("USE_HTTPS")==1 && preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT']))
			{
				header("Cache-Control:");
				header("Pragma:");
			}
		}
		readfile($filePath);
	}
	
	
	function listing($nom_rep, $dir_only = false)
	{
		$size_unit = ConfService::getConf("SIZE_UNIT");
		$sens = 0;
		$ordre = "nom";
		$poidstotal=0;
		$handle=opendir($nom_rep);
		while ($file = readdir($handle))
		{
			if($file!="." && $file!=".." && Utils::showHiddenFiles($file)==1)
			{
				if(ConfService::getRecycleBinDir() != "" 
					&& $nom_rep == $this->repository->getPath()."/".ConfService::getRecycleBinDir() 
					&& $file == RecycleBinManager::getCacheFileName()){
					continue;
				}
				$poidsfic=filesize("$nom_rep/$file");
				$poidstotal+=$poidsfic;
				if(is_dir("$nom_rep/$file"))
				{					
					if(ConfService::useRecycleBin() && $this->repository->getPath()."/".ConfService::getRecycleBinDir() == "$nom_rep/$file")
					{
						continue;
					}
					if($ordre=="mod") {$liste_rep[$file]=filemtime("$nom_rep/$file");}
					else {$liste_rep[$file]=$file;}
				}
				else
				{
					if(!$dir_only)
					{
						if($ordre=="nom") {$liste_fic[$file]=Utils::mimetype("$nom_rep/$file","image", is_dir("$nom_rep/$file"));}
						else if($ordre=="taille") {$liste_fic[$file]=$poidsfic;}
						else if($ordre=="mod") {$liste_fic[$file]=filemtime("$nom_rep/$file");}
						else if($ordre=="type") {$liste_fic[$file]=Utils::mimetype("$nom_rep/$file","type",is_dir("$nom_rep/$file"));}
						else {$liste_fic[$file]=Utils::mimetype("$nom_rep/$file","image", is_dir("$nom_rep/$file"));}
					}
				}
			}
		}
		closedir($handle);
	
		if(isset($liste_fic) && is_array($liste_fic))
		{
			if($ordre=="nom") {if($sens==0){ksort($liste_fic);}else{krsort($liste_fic);}}
			else if($ordre=="mod") {if($sens==0){arsort($liste_fic);}else{asort($liste_fic);}}
			else if($ordre=="taille"||$ordre=="type") {if($sens==0){asort($liste_fic);}else{arsort($liste_fic);}}
			else {if($sens==0){ksort($liste_fic);}else{krsort($liste_fic);}}
		}
		else
		{
			$liste_fic = array();
		}
		if(isset($liste_rep) && is_array($liste_rep))
		{
			if($ordre=="mod") {if($sens==0){arsort($liste_rep);}else{asort($liste_rep);}}
			else {if($sens==0){ksort($liste_rep);}else{krsort($liste_rep);}}
		}
		else ($liste_rep = array());
	
		$liste = Utils::mergeArrays($liste_rep,$liste_fic);
		if ($poidstotal >= 1073741824) {$poidstotal = round($poidstotal / 1073741824 * 100) / 100 . " G".$size_unit;}
		elseif ($poidstotal >= 1048576) {$poidstotal = round($poidstotal / 1048576 * 100) / 100 . " M".$size_unit;}
		elseif ($poidstotal >= 1024) {$poidstotal = round($poidstotal / 1024 * 100) / 100 . " K".$size_unit;}
		else {$poidstotal = $poidstotal . " ".$size_unit;}
	
		return array($liste,$poidstotal);
	}
	
	function date_modif($file)
	{
		$tmp = filemtime($file);
		return date("d/m/Y H:i",$tmp);
	}
	
	function copyOrMove($destDir, $selectedFiles, &$error, &$success, $move = false)
	{
		$mess = ConfService::getMessages();
		if(!is_writable($this->repository->getPath()."/".$destDir))
		{
			$error[] = $mess[38]." ".$destDir." ".$mess[99];
			return ;
		}
				
		foreach ($selectedFiles as $selectedFile)
		{
			if($move && !is_writable(dirname($this->repository->getPath()."/".$selectedFile)))
			{
				$error[] = "\n".$mess[38]." ".dirname($selectedFile)." ".$mess[99];
				continue;
			}
			$this->copyOrMoveFile($destDir, $selectedFile, $error, $success, $move);
		}
	}
	
	function renameAction($actionName, $httpVars)
	{
		$filePath = utf8_decode($httpVars["file"]);
		$newFilename = utf8_decode($httpVars["filename_new"]);
		return $this->rename($filePath, $newFilename);
	}
	
	function rename($filePath, $filename_new)
	{
		$nom_fic=basename($filePath);
		$mess = ConfService::getMessages();
		$filename_new=Utils::processFileName($filename_new);
		$old=$this->repository->getPath()."/$filePath";
		if(!is_writable($old))
		{
			return $mess[34]." ".$nom_fic." ".$mess[99];
		}
		$new=dirname($old)."/".$filename_new;
		if($filename_new=="")
		{
			return "$mess[37]";
		}
		if(file_exists($new))
		{
			return "$filename_new $mess[43]"; 
		}
		if(!file_exists($old))
		{
			return $mess[100]." $nom_fic";
		}
		rename($old,$new);
		return null;		
	}
	
	function mkDir($crtDir, $newDirName)
	{
		$mess = ConfService::getMessages();
		if($newDirName=="")
		{
			return "$mess[37]";
		}
		if(file_exists($this->repository->getPath()."/$crtDir/$newDirName"))
		{
			return "$mess[40]"; 
		}
		if(!is_writable($this->repository->getPath()."/$crtDir"))
		{
			return $mess[38]." $crtDir ".$mess[99];
		}
		mkdir($this->repository->getPath()."/$crtDir/$newDirName",0775);
		return null;		
	}
	
	function createEmptyFile($crtDir, $newFileName)
	{
		$mess = ConfService::getMessages();
		if($newFileName=="")
		{
			return "$mess[37]";
		}
		if(file_exists($this->repository->getPath()."/$crtDir/$newFileName"))
		{
			return "$mess[71]";
		}
		if(!is_writable($this->repository->getPath()."/$crtDir"))
		{
			return "$mess[38] $crtDir $mess[99]";
		}
		
		$fp=fopen($this->repository->getPath()."/$crtDir/$newFileName","w");
		if($fp)
		{
			if(eregi("\.html$",$newFileName)||eregi("\.htm$",$newFileName))
			{
				fputs($fp,"<html>\n<head>\n<title>New Document - Created By AjaXplorer</title>\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">\n</head>\n<body bgcolor=\"#FFFFFF\" text=\"#000000\">\n\n</body>\n</html>\n");
			}
			fclose($fp);
			return null;
		}
		else
		{
			return "$mess[102] $crtDir/$newFileName (".$fp.")";
		}		
	}
	
	
	function delete($selectedFiles, &$logMessages)
	{
		$mess = ConfService::getMessages();
		foreach ($selectedFiles as $selectedFile)
		{	
			if($selectedFile == "" || $selectedFile == DIRECTORY_SEPARATOR)
			{
				return $mess[120];
			}
			$fileToDelete=$this->repository->getPath().$selectedFile;
			if(!file_exists($fileToDelete))
			{
				$logMessages[]=$mess[100]." $selectedFile";
				continue;
			}		
			$this->deldir($fileToDelete);
			if(is_dir($fileToDelete))
			{
				$logMessages[]="$mess[38] $selectedFile $mess[44].";
			}
			else 
			{
				$logMessages[]="$mess[34] $selectedFile $mess[44].";
			}
		}
		return null;
	}
	
	
	
	function copyOrMoveFile($destDir, $srcFile, &$error, &$success, $move = false)
	{
		$mess = ConfService::getMessages();		
		$destFile = $this->repository->getPath().$destDir."/".basename($srcFile);
		$realSrcFile = $this->repository->getPath()."/$srcFile";		
		if(!file_exists($realSrcFile))
		{
			$error[] = $mess[100].$srcFile;
			return ;
		}
		if($realSrcFile==$destFile)
		{
			$error[] = $mess[101];
			return ;
		}
		if(is_dir($realSrcFile))
		{
			$errors = array();
			$succFiles = array();
			$dirRes = $this->dircopy($realSrcFile, $destFile, $errors, $succFiles);
			if(count($errors))
			{
				$error[] = $mess[114];
				return ;
			}			
		}
		else 
		{
			$res = copy($realSrcFile,$destFile);
			if($res != 1)
			{
				$error[] = $mess[114];
				return ;
			}
		}
		
		if($move)
		{
			// Now delete original
			$this->deldir($realSrcFile); // both file and dir
			$messagePart = $mess[74]." $destDir";
			if($destDir == "/".ConfService::getRecycleBinDir())
			{
				RecycleBinManager::fileToRecycle($srcFile);
				$messagePart = $mess[123]." ".$mess[122];
			}
			if(isset($dirRes))
			{
				$success[] = $mess[117]." ".basename($srcFile)." ".$messagePart." ($dirRes ".$mess[116].") ";
			}
			else 
			{
				$success[] = $mess[34]." ".basename($srcFile)." ".$messagePart;
			}
		}
		else
		{			
			if($destDir == "/".ConfService::getRecycleBinDir())
			{
				RecycleBinManager::fileToRecycle($srcFile);
			}
			if(isSet($dirRes))
			{
				$success[] = $mess[117]." ".basename($srcFile)." ".$mess[73]." $destDir (".$dirRes." ".$mess[116].")";	
			}
			else 
			{
				$success[] = $mess[34]." ".basename($srcFile)." ".$mess[73]." $destDir";
			}
		}
		
	}

	// A function to copy files from one directory to another one, including subdirectories and
	// nonexisting or newer files. Function returns number of files copied.
	// This function is PHP implementation of Windows xcopy  A:\dir1\* B:\dir2 /D /E /F /H /R /Y
	// Syntaxis: [$number =] dircopy($sourcedirectory, $destinationdirectory [, $verbose]);
	// Example: $num = dircopy('A:\dir1', 'B:\dir2', 1);

	function dircopy($srcdir, $dstdir, &$errors, &$success, $verbose = false) 
	{
		$num = 0;
		if(!is_dir($dstdir)) mkdir($dstdir);
		if($curdir = opendir($srcdir)) 
		{
			while($file = readdir($curdir)) 
			{
				if($file != '.' && $file != '..') 
				{
					$srcfile = $srcdir . DIRECTORY_SEPARATOR . $file;
					$dstfile = $dstdir . DIRECTORY_SEPARATOR . $file;
					if(is_file($srcfile)) 
					{
						if(is_file($dstfile)) $ow = filemtime($srcfile) - filemtime($dstfile); else $ow = 1;
						if($ow > 0) 
						{
							if($verbose) echo "Copying '$srcfile' to '$dstfile'...";
							if(copy($srcfile, $dstfile)) 
							{
								touch($dstfile, filemtime($srcfile)); $num++;
								if($verbose) echo "OK\n";
								$success[] = $srcfile;
							}
							else 
							{
								$errors[] = $srcfile;
							}
						}
					}
					else if(is_dir($srcfile)) 
					{
						$num += $this->dircopy($srcfile, $dstfile, $errors, $success, $verbose);
					}
				}
			}
			closedir($curdir);
		}
		return $num;
	}
	
	function simpleCopy($origFile, $destFile)
	{
		return copy($origFile, $destFile);
	}
	
	function isWriteable($dir)
	{
		return is_writable($dir);
	}
	
	function deldir($location)
	{
		if(is_dir($location))
		{
			$all=opendir($location);
			while ($file=readdir($all))
			{
				if (is_dir("$location/$file") && $file !=".." && $file!=".")
				{
					$this->deldir("$location/$file");
					if(file_exists("$location/$file")){rmdir("$location/$file"); }
					unset($file);
				}
				elseif (!is_dir("$location/$file"))
				{
					if(file_exists("$location/$file")){unlink("$location/$file"); }
					unset($file);
				}
			}
			closedir($all);
			rmdir($location);
		}
		else
		{
			if(file_exists("$location")) {unlink("$location");}
		}
		if(basename(dirname($location)) == ConfService::getRecycleBinDir())
		{
			// DELETING FROM RECYCLE
			RecycleBinManager::deleteFromRecycle($location);
		}
	}
}

?>
