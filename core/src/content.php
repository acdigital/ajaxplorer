<?php
//---------------------------------------------------------------------------------------------------
//
//	AjaXplorer v1.4
//
//	Charles du Jeu
//	http://sourceforge.net/projects/ajaxplorer
//  http://www.almasound.com
//
//---------------------------------------------------------------------------------------------------

//require_once("classes/class.BookmarksManager.php");
require_once("classes/class.Utils.php");
require_once("classes/class.ConfService.php");
require_once("classes/class.AuthService.php");
require_once("classes/class.FS_Storage.php");
require_once("classes/class.UserSelection.php");
require_once("classes/class.HTMLWriter.php");
require_once("classes/class.AJXP_XMLWriter.php");
require_once("classes/class.AJXP_User.php");
require_once("classes/class.RecycleBinManager.php");
if(isSet($_GET["ajxp_sessid"]))
{
	$_COOKIE["PHPSESSID"] = $_GET["ajxp_sessid"];
}
session_start();
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
ConfService::init("conf/conf.php");
$hautpage=ConfService::getConf("TOP_PAGE");
$baspage=ConfService::getConf("BOTTOM_PAGE");
$limitSize = Utils::convertBytes(ini_get('upload_max_filesize'));

if(AuthService::usersEnabled())
{
	if(isSet($_GET["get_action"]) && $_GET["get_action"] == "logout")
	{
		AuthService::disconnect();
		$loggingResult = 2;
	}	//AuthService::disconnect();
	if(isSet($_GET["get_action"]) && $_GET["get_action"] == "login")
	{
		$userId = (isSet($_GET["userid"])?$_GET["userid"]:null);
		$userPass = (isSet($_GET["password"])?$_GET["password"]:null);
		$loggingResult = AuthService::logUser($userId, $userPass);
	}
	else 
	{
		AuthService::logUser(null, null);	
	}
	// Check that current user can access current repository, try to switch otherwise.
	$loggedUser = AuthService::getLoggedUser();
	if($loggedUser != null)
	{
		if(!$loggedUser->canRead(ConfService::getCurrentRootDirIndex()) && AuthService::getDefaultRootId() != ConfService::getCurrentRootDirIndex())
		{
			ConfService::switchRootDir(AuthService::getDefaultRootId());
		}
	}
	if($loggedUser == null)
	{
		$requireAuth = true;
	}
	if(isset($loggingResult) || (isSet($_GET["get_action"]) && $_GET["get_action"] == "logged_user"))
	{
		AJXP_XMLWriter::header();
		if(isSet($loggingResult)) AJXP_XMLWriter::loggingResult($loggingResult);
		AJXP_XMLWriter::sendUserData();
		AJXP_XMLWriter::close();
		exit(1);
	}
}

$loggedUser = AuthService::getLoggedUser();
if($loggedUser != null)
{
	if($loggedUser->getPref("lang") != "") ConfService::setLanguage($loggedUser->getPref("lang"));
}
$mess = ConfService::getMessages();

foreach($_GET as $getName=>$getValue)
{
	$$getName = $getValue;
}
foreach($_POST as $getName=>$getValue)
{
	$$getName = $getValue;
}

$selection = new UserSelection();
$selection->initFromHttpVars();

if(isSet($action) || isSet($get_action)) $action = (isset($get_action)?$get_action:$action);
else $action = "";

if(isSet($rep) && $action != "upload") $rep = utf8_decode($rep);
if(isSet($dest)) $dest = utf8_decode($dest);

// FILTER ACTION FOR DELETE
if(ConfService::useRecycleBin() && $action == "delete" && $rep != "/".ConfService::getRecycleBinDir())
{
	$action = "move";
	$dest = "/".ConfService::getRecycleBinDir();
	$dest_node = "AJAXPLORER_RECYCLE_NODE";
}
// FILTER ACTION FOR RESTORE
if(ConfService::useRecycleBin() &&  $action == "restore" && $rep == "/".ConfService::getRecycleBinDir())
{
	$originalRep = RecycleBinManager::getFileOrigin($selection->getUniqueFile());
	if($originalRep != "")
	{
		$action = "move";
		$dest = $originalRep;
	}
	
}

