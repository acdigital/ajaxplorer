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
 * Description : Basic plugin, defined by it's manifest.xml
 */
class AJXP_Plugin{
	protected $baseDir;
	protected $id;
	protected $name;
	protected $type;
	/**
	 * XPath query
	 *
	 * @var DOMXPath
	 */
	protected $xPath;
	protected $manifestLoaded = false;
	protected $actions;
	protected $options;
	/**
	 * The manifest.xml loaded
	 *
	 * @var DOMDocument
	 */
	private $manifestDoc;
	public function __construct($id, $baseDir){
		$this->baseDir = $baseDir;
		$this->id = $id;
		$split = explode(".", $id);
		$this->type = $split[0];
		$this->name = $split[1];
		$this->actions = array();
	}
	public function init($options){
		$this->options = $options;
	}
	protected function loadActionsFromManifest(){
		$actionFiles = $this->xPath->query("actions_definition");		
		foreach ($actionFiles as $actionFileNode){
			$data = $this->nodeAttrToHash($actionFileNode);
			$filename = $data["filename"] OR "";
			$include = $data["include"] OR "*";
			$exclude = $data["exclude"] OR "";			
			if(!is_file(INSTALL_PATH."/".$filename)) continue;			
			if($include != "*") $include = explode(",", $include);
			if($exclude != "") $exclude = explode(",", $exclude);			
			$this->initXmlActionsFile(INSTALL_PATH."/".$filename, $include, $exclude);
		}
	}
	public function initXmlActionsFile($xmlFile, $include="*", $exclude=""){		
		$actionDoc = new DOMDocument();
		$actionDoc->load($xmlFile);
		$actionXpath = new DOMXPath($actionDoc);
		$actionNodes = $actionXpath->query("actions/action");				
		foreach ($actionNodes as $actionNode){
			$actionData = array();			
			$actionData["XML"] = $actionDoc->saveXML($actionNode);			
			$names = $actionXpath->query("@name", $actionNode);
			if($names->length){
				$name = $names->item(0)->value;
			}else{
				continue;
			}
			if(is_array($include)){
				if(!in_array($name, $include)) continue;
			}
			if(is_array($exclude)){
				if(in_array($name, $exclude)) continue;
			}
			$callbacks = $actionXpath->query("processing/serverCallback/@methodName", $actionNode);
			if($callbacks->length){
				$actionData["callback"] = $callbacks->item(0)->value;
			}
			$rightContextNodes = $actionXpath->query("rightsContext",$actionNode);
			if($rightContextNodes->length){
				$rightContext = $rightContextNodes->item(0);
				$actionData["rights"] = $this->nodeAttrToHash($rightContext);
			}
			$this->actions[$name] = $actionData;
		}
	}
	public function loadManifest(){
		$file = $this->baseDir."/manifest.xml";
		if(!is_file($file)) {
			return;
		}
		$this->manifestDoc = new DOMDocument();
		try{
			$this->manifestDoc->load($file);
		}catch (Exception $e){
			throw $e;
		}
		$this->xPath = new DOMXPath($this->manifestDoc);
		$this->manifestLoaded = true;
	}
	public function getManifestRawContent($xmlNodeName = ""){
		if($xmlNodeName == ""){
			return $this->manifestDoc->saveXML($this->manifestDoc->documentElement);
		}else{
			$buffer = "";
			$nodes = $this->xPath->query($xmlNodeName);
			foreach ($nodes as $node){
				$buffer .= $this->manifestDoc->saveXML($node);
			}
			return $buffer;
		}
	}
	public function findDependencies(){
		$depPaths = "dependencies/pluginResources/@pluginName";
		$nodes = $this->xPath->query($depPaths);
		$deps = array();
		foreach ($nodes as $attr){
			$deps[] = $attr->value;
		}
		return $deps;
	}
	public function getClassFile(){
		$files = $this->xPath->query("class_definition");
		if(!$files->length) return false;
		return $this->nodeAttrToHash($files->item(0));
	}
	public function manifestLoaded(){
		return $this->manifestLoaded;
	}
	public function getId(){
		return $this->id;
	}
	public function getName(){
		return $this->name;
	}
	public function getType(){
		return $this->type;
	}
	public function getBaseDir(){
		return $this->baseDir;
	}
	/**
	 * Transform a simple node and its attributes to a hash
	 *
	 * @param DOMNode $node
	 */
	private function nodeAttrToHash($node){
		$hash = array();
		$attributes  = $node->attributes;
		if($attributes!=null){
			foreach ($attributes as $domAttr){
				$hash[$domAttr->name] = $domAttr->value;
			}
		}
		return $hash;
	}
}
?>