if(dynamicLibLoading)
{
	jQuery.noConflict();
	document.write('<script language="javascript" type="text/javascript" src="include/js/lib/jquery/jquery.corner.js"></script><script language="javascript" type="text/javascript" src="include/js/lib/scriptaculous/src/scriptaculous.js?load=builder,effects,dragdrop"></script><script language="javascript" type="text/javascript" src="include/js/lib/leightbox/lightbox.js"></script><script language="javascript" type="text/javascript" src="include/js/ajaxplorer/class.Connexion.js"></script><script language="javascript" type="text/javascript" src="include/js/ajaxplorer/class.Modal.js"></script>');
}

Ajaxplorer = Class.create({

	initialize: function(loadRep, usersEnabled, loggedUser, rootDirId, rootDirsList, defaultDisplay)
	{	
		this._initLoadRep = loadRep;
		this.usersEnabled = usersEnabled;
		this._initLoggedUser = loggedUser;
		this._initRootDirsList = rootDirsList;
		this._initRootDirId = rootDirId;
		this._initDefaultDisp = ((defaultDisplay && defaultDisplay!='')?defaultDisplay:'list');
		this.histCount=0;
		if(!this.usersEnabled) this.rootDirId = rootDirId;
		modal.setLoadingStepCounts(8);
		this.initTemplates();
		modal.initForms();
		this.initObjects();
	},
	
	initTemplates:function(){
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'get_template');
		connexion.onComplete = function(transport){
			$('all_forms').innerHTML += transport.responseText;
		}
		connexion.addParameter('template_name', 'forms_tpl.html');
		connexion.sendSync();
		modal.updateLoadingProgress('Dialogs loaded');
		$('originalUploadForm').hide();
	
		connexion.onComplete = function(transport){
			document.body.innerHTML += transport.responseText;
		}
		connexion.addParameter('template_name', 'gui_tpl.html');
		connexion.sendSync();
		modal.updateLoadingProgress('Main template loaded');	
	},
	
	initObjects: function(){
		loadRep = this._initLoadRep;
		crtUser = this._initCrtUser;
		rootDirName = this._initRootDir;
		//modal.updateLoadingProgress('Libraries loaded');
		if(!this.usersEnabled)
		{
			var fakeUser = new User("shared");
			fakeUser.setActiveRepository(this._initRootDirId, 1, 1);
			fakeUser.setRepositoriesList(this._initRootDirsList);
			this.actionBar = new ActionsManager($("action_bar"), this.usersEnabled, fakeUser, this);
			this.foldersTree = new FoldersTree('tree_container', this._initRootDirsList[this._initRootDirId], 'content.php?action=xml_listing', this);
			this.refreshRootDirMenu(this._initRootDirsList, this._initRootDirId);
		}
		else
		{
			this.actionBar = new ActionsManager($("action_bar"), this.usersEnabled, null, this);
			this.foldersTree = new FoldersTree('tree_container', 'No Repository', 'content.php?action=xml_listing', this);
			if(this._initLoggedUser)
			{
				this.getLoggedUserFromServer();
			}
		}
		
		this.actionBar.init();
		this.actionBar.updateDisplayButton(this._initDefaultDisp);
		modal.updateLoadingProgress('ActionBar Initialized');
		
		this.filesList = new FilesList($("selectable_div"), 
										true, 
										["StringDirFile", "NumberKo", "String", "MyDate"], 
										null, 
										this, 
										this._initDefaultDisp);	
	
		this.contextMenu = new Proto.Menu({
		  selector: '', // context menu will be shown when element with class name of "contextmenu" is clicked
		  className: 'menu desktop', // this is a class which will be attached to menu container (used for css styling)
		  menuItems: [],
		  fade:true,
		  zIndex:2000
		});
		var protoMenu = this.contextMenu;
		protoMenu.options.beforeShow = function(e){setTimeout(function(){
		  	this.options.menuItems = ajaxplorer.actionBar.getContextActions(Event.element(e));
		  	this.refreshList();
		  }.bind(protoMenu),0);};
	
		this.filesList.setContextualMenu(this.contextMenu);
		this.foldersTree.setContextualMenu(this.contextMenu);
		this.actionBar.setContextualMenu(this.contextMenu);
		  
		this.sEngine = new SearchEngine("search_container", "search_txt","search_results", "search_button", this);
		this.infoPanel = new InfoPanel("info_panel");
		this.messageBox = $('message_div');
		this.initGUI();	
		modal.updateLoadingProgress('GUI Initialized');
		this.initFocusBehaviours();
		this.initTabNavigation();
		modal.updateLoadingProgress('Navigation loaded');
		this.focusOn(this.foldersTree);
		document.onkeydown = function(event){		
			if(event == null)
			{
				event = window.event;				
				if(event.keyCode == 9){return false;}
			}		
		};
		this.blockShortcuts = false;
		this.blockNavigation = false;
		
		new AjxpAutocompleter("current_path", "autocomplete_choices");
		if(Prototype.Browser.Gecko){
			this.history = new Proto.History(function(hash){
				this.goTo(this.historyHashToPath(hash));
			}.bind(this));
		}
		this.goTo(loadRep);	
	},
	
	getLoggedUserFromServer: function(){
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'logged_user');
		connexion.onComplete = function(transport){this.logXmlUser(transport.responseXML);}.bind(this);
		connexion.sendAsync();	
	},
	
	logXmlUser: function(xmlResponse){
		this.user = null;
		try{			
			var childs = xmlResponse.documentElement.childNodes;		
			for(var i=0; i<childs.length;i++){
				if(childs[i].tagName == "user"){
					var userId = childs[i].getAttribute('id');
					childs = childs[i].childNodes;
					break;
				}		
			}	
			if(userId) this.user = new User(userId, childs);
		}catch(e){alert('Error parsing XML for user : '+e);}
		
		if(this.user != null)
		{
			this.rootDirId = this.user.getActiveRepository();
			var repList = this.user.getRepositoriesList();
			this.foldersTree.changeNodeLabel(this.foldersTree.getRootNodeId(), repList.get(this.user.getActiveRepository()));
			this.refreshRootDirMenu(this.user.getRepositoriesList(), this.user.getActiveRepository());
		}
		else
		{
			this.refreshRootDirMenu(null, null);
			this.foldersTree.changeNodeLabel(this.foldersTree.getRootNodeId(), 'No Repository');
		}
		this.actionBar.setUser(this.user);
		this.foldersTree.setCurrentNodeName(this.foldersTree.getRootNodeId());
		this.foldersTree.reloadCurrentNode();
		this.filesList.loadXmlList('/');
		this.actionBar.loadBookmarks();
	},
		
	goTo: function(rep, selectFile){
		this.actionBar.updateLocationBar(rep);
		this.actionBar.update(true);
		this.foldersTree.goToDeepPath(rep);	
		this.filesList.loadXmlList(rep, selectFile);	
	},
	
	clickDir: function(url, parent_url, objectName){
		if(this.actionBar.treeCopyActive)
		{
			if(this.actionBar.treeCopyActionDest) this.actionBar.treeCopyActionDest.each(function(element){element.value = url});
			if(this.actionBar.treeCopyActionDestNode) this.actionBar.treeCopyActionDestNode.each(function(element){element.value = objectName});
		}
		else
		{
			this.getFoldersTree().clickDir(url, parent_url, objectName);
			this.getFilesList().loadXmlList(url);
			this.getActionBar().updateLocationBar(url);
			this.getActionBar().update(true);
		}
	},
	
	updateHistory: function(path){
		if(this.history) this.history.historyLoad(this.pathToHistoryHash(path));
	},
	
	pathToHistoryHash: function(path){
		document.title = 'AjaXplorer - '+(getBaseName(path)?getBaseName(path):'/');
		if(!this.pathesHash){
			this.pathesHash = new Hash();
			this.histCount = -1;
		}
		var foundKey;
		this.pathesHash.each(function(pair){
			if(pair.value == path) foundKey = pair.key;
		});
		if(foundKey != undefined) return foundKey;
	
		this.histCount++;
		this.pathesHash.set(this.histCount, path);
		return this.histCount;
	},
	
	historyHashToPath: function(hash){
		if(!this.pathesHash) return "/";
		var path = this.pathesHash.get(hash);
		if(path == undefined) return "/";
		return path;
	},
	
	
	cancelCopyOrMove: function(){
		this.foldersTree.setTreeInNormalMode();
		this.foldersTree.selectCurrentNodeName();
		this.actionBar.treeCopyActive = false;
		hideLightBox();
		return false;
	},
	
	disableShortcuts: function(){
		this.blockShortcuts = true;
	},
	
	enableShortcuts: function(){
		this.blockShortcuts = false;
	},
	
	disableNavigation: function(){
		this.blockNavigation = true;
	},
	
	enableNavigation: function(){
		this.blockNavigation = false;
	},
	
	getActionBar: function(){
		return this.actionBar;
	},
	
	getFilesList: function(){
		return this.filesList;
	},
	
	getFoldersTree: function(){
		return this.foldersTree;
	},
	
	closeMessageDiv: function(){
		if(this.messageDivOpen)
		{
			new Effect.Fade(this.messageBox);
			this.messageDivOpen = false;
		}
	},
	
	tempoMessageDivClosing: function(){
		this.messageDivOpen = true;
		setTimeout('ajaxplorer.closeMessageDiv()', 3000);
	},
	
	displayMessage: function(messageType, message){
		message = message.replace(new RegExp("(\\n)", "g"), "<br>");
		if(messageType == "ERROR"){ this.messageBox.removeClassName('logMessage');  this.messageBox.addClassName('errorMessage');}
		else { this.messageBox.removeClassName('errorMessage');  this.messageBox.addClassName('logMessage');}
		$('message_content').innerHTML = message;
		// appear at bottom of content panel
		var containerOffset = Position.cumulativeOffset($('content_pane'));
		var containerDimensions = $('content_pane').getDimensions();
		var boxHeight = $(this.messageBox).getHeight();
		var topPosition = containerOffset[1] + containerDimensions.height - boxHeight - 20;
		var boxWidth = parseInt(containerDimensions.width * 90/100);
		var leftPosition = containerOffset[0] + parseInt(containerDimensions.width*5/100);
		this.messageBox.style.top = topPosition+'px';
		this.messageBox.style.left = leftPosition+'px';
		this.messageBox.style.width = boxWidth+'px';
		jQuery(this.messageBox).corner("round");
		new Effect.Appear(this.messageBox);
		this.tempoMessageDivClosing();
	},
	
	initFocusBehaviours: function(){
		$('topPane').observe("click", function(){
			ajaxplorer.focusOn(ajaxplorer.foldersTree);
		});
		$('content_pane').observe("click", function(){
			ajaxplorer.focusOn(ajaxplorer.filesList);
		});	
		$('action_bar').observe("click", function(){
			ajaxplorer.focusOn(ajaxplorer.actionBar);
		});
		$('search_div').observe("click", function(){
			ajaxplorer.focusOn(ajaxplorer.sEngine);
		});
		
	},
	
	focusOn : function(object){
		var objects = [this.foldersTree, this.sEngine, this.filesList, this.actionBar];
		objects.each(function(obj){
			if(obj != object) obj.blur();
		});
		object.focus();
	},
	
	
	initTabNavigation: function(){
		var objects = [this.foldersTree, this.filesList, this.actionBar];		
		// ASSIGN OBSERVER
		Event.observe(document, "keydown", function(e)
		{
			if (e == null) e = document.parentWindow.event;
			if(e.keyCode == Event.KEY_TAB)
			{			
				if(this.blockNavigation) return;			
				var shiftKey = e['shiftKey'];
				for(i=0; i<objects.length;i++)
				{
					if(objects[i].hasFocus)
					{
						objects[i].blur();
						var nextIndex;
						if(shiftKey)
						{
							if(i>0) nextIndex=i-1;
							else nextIndex = (objects.length) - 1;
						}
						else
						{
							if(i<objects.length-1)nextIndex=i+1;
							else nextIndex = 0;
						}
						objects[nextIndex].focus();
						break;
					}
				}
				return false;
			}
			if(this.blockShortcuts || e['ctrlKey']) return true;
			if(e.keyCode > 90 || e.keyCode < 65) return true;
			else return this.actionBar.fireActionByKey(String.fromCharCode(e.keyCode).toLowerCase());
		}.bind(this));
	},
	
	initGUI: function(){
		jQuery("#toolbars").corner("round bottom 10px");
		jQuery("#action_bar a").corner("round 8px");
		if(!Prototype.Browser.WebKit){ // Do not try this on safari, not working good.
			jQuery(".action_bar a").corner("round 8px");
		}
		jQuery("#location_form").corner("round 8px");
		
		jQuery("#verticalSplitter").splitter({
				type: "v",
				initA: 200, maxA:400, minA:50
				});
		jQuery("#sidebarSplitter").splitter({
				type: "h",
				initB: 150,
				minB: 23,
				maxB: 500
				});
				
		jQuery("#browser_round").corner("round 8px");
		fitHeightToBottom($("browser"), window, 15);
		fitHeightToBottom($("verticalSplitter"), $('browser'), (Prototype.Browser.IE?8:0));
		fitHeightToBottom($('tree_container'), null, (Prototype.Browser.IE?0:3));
		fitHeightToBottom(this.sEngine._resultsBox, null, 10);
		this.currentSideToggle = 'search';
		this.toggleSidePanel('info');	
		jQuery("#sidebarSplitter").trigger("resize");
		
		new Effect.Fade(this.messageBox);
		$(this.actionBar._htmlElement).getElementsBySelector('a', 'input[type="image"]').each(function(element){
			disableTextSelection(element);
		});
		$('search_container').getElementsBySelector('a', 'div[id="search_results"]').each(function(element){
			disableTextSelection(element);
		});
		disableTextSelection($('tree_container'));
		disableTextSelection($('bookmarks_bar'));
		disableTextSelection($('panelsToggle'));
		disableTextSelection($('info_panel'));
		disableTextSelection($('dir_chooser'));
		
	},
	
	refreshRootDirMenu: function(rootDirsList, rootDirId){
		if(this.rootDirsButton) {
			this.rootDirsButton.remove();this.rootDirsButton = null;
		}
		if(!rootDirsList || rootDirsList.size() <= 1) return;
		// CREATE BUTTON
		var img = new Element('img', {
				id:'root_dir_button', 
				src:'images/crystal/lower.png',
				alt:MessageHash[200],
				align:'absmiddle'
			}).setStyle({cursor:'pointer'});			
		$('dir_chooser').appendChild(img);
		this.rootDirsButton = img;
		var actions = new Array();
		rootDirsList.each(function(pair){
			var value = pair.value;
			var key = pair.key;
			var selected = (key == this.rootDirId ? true:false);
			actions[actions.length] = {
				name:value,
				alt:value,				
				image:'images/foldericon.png',				
				className:"edit",
				disabled:selected,
				callback:function(e){
					ajaxplorer.triggerRootDirChange(''+key);
				}
			}
		}.bind(this));		
		
		this.rootMenu = new Proto.Menu({
			selector: '[id="root_dir_button"]',
			className: 'menu rootDirChooser',
			mouseClick:'left',
			anchor:img,
			menuTitle:MessageHash[200],
			menuItems: actions,
			fade:true,
			zIndex:2000
		});			
	},
	

	triggerRootDirChange: function(rootDirId){
		this.actionBar.updateLocationBar('/');
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'switch_root_dir');
		connexion.addParameter('root_dir_index', rootDirId);
		oThis = this;
		connexion.onComplete = function(transport){
			if(this.usersEnabled)
			{
				this.getLoggedUserFromServer();
			}
			else
			{
				this.foldersTree.setCurrentNodeName(this.foldersTree.getRootNodeId());
				this.foldersTree.changeNodeLabel(this.foldersTree.getRootNodeId(), this._initRootDirsList[rootDirId]);
				this.actionBar.parseXmlMessage(transport.responseXML);
				this.foldersTree.reloadCurrentNode();
				this.foldersTree.changeNodeLabel(this.foldersTree.getRootNodeId(), this._initRootDirsList[rootDirId]);
				this.filesList.loadXmlList('/');
				this.rootDirId = rootDirId;
				this.actionBar.loadBookmarks();			
			}
		}.bind(this);
		connexion.sendAsync();
	},
	
	toggleSidePanel: function(srcName){	
		if(srcName == 'info' && this.currentSideToggle != 'info'){
			$(this.sEngine.htmlElement).hide();
			$('search_header').addClassName("toggleInactive");
			$('search_header').getElementsBySelector("img")[0].hide();
			$(this.infoPanel.htmlElement).show();
			$('info_panel_header').removeClassName("toggleInactive");
			$('info_panel_header').getElementsBySelector("img")[0].show();
		}
		else if(srcName == 'search' && this.currentSideToggle != 'search'){
			$(this.sEngine.htmlElement).show();
			$('search_header').removeClassName("toggleInactive");
			$('search_header').getElementsBySelector("img")[0].show();
			$(this.infoPanel.htmlElement).hide();
			$('info_panel_header').addClassName("toggleInactive");
			$('info_panel_header').getElementsBySelector("img")[0].hide();
			fitHeightToBottom(this.sEngine._resultsBox, null, 5, true);
		}
		this.currentSideToggle = srcName;
	},

	loadLibraries: function(){
		if(!dynamicLibLoading) {this.init(); return;}
		var connexion = new Connexion();
		var toLoad = $A([]);			
		modal.incrementStepCounts(toLoad.size());
		toLoad.each(function(fileName){
			var onLoad = function(){modal.updateLoadingProgress(fileName);};
			if(fileName == toLoad.last()) onLoad = function(){modal.updateLoadingProgress(fileName);this.init();}.bind(this);
			connexion.loadLibrary(fileName, onLoad);
		});
	},
	
	libLoaded: function(fileName){	
		modal.updateLoadingProgress('Loaded : ' + fileName);
	}

});