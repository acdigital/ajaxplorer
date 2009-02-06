<?php

class Utils
{
	
	function securePath($path)
	{
		if($path == null) $path = ""; 
		//
		// REMOVE ALL "../" TENTATIVES
		//
		$dirs = explode('/', $path);
		for ($i = 0; $i < count($dirs); $i++)
		{
			if ($dirs[$i] == '.' or $dirs[$i] == '..')
			{
				$dirs[$i] = '';
			}
		}
		// rebuild safe directory string
		$path = implode('/', $dirs);
		
		//
		// REPLACE DOUBLE SLASHES
		//
		while (eregi('//', $path)) 
		{
			$path = str_replace('//', '/', $path);
		}
		return $path;
	}
	
	function parseFileDataErrors($boxData, $errorCodes)
	{
		$mess = ConfService::getMessages();
		$userfile_error = $boxData["error"];		
		$userfile_tmp_name = $boxData["tmp_name"];
		$userfile_size = $boxData["size"];
		if ($userfile_error != UPLOAD_ERR_OK)
		{
			$errorsArray = array();
			$errorsArray[UPLOAD_ERR_FORM_SIZE] = $errorsArray[UPLOAD_ERR_INI_SIZE] = ($errorCodes?"409 ":"")."File is too big! Max is".ini_get("upload_max_filesize");
			$errorsArray[UPLOAD_ERR_NO_FILE] = ($errorCodes?"410 ":"")."No file found on server!";
			$errorsArray[UPLOAD_ERR_PARTIAL] = ($errorCodes?"410 ":"")."File is partial";
			$errorsArray[UPLOAD_ERR_INI_SIZE] = ($errorCodes?"410 ":"")."No file found on server!";
			if($userfile_error == UPLOAD_ERR_NO_FILE)
			{
				// OPERA HACK, do not display "no file found error"
				if(!ereg('Opera',$_SERVER['HTTP_USER_AGENT']))
				{
					return $errorsArray[$userfile_error];				
				}
			}
			else
			{
				return $errorsArray[$userfile_error];
			}
		}
		if ($userfile_tmp_name=="none" || $userfile_size == 0)
		{
			return ($errorCodes?"410 ":"").$mess[31];
		}
		return null;
	}
	
	function mergeArrays($t1,$t2)
	{
		$liste = array();
		$tab1=$t1; $tab2=$t2;
		if(is_array($tab1)) {while (list($cle,$val) = each($tab1)) {$liste[$cle]=$val;}}
		if(is_array($tab2)) {while (list($cle,$val) = each($tab2)) {$liste[$cle]=$val;}}
		return $liste;
	}
	
	function removeWinReturn($fileContent)
	{
		$fileContent = str_replace(chr(10), "", $fileContent);
		$fileContent = str_replace(chr(13), "", $fileContent);
		return $fileContent;
		/*
		$fic=file($fileName);
		$fp=fopen($fileName,"w");
		while (list ($cle, $val) = each ($fic))
		{
			$val=str_replace(CHR(10),"",$val);
			$val=str_replace(CHR(13),"",$val);
			fputs($fp,"$val\n");
		}
		fclose($fp);
		*/
	}
	
	function tipsandtricks()
	{
		$tips = array();
		$tips[] = "DoubleClick in the list to directly download a file or to open a folder.";
		$tips[] = "When the 'Edit' button is enabled (on text files), you can directly edit the selected file online.";
		$tips[] = "Type directly a folder URL in the location bar then hit 'ENTER' to go to a given folder.";
		$tips[] = "Use MAJ+Click and CTRL+Click to perform multiple selections in the list.";
		$tips[] = "Use the Bookmark button to save your frequently accessed locations in the bookmark bar.";
		$tips[] = "Use the TAB button to navigate through the main panels (tree, list, location bar).";
		$tips[] = "Use the 'u' key to go to the parent directory.";
		$tips[] = "Use the 'h' key to refresh current listing.";
		$tips[] = "Use the 'b' key to bookmark current location to your bookmark bar.";
		$tips[] = "Use the 'l' key to open Upload Form.";
		$tips[] = "Use the 'd' key to create a new directory in this folder.";
		$tips[] = "Use the 'f' key to create a new file in this folder.";
		$tips[] = "Use the 'r' key to rename a file.";
		$tips[] = "Use the 'c' key to copy one or more file or folders to a different folder.";
		$tips[] = "Use the 'm' key to move one or more file or folders to a different folder.";
		$tips[] = "Use the 's' key to delete one or more file or folders.";
		$tips[] = "Use the 'e' key to edit a file or view an image.";
		$tips[] = "Use the 'o' key to download a file to your hard drive.";
		return $tips[array_rand($tips, 1)];
	}
	