//--------------------------------------
// FIRST CHECK RIGHTS FOR THIS ACTION
//--------------------------------------
if(AuthService::usersEnabled())
{
	$loggedUser = AuthService::getLoggedUser();	
	switch ($action)
	{
		// NEEDS WRITE RIGHTS
		case "edit":
		case "copy":
		case "move":
		case "delete":
		case "rename":
		case "mkdir":
		case "mkfile":
			if($loggedUser == null || !$loggedUser->canWrite(ConfService::getCurrentRootDirIndex().""))
			{
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage(null, "You have no write permission!");
				AJXP_XMLWriter::requireAuth();
				AJXP_XMLWriter::close();
				exit(1);
			}
		break;		
		case "upload":		
		case "fancy_uploader":
			if($loggedUser == null || !$loggedUser->canWrite(ConfService::getCurrentRootDirIndex().""))
			{
				if(isSet($_FILES['Filedata']))
				{
					header('HTTP/1.0 ' . '415 Not authorized');
					die('Error 415 Not authorized!');
				}
				else
				{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess[207]);
					AJXP_XMLWriter::requireAuth();
					AJXP_XMLWriter::close();
				}
				exit(1);
			}
		break;
		
		// NEEDS READ RIGHTS
		case "voir":
		case "image_proxy":
		case "mp3_proxy":
		case "switch_root_dir":
		case "xml_listing":
		case "download":
		case "root_tree":		
			if($loggedUser == null || !$loggedUser->canRead(ConfService::getCurrentRootDirIndex().""))
			{
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage(null, $mess[208]);
				AJXP_XMLWriter::requireAuth();
				AJXP_XMLWriter::close();
				exit(1);
			}			
		break;
		// NO SPECIFIC RIGHTS
		case "display_action_bar":
		case "display_bookmark_bar":
		case "display_doc":
		default:
		break;
	}
}

//------------------------------------
//	SWITCH ON ACTION VARIABLE
//------------------------------------

