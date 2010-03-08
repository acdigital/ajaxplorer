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
 * Description : A selector for displaying repository list. Will hook to ajaxplorer:repository_list_refreshed.
 */
Class.create("RepositorySelect", {
	__implements : "IAjxpWidget",
	_defaultString:'No Repository',
	_defaultIcon : 'network-wired.png',
	
	initialize : function(oElement){
		this.element = oElement;
		this.createGui();
		document.observe("ajaxplorer:repository_list_refreshed", function(e){
			this.refreshRepositoriesMenu(e.memo.list,e.memo.active);
		}.bind(this) );
	},
	
	createGui : function(){
		this.icon = new Element('img', {
			id:'repo_icon',
			src:resolveImageSource(this._defaultIcon,'/images/crystal/actions/ICON_SIZE', 16),
			width:16,
			height:16,
			align:'absmiddle'
		});
		this.label = new Element('input', {
			 type:"text", 
			 name:"repo_path", 
			 value:this._defaultString, 
			 id:"repo_path"
		});
		var div = new Element('div', {id:'repository_form'});
		div.insert(this.icon);
		div.insert(this.label);
		this.element.insert(div);
		var callback = function(e){alert('toto!')};
		this.button = simpleButton(
			'repository_goto', 
			'inlineBarButtonLeft', 
			200, 
			200, 
			'arrow_down_margin.png', 
			16, 
			'inline_hover');
		this.button.setStyle({marginRight:'7px'});
		this.element.insert(this.button);
	},
	
	refreshRepositoriesMenu: function(repositoryList, repositoryId){
		this.button.addClassName('disabled');
		var actions = new Array();
		if(repositoryList && repositoryList.size() > 1){
			repositoryList.each(function(pair){
				var repoObject = pair.value;
				var key = pair.key;
				var selected = (key == repositoryId ? true:false);
				actions[actions.length] = {
					name:repoObject.getLabel(),
					alt:repoObject.getLabel(),				
					image:repoObject.getIcon(),
					className:"edit",
					disabled:selected,
					callback:function(e){
						this.onRepoSelect(''+key);
					}.bind(this)
				};
				if(key == repositoryId){
					this.label.setValue(repoObject.getLabel());
					this.icon.src = repoObject.getIcon();
				}
			}.bind(this));
		}else{
			this.label.setValue(this._defaultString);
			this.icon.src = resolveImageSource(this._defaultIcon,'/images/crystal/actions/ICON_SIZE', 16);
		}
		if(this.repoMenu){
			this.repoMenu.options.menuItems = actions;
			this.repoMenu.refreshList();
		}else{
			this.repoMenu = new Proto.Menu({			
				className: 'menu rootDirChooser',
				mouseClick:'left',
				anchor:this.button,
				createAnchor:false,
				anchorContainer:$('dir_chooser'),
				anchorSrc:ajxpResourcesFolder+'/images/crystal/lower.png',
				anchorTitle:MessageHash[200],
				topOffset:2,
				leftOffset:-127,
				menuTitle:MessageHash[200],
				menuItems: actions,
				fade:true,
				zIndex:1500
			});		
		}
		if(actions.length) this.button.removeClassName('disabled');
        actions.sort(function(a,b) { return a.name > b.name; });
	},
	
	onRepoSelect : function(key){
		ajaxplorer.triggerRepositoryChange(key);
	},
	
	resize : function(){},
	showElement : function(show){}
	
});