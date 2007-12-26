<?php
//--------------------------------------------------------
//
//	ajaXplorer v2.2
//
//	Charles du Jeu
//	http://sourceforge.net/projects/ajaxplorer/
//
//--------------------------------------------------------

//--------------------------------------------------------
//	CONFIGURATION IS HERE!
//--------------------------------------------------------


/* DEFAULT LANGUAGE
/* French : fr
/* English : en
/*************************/
$available_languages = array("en"=>"English", "fr"=>"Français", "nl"=>"Nederlands", "es"=>"Español");
$dft_langue="en";

define("ENABLE_USERS", 1);
define("ADMIN_PASSWORD", "admin");
define("ALLOW_GUEST_BROWSING", 0);
define("AUTH_MODE", "ajaxplorer"); // "ajaxplorer", "local_http", "remote"

define("AUTH_MODE_REMOTE_SERVER", "www.yourdomain.com"); //
define("AUTH_MODE_REMOTE_URL", "/answering_script.php"); // 
define("AUTH_MODE_REMOTE_USER", ""); // 
define("AUTH_MODE_REMOTE_PASSWORD", ""); // 
define("AUTH_MODE_REMOTE_PORT", 80); // 
define("AUTH_MODE_REMOTE_SESSION_NAME", "session_id"); // 

/* ABSOLUTE PATH(S) AND PUBLIC NAME OF THE FILES TO EXPLORE
/* You can add as many as you want,  
/* Just increment the "$REPOSITORIES" index.
/*********************************************************/
$REPOSITORIES[0] = array(
	"PATH"			=>	realpath(dirname(__FILE__)."/../../files"), 
	"DISPLAY"		=>	"Default Files", 
	"ACCESS"		=>	"fs", 
	"CREATE"		=>	true,
	"RECYCLE_BIN" 	=> 	'recycle_bin'
);
/*
$REPOSITORIES[1] = array(
	"PATH"			=>	"/home/username/example/public/files",
	"DISPLAY"		=>"Web Files", 
	"ACCESS"		=>	"filesystem", 
	"CREATE"		=>	false,
	"RECYCLE_BIN" 	=> 	''
);
*/
/*
$REPOSITORIES[2] = array(
	"PATH"=>"C:\your\location3\on\windows", 
	"DISPLAY"=>"Windows Documents"
);
*/
// UNITE DE TAILLE DES FICHIER (octets "o", bytes "b")
// (Unit of file size, "o" or "b")
$size_unit="o";

// NOMBRE DE CARACTERES MAXIMUM POUR LES NOMS DE FICHIER
// (max number chars for file and directory names)

$max_caracteres=50;

// AFFICHAGE DES FICHIERS CACHES : oui=1, non=0 (UN FICHIER CACHE COMMENCE PAR UN POINT)
// (Show hidden files, yes=1, no=0)

$showhidden=0;

$upload_max_number = 6;

/* WEBMASTER EMAIL
/*********************************/
$webmaster_email = "webmaster@yourdomain.com";

/* RECYCLE BIN : leave blank if you do not want to use it.
/********************************/
$recycle_bin = "recycle_bin";

/*  HTTPS DOMAIN? (USED TO CORRECT A BUG IN IE)
/**************************************************/
$use_https=false;





//------------------------------------------------------
//		DO NOT CHANGE THESE VARIABLES BELOW
//------------------------------------------------------



$installPath = realpath(dirname(__FILE__)."/../..");
define("INSTALL_PATH", $installPath);
define("USERS_DIR", $installPath."/server/users");
define("SERVER_ACCESS", "content.php");
define("ADMIN_ACCESS", "admin.php");
define("IMAGES_FOLDER", "client/images");
define("CLIENT_RESOURCES_FOLDER", "client");
define("SERVER_RESOURCES_FOLDER", "server/classes");
define("DOCS_FOLDER", "client/doc");

define("OLD_USERS_DIR", $installPath."/bookmarks");
define("INITIAL_ADMIN_PASSWORD", "admin");

// PAGES D'ENTETE ET DE BAS DE PAGE
// (header and footer files )
$baspage=CLIENT_RESOURCES_FOLDER."/html/bottom.html";


?>