	function processFileName($fileName)
	{
		$max_caracteres = ConfService::getConf("MAX_CHAR");
		// Don't allow those chars : ' " & , ; / \ ` < > : * ? | ! + ^ 
		$fileName=stripslashes($fileName);
		// Unless I'm mistaken, ' is a valid char for a file name (under both Linux and Windows).
		// I've found this regular expression for Windows file name validation, not sure how it applies for linux :
		// ^[^\\\./:\*\?\"<>\|]{1}[^\\/:\*\?\"<>\|]{0,254}$   This reg ex remove ^ \ . / : * ? " < > | as the first char, and (same thing but . for any other char), and it limits to 254 chars (could use max_caracteres instead)
		// Anyway, here is the corrected version of the big str_replace calls below that doesn't kill UTF8 encoding
		$fileNameTmp=ereg_replace("[\"&,;/`<>:\*\|\?!\^]", "", $fileName);
		return substr($fileNameTmp, 0, $max_caracteres);
	}
	
	function mimetype($fileName,$mode, $isDir)
	{
		$mess = ConfService::getMessages();
		if($isDir){$image="folder.png";$typeName=$mess[8];}
		else if(eregi("\.mid$",$fileName)){$image="midi.png";$typeName=$mess[9];}
		else if(eregi("\.txt$",$fileName)){$image="txt2.png";$typeName=$mess[10];}
		else if(eregi("\.sql$",$fileName)){$image="txt2.png";$typeName=$mess[10];}
		else if(eregi("\.js$",$fileName)){$image="javascript.png";$typeName=$mess[11];}
		else if(eregi("\.gif$",$fileName)){$image="image.png";$typeName=$mess[12];}
		else if(eregi("\.jpg$",$fileName)){$image="image.png";$typeName=$mess[13];}
		else if(eregi("\.html$",$fileName)){$image="html.png";$typeName=$mess[14];}
		else if(eregi("\.htm$",$fileName)){$image="html.png";$typeName=$mess[15];}
		else if(eregi("\.rar$",$fileName)){$image="archive.png";$typeName=$mess[60];}
		else if(eregi("\.gz$",$fileName)){$image="zip.png";$typeName=$mess[61];}
		else if(eregi("\.tgz$",$fileName)){$image="archive.png";$typeName=$mess[61];}
		else if(eregi("\.z$",$fileName)){$image="archive.png";$typeName=$mess[61];}
		else if(eregi("\.ra$",$fileName)){$image="video.png";$typeName=$mess[16];}
		else if(eregi("\.ram$",$fileName)){$image="video.png";$typeName=$mess[17];}
		else if(eregi("\.rm$",$fileName)){$image="video.png";$typeName=$mess[17];}
		else if(eregi("\.pl$",$fileName)){$image="source_pl.png";$typeName=$mess[18];}
		else if(eregi("\.zip$",$fileName)){$image="zip.png";$typeName=$mess[19];}
		else if(eregi("\.wav$",$fileName)){$image="sound.png";$typeName=$mess[20];}
		else if(eregi("\.php$",$fileName)){$image="php.png";$typeName=$mess[21];}
		else if(eregi("\.php3$",$fileName)){$image="php.png";$typeName=$mess[22];}
		else if(eregi("\.phtml$",$fileName)){$image="php.png";$typeName=$mess[22];}
		else if(eregi("\.exe$",$fileName)){$image="exe.png";$typeName=$mess[50];}
		else if(eregi("\.bmp$",$fileName)){$image="image.png";$typeName=$mess[56];}
		else if(eregi("\.png$",$fileName)){$image="image.png";$typeName=$mess[57];}
		else if(eregi("\.css$",$fileName)){$image="css.png";$typeName=$mess[58];}
		else if(eregi("\.mp3$",$fileName)){$image="sound.png";$typeName=$mess[59];}
		else if(eregi("\.xls$",$fileName)){$image="spreadsheet.png";$typeName=$mess[64];}
		else if(eregi("\.doc$",$fileName)){$image="document.png";$typeName=$mess[65];}
		else if(eregi("\.pdf$",$fileName)){$image="pdf.png";$typeName=$mess[79];}
		else if(eregi("\.mov$",$fileName)){$image="video.png";$typeName=$mess[80];}
		else if(eregi("\.avi$",$fileName)){$image="video.png";$typeName=$mess[81];}
		else if(eregi("\.mpg$",$fileName)){$image="video.png";$typeName=$mess[82];}
		else if(eregi("\.mpeg$",$fileName)){$image="video.png";$typeName=$mess[83];}
		else if(eregi("\.swf$",$fileName)){$image="flash.png";$typeName=$mess[91];}
		else {$image="mime_empty.png";$typeName=$mess[23];}
		if($mode=="image"){return $image;} else {return $typeName;}
	}
		
