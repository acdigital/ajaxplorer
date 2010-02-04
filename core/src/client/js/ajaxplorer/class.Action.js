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
 * Description : A "Command" object, encapsulating its callbacks, display attributes, etc.
 */
Action = Class.create({

	__DEFAULT_ICON_PATH : "/images/crystal/actions/ICON_SIZE",
	
	initialize:function(){
		this.actionBar = arguments[0];
		this.options = Object.extend({
			name:'',
			src:'',
			text:'',
			title:'',
			hasAccessKey:false,
			accessKey:'',
			subMenu:false,
			callbackCode:'',
			callback:Prototype.emptyFunction,
			displayAction:false,
			prepareModal:false, 
			formId:undefined, 
			formCode:undefined
			}, arguments[1] || { });
		this.context = Object.extend({
			selection:true,
			dir:false,
			allowedMimes:$A([]),
			root:true,
			inZip:true,
			recycle:false,
			behaviour:'hidden',
			actionBar:false,
			actionBarGroup:'default',
			contextMenu:false,
			infoPanel:false			
			}, arguments[2] || { });
			
		this.selectionContext = Object.extend({			
			dir:false,
			file:true,
			recycle:false,
			behaviour:'disabled',
			allowedMimes:$A([]),			
			unique:true,
			multipleOnly:false
			}, arguments[3] || { });
		this.rightsContext = Object.extend({			
			noUser:true,
			userLogged:true,
			guestLogged:false,
			read:true,
			write:false,
			adminOnly:false
			}, arguments[4] || { });
		this.subMenuItems = {};
		this.elements = new Array();
		this.contextHidden = false;
		this.deny = false;
		if(this.options.subMenu && !this.actionBar){
			alert('Warning, wrong action definition. Cannot use a subMenu if not displayed in the actionBar!');
		}
	}, 
	
	apply: function(){
		if(this.deny) return;
		if(this.options.prepareModal){
			modal.prepareHeader(
				this.options.title, 
				resolveImageSource(this.options.src,this.__DEFAULT_ICON_PATH, 16)
			);
		}
		window.actionArguments = $A([]);
		if(arguments[0]) window.actionArguments = $A(arguments[0]);
		if(this.options.callbackCode) this.options.callbackCode.evalScripts();
		window.actionArguments = null;
	},
	
	fireContextChange: function(){
		if(arguments.length < 5) return;
		var usersEnabled = arguments[0];
		var crtUser = arguments[1];
		var crtIsRecycle = arguments[2];
		var crtDisplayMode = arguments[3];
		var crtInZip = arguments[4];
		var crtIsRoot = arguments[5];
		var crtAjxpMime = arguments[6] || '';
		if(this.options.listeners && this.options.listeners["contextChange"]){
			this.options.listeners["contextChange"].evalScripts();
		}		
		var rightsContext = this.rightsContext;
		if(!rightsContext.noUser && !usersEnabled){
			return this.hideForContext();				
		}
		if((rightsContext.userLogged == 'only' && crtUser == null) ||
			(rightsContext.guestLogged && rightsContext.guestLogged=='hidden' & crtUser!=null && crtUser.id=='guest')){
			return this.hideForContext();
		}
		if(rightsContext.userLogged == 'hidden' && crtUser != null && !(crtUser.id=='guest' && rightsContext.guestLogged && rightsContext.guestLogged=='show') ){
			return this.hideForContext();
		}
		if(rightsContext.adminOnly && (crtUser == null || !crtUser.isAdmin)){
			return this.hideForContext();
		}
		if(rightsContext.read && crtUser != null && !crtUser.canRead()){
			return this.hideForContext();
		}
		if(rightsContext.write && crtUser != null && !crtUser.canWrite()){
			return this.hideForContext();
		}
		if(this.context.allowedMimes.length){
			if(!this.context.allowedMimes.indexOf(crtAjxpMime)==-1){
				return this.hideForContext();
			}
		}
		if(this.context.recycle){
			if(this.context.recycle == 'only' && !crtIsRecycle){
				return this.hideForContext();				
			}
			if(this.context.recycle == 'hidden' && crtIsRecycle){
				return this.hideForContext();
			}
		}
		if(!this.context.inZip && crtInZip){
			return this.hideForContext();
		}
		if(!this.context.root && crtIsRoot){
			return this.hideForContext();
		}
		if(this.options.displayAction && this.options.displayAction == crtDisplayMode){
			return this.hideForContext();
		}
		this.showForContext();				
		
	},
		
	fireSelectionChange: function(){
		if(this.options.listeners && this.options.listeners["selectionChange"]){
			this.options.listeners["selectionChange"].evalScripts();
		}
		if(arguments.length < 1 
			|| this.contextHidden 
			|| !this.context.selection) {	
			return;
		}
		var userSelection = arguments[0];		
		var bSelection = false;
		if(userSelection != null) 
		{			
			bSelection = !userSelection.isEmpty();
			var bUnique = userSelection.isUnique();
			var bFile = userSelection.hasFile();
			var bDir = userSelection.hasDir();
			var bRecycle = userSelection.isRecycle();
		}
		var selectionContext = this.selectionContext;
		if(selectionContext.allowedMimes.size()){
			if(selectionContext.behaviour == 'hidden') this.hide();
			else this.disable();
		}
		if(selectionContext.unique && !bUnique){
			return this.disable();
		}
		if((selectionContext.file || selectionContext.dir) && !bFile && !bDir){
			return this.disable();
		}
		if((selectionContext.dir && !selectionContext.file && bFile) 
			|| (!selectionContext.dir && selectionContext.file && bDir)){
			return this.disable();
		}
		if(!selectionContext.recycle && bRecycle){
			return this.disable();
		}
		if((selectionContext.allowedMimes.size() && userSelection && !userSelection.hasMime(selectionContext.allowedMimes)) 
			&& !(selectionContext.dir && bDir)){
			if(selectionContext.behaviour == 'hidden') return this.hide();
			else return this.disable();
		}
		this.show();
		this.enable();
		
	},
		
	createFromXML:function(xmlNode){
		this.options.name = xmlNode.getAttribute('name');
		for(var i=0; i<xmlNode.childNodes.length;i++){
			var node = xmlNode.childNodes[i];			
			if(node.nodeName == "processing"){
				for(var j=0; j<node.childNodes.length; j++){
					var processNode = node.childNodes[j];
					if(processNode.nodeName == "clientForm"){
						this.options.formId = processNode.getAttribute("id");
						this.options.formCode = processNode.firstChild.nodeValue;
						this.insertForm();
					}else if(processNode.nodeName == "clientCallback" && processNode.firstChild){
						this.options.callbackCode = '<script>'+processNode.firstChild.nodeValue+'</script>';
						if(processNode.getAttribute('prepareModal') && processNode.getAttribute('prepareModal') == "true"){
							this.options.prepareModal = true;						
						}
						if(processNode.getAttribute('displayModeButton') && processNode.getAttribute('displayModeButton') != ''){
							this.options.displayAction = processNode.getAttribute('displayModeButton');
						}						
					}else if(processNode.nodeName == "clientListener" && processNode.firstChild){
						if(!this.options.listeners) this.options.listeners = [];
						this.options.listeners[processNode.getAttribute('name')] = '<script>'+processNode.firstChild.nodeValue+'</script>';
					}
				}
			}else if(node.nodeName == "gui"){
				this.options.text = MessageHash[node.getAttribute('text')];
				this.options.title = MessageHash[node.getAttribute('title')];
				this.options.src = node.getAttribute('src');								
				if(node.getAttribute('hasAccessKey') && node.getAttribute('hasAccessKey') == "true"){
					this.options.accessKey = node.getAttribute('accessKey');
					this.options.hasAccessKey = true;
				}
				for(var j=0; j<node.childNodes.length;j++){
					if(node.childNodes[j].nodeName == "context"){
						this.attributesToObject(this.context, node.childNodes[j]);
					}
					else if(node.childNodes[j].nodeName == "selectionContext"){
						this.attributesToObject(this.selectionContext, node.childNodes[j]);
					}
				}
							
			}else if(node.nodeName == "rightsContext"){
				this.attributesToObject(this.rightsContext, node);
			}else if(node.nodeName == "subMenu"){
				this.options.subMenu = true;
				for(var j=0;j<node.childNodes.length;j++){
					if(node.childNodes[j].nodeName == "staticItems" || node.childNodes[j].nodeName == "dynamicItems"){
						this.subMenuItems[node.childNodes[j].nodeName] = [];
						for(var k=0;k<node.childNodes[j].childNodes.length;k++){
							if(node.childNodes[j].childNodes[k].nodeName == "item"){
								var item = {};
								for(var z=0;z<node.childNodes[j].childNodes[k].attributes.length;z++){
									var attribute = node.childNodes[j].childNodes[k].attributes[z];
									item[attribute.nodeName] = attribute.nodeValue;
								}
								this.subMenuItems[node.childNodes[j].nodeName].push(item);
							}
						}
					}
				}
				if(this.subMenuItems.staticItems){
					this.buildSubmenuStaticItems();
				}
				if(this.subMenuItems.dynamicItems){
					this.prepareSubmenuDynamicBuilder();
				}
			}
		}
		if(!this.options.hasAccessKey) return;
		if(this.options.accessKey == '' 
			|| !MessageHash[this.options.accessKey] 
			|| this.options.text.indexOf(MessageHash[this.options.accessKey]) == -1)
		{
			this.options.accessKey == this.options.text.charAt(0);
		}else{
			this.options.accessKey = MessageHash[this.options.accessKey];
		}		
	}, 
	
	toActionBar:function(){
		var button = new Element('a', {
			href:this.options.name,
			id:this.options.name +'_button'
		}).observe('click', function(e){
			Event.stop(e);
			if(this.options.subMenu){
				this.subMenu.show(e);
			}else{
				this.apply();
			}
		}.bind(this));
		var imgPath = resolveImageSource(this.options.src,this.__DEFAULT_ICON_PATH, 22);
		var img = new Element('img', {
			id:this.options.name +'_button_icon',
			src:imgPath,
			width:18,
			height:18,
			border:0,
			align:'absmiddle',
			alt:this.options.title,
			title:this.options.title
		});
		var titleSpan = new Element('span', {id:this.options.name+'_button_label'}).setStyle({paddingLeft:6,paddingRight:6, cursor:'pointer'});
		button.insert(img).insert(new Element('br')).insert(titleSpan.update(this.getKeyedText()));
		this.elements.push(button);
		button.observe("mouseover", function(){
			if(button.hasClassName('disabled')) return;
			if(this.hideTimeout) clearTimeout(this.hideTimeout);
			new Effect.Morph(img, {
				style:'width:25px; height:25px;margin-top:0px;',
				duration:0.08,
				transition:Effect.Transitions.sinoidal,
				afterFinish: function(){this.updateTitleSpan(titleSpan, 'big');}.bind(this)
			});
		}.bind(this) );
		button.observe("mouseout", function(){
			if(button.hasClassName('disabled')) return;
			this.hideTimeout = setTimeout(function(){				
				new Effect.Morph(img, {
					style:'width:18px; height:18px;margin-top:8px;',
					duration:0.2,
					transition:Effect.Transitions.sinoidal,
					afterFinish: function(){this.updateTitleSpan(titleSpan, 'small');}.bind(this)
				});	
			}.bind(this), 10);
		}.bind(this) );
		button.hide();
		if(this.options.subMenu){
			this.buildActionBarSubMenu(button);
		}
		return button;
	},
	
	buildSubmenuStaticItems : function(){
		var menuItems = [];
		if(this.subMenuItems.staticItems){
			this.subMenuItems.staticItems.each(function(item){
				var itemText = MessageHash[item.text];
				if(item.hasAccessKey && item.hasAccessKey=='true' && MessageHash[item.accessKey]){
					itemText = this.getKeyedText(MessageHash[item.text],true,MessageHash[item.accessKey]);
					if(!this.subMenuItems.accessKeys) this.subMenuItems.accessKeys = [];
					this.actionBar.registerKey(MessageHash[item.accessKey],this.options.name, item.command);
					//@todo REGISTER THESE ACCESSKEYS
				}
				menuItems.push({
					name:itemText,
					alt:MessageHash[item.title],
					image:resolveImageSource(item.src, '/images/crystal/actions/ICON_SIZE', 22),
					isDefault:(item.isDefault?true:false),
					callback:function(e){this.apply([item.command]);}.bind(this)
				});
			}, this);
		}
		this.subMenuItems.staticOptions = menuItems;
	},
	
	prepareSubmenuDynamicBuilder : function(){		
		this.subMenuItems.dynamicBuilder = function(protoMenu){
			setTimeout(function(){
		  		var menuItems = [];
		  		this.subMenuItems.dynamicItems.each(function(item){
		  			var action = this.actionBar.actions.get(item['actionId']);
		  			if(action.deny) return;
					menuItems.push({
						name:action.getKeyedText(),
						alt:action.options.title,
						image:resolveImageSource(action.options.src, '/images/crystal/actions/ICON_SIZE', 16),						
						callback:function(e){this.apply();}.bind(action)
					});
		  		}, this);
			  	protoMenu.options.menuItems = menuItems;
			  	protoMenu.refreshList();
			}.bind(this),0);
		}.bind(this);		
	},
	
	buildActionBarSubMenu : function(button){
		this.subMenu = new Proto.Menu({
		  mouseClick:"over",
		  anchor: button, // context menu will be shown when element with class name of "contextmenu" is clicked
		  className: 'menu desktop', // this is a class which will be attached to menu container (used for css styling)
		  topOffset : 2,
		  leftOffset : -1,	
		  parent : this.actionBar,	 
		  menuItems: this.subMenuItems.staticOptions || [],
		  fade:true,
		  zIndex:2000		  
		});		
		this.subMenu.options.beforeShow = function(e){
			button.addClassName("menuAnchorSelected");
		  	if(this.subMenuItems.dynamicItems){
		  		this.subMenuItems.dynamicBuilder(this.subMenu);
		  	}
		}.bind(this);		
		this.subMenu.options.beforeHide = function(e){
			button.removeClassName("menuAnchorSelected");
		}.bind(this);
		this.actionBar.subMenus.push(this.subMenu);
	},
	
	updateTitleSpan : function(span, state){		
		if(!span.orig_width && state == 'big'){
			var origWidth = span.getWidth();
			span.setStyle({display:'block',width:origWidth, overflow:'visible', padding:0});
			span.orig_width = origWidth;
		}
		span.setStyle({fontSize:(state=='big'?'11px':'9px')});
	},
	
	setIconSrc : function(newSrc){
		this.options.src = newSrc;
		if($(this.options.name +'_button_icon')){
			$(this.options.name +'_button_icon').src = resolveImageSource(this.options.src,this.__DEFAULT_ICON_PATH, 22);
		}		
	},
	
	setLabel : function(newLabel, newTitle){
		this.options.text = MessageHash[newLabel];
		if($(this.options.name+'_button_label')){
			$(this.options.name+'_button_label').update(this.getKeyedText());
		}
		if(!newTitle) return;
		this.options.title = MessageHash[newTitle];
		if($(this.options.name+'_button_icon')){
			$(this.options.name+'_button_icon').title = this.options.title;
		}
	},
	
	toInfoPanel:function(){
		return this.options;
	},
	
	toContextMenu:function(){
		return this.options;
	},
	
	hideForContext: function(){
		this.hide();
		this.contextHidden = true;
	},
	
	showForContext: function(){
		this.show();
		this.contextHidden = false;
	},
	
	hide: function(){		
		if(this.elements.size() > 0 || (!this.context.actionBar && this.context.infoPanel)) this.deny = true;
		this.elements.each(function(elem){
			elem.hide();
		});
	},
	
	show: function(){
		if(this.elements.size() > 0 || (!this.context.actionBar && this.context.infoPanel)) this.deny = false;
		this.elements.each(function(elem){
			elem.show();
		});
	},
	
	disable: function(){
		if(this.elements.size() > 0 || (!this.context.actionBar && this.context.infoPanel)) this.deny = true;
		this.elements.each(function(elem){
			elem.addClassName('disabled');
		});	
	},
	
	enable: function(){
		if(this.elements.size() > 0 || (!this.context.actionBar && this.context.infoPanel)) this.deny = false;
		this.elements.each(function(elem){
			elem.removeClassName('disabled');
		});	
	},
	
	remove: function(){
		// Remove all elements and forms from html
		this.elements.each(function(el){
			$(el).remove();
		});
		if(this.options.formId && $('all_forms').select('[id="'+this.options.formId+'"]').length){
			$('all_forms').select('[id="'+this.options.formId+'"]')[0].remove();
		}
	},
	
	getKeyedText: function(displayString, hasAccessKey, accessKey){
		if(!displayString){
			displayString = this.options.text;
		}
		if(!hasAccessKey){
			hasAccessKey = this.options.hasAccessKey;
		}
		if(!accessKey){
			accessKey = this.options.accessKey;
		}
		if(!hasAccessKey) return displayString;
		var keyPos = displayString.toLowerCase().indexOf(accessKey.toLowerCase());
		if(keyPos==-1){
			return displayString + ' (<u>' + accessKey + '</u>)';
		}
		if(displayString.charAt(keyPos) != accessKey){
			// case differ
			accessKey = displayString.charAt(keyPos);
		}
		returnString = displayString.substring(0,displayString.indexOf(accessKey));
		returnString += '<u>'+accessKey+'</u>';
		returnString += displayString.substring(displayString.indexOf(accessKey)+1, displayString.length);
		return returnString;
	},
	
	insertForm: function(){
		if(!this.options.formCode || !this.options.formId) return;
		if($('all_forms').select('[id="'+this.options.formId+'"]').length) return;
		$('all_forms').insert(this.options.formCode);
	},
	
	attributesToObject: function(object, node){
		Object.keys(object).each(function(key){
			if(node.getAttribute(key)){
				value = node.getAttribute(key);
				if(value == 'true') value = true;
				else if(value == 'false') value = false;
				if(key == 'allowedMimes'){
					if(value && value.split(',').length){
						value = $A(value.split(','));
					}else{
						value = $A([]);
					}					
				}
				this[key] = value;
			}
		}.bind(object));
	}

});