switch($action)
{
	//------------------------------------
	//	ONLINE EDIT
	//------------------------------------
	case "edit";	
	//include($hautpage);
	$fic = utf8_decode($fic);
	if(isset($save) && $save==1)
	{
		$code=stripslashes($code);
		$code=str_replace("&lt;","<",$code);
		$fp=fopen(ConfService::getRootDir()."/$fic","w");
		fputs ($fp,$code);
		fclose($fp);
		Utils::enlever_controlM(ConfService::getRootDir()."/$fic");
		$logMessage = $mess[115];
		echo $logMessage;
	}
	else 
	{
		header("Content-type:text/plain");
		$fp=fopen(ConfService::getRootDir()."/$fic","r");
		while (!feof($fp))
		{
			$tmp=fgets($fp,4096);
			echo "$tmp";
		}
		fclose($fp);
	}
	exit(0);
	break;


	//------------------------------------
	//	HELP
	//------------------------------------
	case "help";
	include($hautpage);
	HTMLWriter::toolbar((isset($_GET["user"])?$_GET["user"]:"shared_bookmarks"));
	include("include/${langue}_help.htm");
	include($baspage);
	exit(0);
	break;


	//------------------------------------
	//	DOWNLOAD
	//------------------------------------

	case "download";
	$fic = utf8_decode($fic);
	$NomFichier = basename($fic);
	$taille=filesize(ConfService::getRootDir()."/$fic");
	header("Content-Type: application/force-download; name=\"$NomFichier\"");
	header("Content-Transfer-Encoding: binary");
	header("Content-Length: $taille");
	header("Content-Disposition: attachment; filename=\"$NomFichier\"");
	header("Expires: 0");
	header("Cache-Control: no-cache, must-revalidate");
	header("Pragma: no-cache");
	// For SSL websites there is a bug with IE see article KB 323308
	// therefore we must reset the Cache-Control and Pragma Header
	if (ConfService::getConf("USE_HTTPS")==1) 
	{
		if (preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT']))
		{
			header("Cache-Control:");
			header("Pragma:");
		}
	}
	
	readfile(ConfService::getRootDir()."/$fic");
	exit();
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
	if(!is_writable(ConfService::getRootDir()."/".$dest))
	{
		$errorMessage = $mess[38]." ".$dest." ".$mess[99];
		break;
	}
	if($action == "move" && !is_writable(dirname(ConfService::getRootDir()."/".$selection->getUniqueFile())))
	{
		$errorMessage.= "\n".$mess[38]." ".dirname($selection->getUniqueFile())." ".$mess[99];
		break;
	}
	
	$success = $error = array();
	$selectedFiles = $selection->getFiles();
	foreach ($selectedFiles as $selectedFile)
	{
		FS_Storage::copyOrMoveFile($dest, $selectedFile, $error, $success, ($action=="move"?true:false));
	}
	
	if(count($error)) $errorMessage = join("\n", $error);
	else $logMessage = join("\n", $success);
	$reload_current_node = true;
	if(isSet($dest_node)) $reload_dest_node = $dest_node;
	$reload_file_list = true;
	break;

	case "image_proxy":
	$fic = utf8_decode($fic);
	$taille=filesize(ConfService::getRootDir()."/$fic");
	header("Content-Type: ".Utils::getImageMimeType($fic)."; name=\"".basename($fic)."\"");
	header('Cache-Control: public');
	readfile(ConfService::getRootDir()."/$fic");
	exit(0);
	break;
	
	case "mp3_proxy":
	//$fic = utf8_decode($fic);
	$taille=filesize(ConfService::getRootDir()."/$fic");
	header("Content-Type: audio/mp3; name=\"".basename($fic)."\"");
	readfile(ConfService::getRootDir()."/$fic");
	exit(0);
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
	foreach ($selection->getFiles() as $selectedFile)
	{	
		$a_effacer=ConfService::getRootDir().$selectedFile;
		if($selectedFile == "" || $selectedFile == DIRECTORY_SEPARATOR)
		{
			$errorMessage = $mess[120];
			break;
		}
		if(file_exists($a_effacer))
		{
			FS_Storage::deldir($a_effacer);
			if(is_dir($a_effacer))
			{
				$logMessages[]="$mess[38] $selectedFile $mess[44].";
			}
			else 
			{
				$logMessages[]="$mess[34] $selectedFile $mess[44].";
			}
		}
		else 
		{
			$logMessages[]=$mess[100]." $selectedFile";
		}
	}
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
	$fic = utf8_decode($fic);
	$nom_fic=basename($fic);
	$fic_new=Utils::traite_nom_fichier(utf8_decode($fic_new));
	$old=ConfService::getRootDir()."/$fic";
	if(!is_writable($old))
	{
		$errorMessage = $mess[34]." ".$nom_fic." ".$mess[99];
		break;		
	}
	$new=dirname($old)."/".$fic_new;
	if($fic_new=="")
	{
		$errorMessage="$mess[37]";
		break;
	}
	if(file_exists($new))
	{
		$errorMessage="$fic_new $mess[43]"; 
		break;
	}
	if(!file_exists($old))
	{
		$errorMessage = $mess[100]." $nom_fic";
		break;
	}
	rename($old,$new);
	
	$logMessage="$fic $mess[41] $fic_new";
	$reload_current_node = true;
	$reload_file_list = basename($new);
	break;


	//------------------------------------
	//	CREER UN REPERTOIRE / CREATE DIR
	//------------------------------------

	case "mkdir";
	$err="";
	$messtmp="";
	$nomdir=Utils::traite_nom_fichier(utf8_decode($nomdir));
	if($nomdir=="")
	{
		$errorMessage="$mess[37]";
		break;
	}
	if(file_exists(ConfService::getRootDir()."/$rep/$nomdir"))
	{
		$errorMessage="$mess[40]"; 
		break;
	}
	if(!is_writable(ConfService::getRootDir()."/$rep"))
	{
		$errorMessage = $mess[38]." $rep ".$mess[99];
		break;
	}
	mkdir(ConfService::getRootDir()."/$rep/$nomdir",0775);
	$reload_file_list = $nomdir;
	$messtmp.="$mess[38] $nomdir $mess[39] ";
	if($rep=="") {$messtmp.="/";} else {$messtmp.="$rep";}
	$logMessage = $messtmp;
	$reload_current_node = true;
	break;

	//------------------------------------
	//	CREER UN FICHIER / CREATE FILE
	//------------------------------------

	case "mkfile";
	$err="";
	$messtmp="";
	$nomfic=Utils::traite_nom_fichier(utf8_decode($nomfic));
	if($nomfic=="")
	{
		$errorMessage="$mess[37]"; break;
	}
	if(file_exists(ConfService::getRootDir()."/$rep/$nomfic"))
	{
		$errorMessage="$mess[71]"; break;
	}
	if(!is_writable(ConfService::getRootDir()."/$rep"))
	{
		$errorMessage="$mess[38] $rep $mess[99]";break;
	}
	
	$fp=fopen(ConfService::getRootDir()."/$rep/$nomfic","w");
	if($fp)
	{
		if(eregi("\.html$",$nomfic)||eregi("\.htm$",$nomfic))
		{
			fputs($fp,"<html>\n<head>\n<title>Document sans titre</title>\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">\n</head>\n<body bgcolor=\"#FFFFFF\" text=\"#000000\">\n\n</body>\n</html>\n");
		}
		fclose($fp);
		$messtmp.="$mess[34] $nomfic $mess[39] ";
		if($rep=="") {$messtmp.="/";} else {$messtmp.="$rep";}
		$logMessage = $messtmp;
		$reload_file_list = $nomfic;
	}
	else
	{
		$err = 1;
		$errorMessage = "$mess[102] $rep/$nomfic (".$fp.")";
	}

	break;


	//------------------------------------
	//	UPLOAD
	//------------------------------------
	
	case "fancy_uploader":
	case "get_template":
	header("Content-type:text/html");
	if($get_action == "fancy_uploader"){
		include("include/html/fancy_tpl.html");
		include("include/html/bas.htm");
	}else{
		if(isset($template_name)){
			$mess = array_map("utf8_encode", $mess);
			include("include/html/".$template_name);
		}
	}
	exit(0);	
	break;
	

	case "upload":

	if($rep!=""){$rep_source="/$rep";}
	else $rep_source = "";
	$destination=ConfService::getRootDir().$rep_source;
	if(!is_writable($destination))
	{
		$errorMessage = "$mess[38] $rep $mess[99].";
		break;
	}	
	$logMessage = "";
	$fancyLoader = false;
	foreach ($_FILES as $boxName => $boxData)
	{
		if(substr($boxName, 0, 9) == "userfile_")
		{
			foreach($boxData as $usFileName=>$usFileValue)
			{
				$varName = "userfile_".$usFileName;
				$$varName = $usFileValue;
			}
		}
		else if($boxName == 'Filedata')
		{
			$fancyLoader = true;
			foreach($boxData as $usFileName=>$usFileValue)
			{
				$varName = "userfile_".$usFileName;
				$$varName = $usFileValue;
			}			
		}
		else 
		{
			continue;
		}
		if ($userfile_error != UPLOAD_ERR_OK)
		{
			$errorsArray = array();
			$errorsArray[UPLOAD_ERR_FORM_SIZE] = $errorsArray[UPLOAD_ERR_INI_SIZE] = "409 : File is too big! Max is".ini_get("upload_max_filesize");
			$errorsArray[UPLOAD_ERR_NO_FILE] = "410 : No file found on server!($boxName)";
			$errorsArray[UPLOAD_ERR_PARTIAL] = "410 : File is partial";
			if($userfile_error == UPLOAD_ERR_NO_FILE && ereg('Opera',$_SERVER['HTTP_USER_AGENT']))
			{
				// BEURK : Opera hack, do not display "no file found error"
				continue;
			}
			$errorMessage = $errorsArray[$userfile_error];
			continue;
		}
		if ($userfile_size!=0)
		{
			$taille_ko=$userfile_size/1024;
		}
		else
		{
			$taille_ko=0;
		}
		if ($userfile_tmp_name=="none")
		{
			$errorMessage=$mess[31];
			break;
		}
		if ($userfile_tmp_name!="none" && $userfile_size!=0)
		{
			if($fancyLoader) $userfile_name = utf8_decode($userfile_name);
			$userfile_name=Utils::traite_nom_fichier($userfile_name);
			if (!copy($userfile_tmp_name, "$destination/".$userfile_name))
			{
				$errorMessage=($fancyLoader?"411 ":"")."$mess[33] ".$userfile_name;
				break;
			}
			else
			{
				$logMessage.="$mess[34] ".$userfile_name." $mess[35] $rep";
			}
		}
	}
	if($fancyLoader)
	{
		header('HTTP/1.0 '.$errorMessage);
		die('Error '.$errorMessage);
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

	//---------------------------------------------------------------------------------------------------------------------------
	//	EMAIL URL
	//---------------------------------------------------------------------------------------------------------------------------
	case "email_url":
	$to      = $_POST['email_dest'];
	$subject = "URL sent by AjaXplorer";
	$message = "Hello, \n a friend of yours has sent you an URL to browse a folder in AjaXplorer : ";
	$message .= "\n\n Sender : ".$_POST["email_exp"];
	$message .= "\n The URL : ".$_POST["email_url"];
	$message .= "\n Additional Comment : ".wordwrap($_POST["email_comment"], 70);
	$headers = 'From: '.$webmaster_email. "\r\n" .
	'Reply-To: '.$webmaster_email . "\r\n" .
	'X-Mailer: PHP/' . phpversion();
	
	$res = @mail($to, $subject, $message, $headers);
	if($res)
	{
		$logMessage = $mess[111].$message;
	}
	else 
	{
		$errorMessage = $mess[112];
	}
	break;
	
	case "switch_root_dir":
	
	if(isSet($root_dir_index))
	{
		$dirList = ConfService::getRootDirsList();
		if(!isSet($dirList[$root_dir_index]))
		{
			$errorMessage = "Trying to switch to an unkown folder!";
			break;
		}
		else
		{
			ConfService::switchRootDir($root_dir_index);
			$logMessage = "Successfully Switched!";
		}
	}
	break;
	
	//------------------------------------
	//	XML LISTING
	//------------------------------------
	case "xml_listing":
	
	if(!isSet($rep) || $rep == "/") $rep = "";
	$searchMode = $fileListMode = $completeMode = false;
	if(isSet($mode)){
		if($mode == "search") $searchMode = true;
		else if($mode == "file_list") $fileListMode = true;
		else if($mode == "complete") $completeMode = true;
	}	
	$nom_rep = FS_Storage::initName($rep);
	$result = FS_Storage::listing($nom_rep, !($searchMode || $fileListMode));
	$reps = $result[0];
	AJXP_XMLWriter::header();
	foreach ($reps as $repIndex => $repName)
	{
		$link = "content.php?id=&ordre=nom&sens=1&action=xml_listing&rep=".$rep."/".$repName;
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
			$atts[] = "is_file=\"".(is_file($currentFile)?"oui":"non")."\"";
			$atts[] = "is_editable=\"".Utils::is_editable($currentFile)."\"";
			$atts[] = "is_image=\"".Utils::is_image($currentFile)."\"";
			if(Utils::is_image($currentFile))
			{
				list($width, $height, $type, $attr) = @getimagesize($currentFile);
				$atts[] = "image_type=\"".image_type_to_mime_type($type)."\"";
				$atts[] = "image_width=\"$width\"";
				$atts[] = "image_height=\"$height\"";
			}
			$atts[] = "is_mp3=\"".Utils::is_mp3($currentFile)."\"";
			$atts[] = "mimetype=\"".Utils::mimetype($currentFile, "type")."\"";
			$atts[] = "modiftime=\"".FS_Storage::date_modif($currentFile)."\"";
			$atts[] = "filesize=\"".Utils::roundSize(filesize($currentFile))."\"";
			$atts[] = "filename=\"".$rep."/".str_replace("&", "&amp;", $repIndex)."\"";
			$atts[] = "icon=\"".(is_file($currentFile)?$repName:"folder.png")."\"";
			
			$attributes = join(" ", $atts);
			$repName = $repIndex;
		}
		else 
		{
			$folderBaseName = str_replace("&", "&amp;", $repName);
			$folderFullName = "$rep/".$folderBaseName;
			$parentFolderName = $rep;
			if(!$completeMode){
				$attributes = "icon=\"images/foldericon.png\"  openicon=\"images/openfoldericon.png\" filename=\"$folderFullName\" parentname=\"$parentFolderName\" src=\"$link\" action=\"javascript:ajaxplorer.clickDir('".$folderFullName."','".$parentFolderName."',CURRENT_ID)\"";
			}
		}
		print(utf8_encode("<tree text=\"".str_replace("&", "&amp;", $repName)."\" $attributes>"));
		print("</tree>");
	}
	if($nom_rep == ConfService::getRootDir() && ConfService::useRecycleBin() && !$completeMode)
	{
		if($fileListMode)
		{
			print(utf8_encode("<tree text=\"".str_replace("&", "&amp;", $mess[122])."\" filesize=\"-\" is_file=\"non\" is_recycle=\"1\" mimetype=\"Trashcan\" modiftime=\"".FS_Storage::date_modif(ConfService::getRootDir()."/".ConfService::getRecycleBinDir())."\" filename=\"/".ConfService::getRecycleBinDir()."\" icon=\"trashcan.png\"></tree>"));
		}
		else 
		{
			// ADD RECYCLE BIN TO THE LIST
			print("<tree text=\"$mess[122]\" is_recycle=\"true\" icon=\"images/crystal/mimes/16/trashcan.png\"  openIcon=\"images/crystal/mimes/16/trashcan.png\" filename=\"/".ConfService::getRecycleBinDir()."\" action=\"javascript:ajaxplorer.clickDir('/".ConfService::getRecycleBinDir()."','/',CURRENT_ID)\"/>");
		}
	}
	AJXP_XMLWriter::close();
	exit(1);
	break;		
		
	case "display_bookmark_bar":
	//------------------------------------
	//	BOOKMARK BAR
	//------------------------------------
	header("Content-type:text/html");
	$bmUser = null;
	if(AuthService::usersEnabled() && AuthService::getLoggedUser() != null)
	{
		$bmUser = AuthService::getLoggedUser();
	}
	else if(!AuthService::usersEnabled())
	{
		$bmUser = new AJXP_User("shared");
	}
	if($bmUser == null) exit(1);
	if(isSet($_GET["bm_action"]) && isset($_GET["bm_path"]))
	{
		if($_GET["bm_action"] == "add_bookmark")
		{
			$bmUser->addBookMark($_GET["bm_path"]);
		}
		else if($_GET["bm_action"] == "delete_bookmark")
		{
			$bmUser->removeBookmark($_GET["bm_path"]);
		}
	}
	if(AuthService::usersEnabled() && AuthService::getLoggedUser() != null)
	{
		$bmUser->save();
		AuthService::updateUser($bmUser);
	}
	else if(!AuthService::usersEnabled())
	{
		$bmUser->save();
	}
	HTMLWriter::bookmarkBar($bmUser->getBookMarks());
	session_write_close();
	exit(1);
	break;
		
	case "save_user_pref":
		$userObject = AuthService::getLoggedUser();
		if($userObject == null) exit(1);
		$i = 0;
		while(isSet($_GET["pref_name_".$i]) && isSet($_GET["pref_value_".$i]))
		{
			$prefName = $_GET["pref_name_".$i];
			$prefValue = $_GET["pref_value_".$i];
			if($prefName != "password")
			{
				$userObject->setPref($prefName, $prefValue);
				$userObject->save();
				AuthService::updateUser($userObject);
				setcookie("AJXP_$prefName", $prefValue);
			}
			else
			{
				AuthService::updatePassword($userObject->getId(), $prefValue);
			}
			$i++;
		}
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::sendMessage("Done($i)", null);
		AJXP_XMLWriter::close();
		exit(1);
	break;
	
	case "display_doc":
	{
		echo HTMLWriter::getDocFile($_GET["doc_file"]);
		exit();
	}
	
	
		
	//------------------------------------
	//	DEFAUT
	//------------------------------------

	default;
	break;
}



AJXP_XMLWriter::header();

if(isset($logMessage) || isset($errorMessage))
{
	AJXP_XMLWriter::sendMessage((isSet($logMessage)?$logMessage:null), (isSet($errorMessage)?$errorMessage:null));
}

if(isset($requireAuth))
{
	AJXP_XMLWriter::requireAuth();
}

if(isset($reload_current_node) && $reload_current_node == "true")
{
	AJXP_XMLWriter::reloadCurrentNode();
}

if(isset($reload_dest_node) && $reload_dest_node != "")
{
	AJXP_XMLWriter::reloadNode($reload_dest_node);
}

if(isset($reload_file_list))
{
	AJXP_XMLWriter::reloadFileList($reload_file_list);
}

AJXP_XMLWriter::close();



session_write_close();
?>