	function getAjxpMimes($keyword){
		if($keyword == "editable"){
			return "txt,sql,php,php3,phtml,htm,html,cgi,pl,js,css,inc,xml,xsl,java";
		}else if($keyword == "image"){
			return "png,bmp,jpg,jpeg,gif";
		}else if($keyword == "audio"){
			return "mp3";
		}else if($keyword == "zip"){
			if(ConfService::zipEnabled()){
				return "zip";
			}else{
				return "none_allowed";
			}
		}
		return "";
	}
		
	function is_image($fileName)
	{
		if(eregi("\.png$|\.bmp$|\.jpg$|\.jpeg$|\.gif$",$fileName)){
			return 1;
		}
		return 0;
	}
	
	function is_mp3($fileName)
	{
		if(eregi("\.mp3$",$fileName)) return 1;
		return 0;
	}
	
	function getImageMimeType($fileName)
	{
		if(eregi("\.jpg$|\.jpeg$",$fileName)){return "image/jpeg";}
		else if(eregi("\.png$",$fileName)){return "image/png";}	
		else if(eregi("\.bmp$",$fileName)){return "image/bmp";}	
		else if(eregi("\.gif$",$fileName)){return "image/gif";}	
	}
	
	function roundSize($filesize)
	{
		$size_unit = ConfService::getConf("SIZE_UNIT");
		if($filesize < 0){
			$filesize = sprintf("%u", $filesize);
		}
		if ($filesize >= 1073741824) {$filesize = round($filesize / 1073741824 * 100) / 100 . " G".$size_unit;}
		elseif ($filesize >= 1048576) {$filesize = round($filesize / 1048576 * 100) / 100 . " M".$size_unit;}
		elseif ($filesize >= 1024) {$filesize = round($filesize / 1024 * 100) / 100 . " K".$size_unit;}
		else {$filesize = $filesize . " ".$size_unit;}
		if($filesize==0) {$filesize="-";}
		return $filesize;
	}
	
	function showHiddenFiles($fileName)
	{
		$showhidden = ConfService::getConf("SHOW_HIDDEN");
		if(substr($fileName,0,1)=="." && $showhidden==0) {
			return 0;
		}
		return 1;
	}
	
	/**
	 * Convert a shorthand byte value from a PHP configuration directive to an integer value
	 * @param    string   $value
	 * @return   int
	 */
	function convertBytes( $value ) 
	{
	    if ( is_numeric( $value ) ) 
	    {
	        return $value;
	    } 
	    else 
	    {
	        $value_length = strlen( $value );
	        $qty = substr( $value, 0, $value_length - 1 );
	        $unit = strtolower( substr( $value, $value_length - 1 ) );
	        switch ( $unit ) 
	        {
	            case 'k':
	                $qty *= 1024;
	                break;
	            case 'm':
	                $qty *= 1048576;
	                break;
	            case 'g':
	                $qty *= 1073741824;
	                break;
	        }
	        return $qty;
	    }
	}

	function xmlEntities($string){
		return str_replace(array("&", "<",">"), array("&amp;", "&lt;","&gt;"), $string);
	}


}

?>