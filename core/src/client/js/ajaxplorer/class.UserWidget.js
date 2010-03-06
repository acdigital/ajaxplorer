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
 * Description : Container for parent/location/bookmark components.
 */
Class.create("UserWidget", {
	__implements : ["IAjxpWidget"],
	initialize: function(element){
		this.element = element;
		document.observe("ajaxplorer:user_logged", this.updateGui.bind(this));
	},
	updateGui : function(){
		var logging_string = "";
		var oUser = ajaxplorer.user;
		this.element.stopObserving("click", this.displayUserPrefs.bind(this));
		if(oUser != null) 
		{
			if(oUser.id != 'guest') 
			{
				logging_string = '<ajxp:message ajxp_message_id="142">'+MessageHash[142]+'</ajxp:message><i ajxp_message_title_id="189" title="'+MessageHash[189]+'">'+ oUser.id+' <img src="'+ajxpResourcesFolder+'/images/crystal/actions/16/configure.png" height="16" width="16" border="0" align="absmiddle"></i>';
				if(oUser.getPreference('lang') != null && oUser.getPreference('lang') != "" && oUser.getPreference('lang') != ajaxplorer.currentLanguage)
				{
					ajaxplorer.loadI18NMessages(oUser.getPreference('lang'));
				}
				this.element.observe("click", this.displayUserPrefs.bind(this));
			}
			else 
			{
				logging_string = '<ajxp:message ajxp_message_id="143">'+MessageHash[143]+'</ajxp:message>';
			}
		}
		else 
		{
			logging_string = '<ajxp:message ajxp_message_id="142">'+MessageHash[144]+'</ajxp:message>';
		}
		this.element.update(logging_string);
		
	},
	
	displayUserPrefs: function()
	{
		if(ajaxplorer.user == null) return;
		var userLang = ajaxplorer.user.getPreference("lang");
		var userDisp = ajaxplorer.user.getPreference("display");	
		var onLoad = function(){		
			var elements = $('user_pref_form').getElementsBySelector('input[type="radio"]');		
			elements.each(function(elem){
				elem.checked = false;			
				if(elem.id == 'display_'+userDisp || elem.id == 'lang_'+userLang) {
					elem.checked = true;
				}
			});
			if($('user_change_ownpass_old')){
				$('user_change_ownpass_old').value = $('user_change_ownpass1').value = $('user_change_ownpass2').value = '';
				// Update pass_seed
				var connexion = new Connexion();
				connexion.addParameter("get_action", "get_seed");
				connexion.onComplete = function(transport){
					$('pass_seed').value = transport.responseText;
				};
				connexion.sendSync();			
			}
		};
		
		var onComplete = function(){
			var elements = $('user_pref_form').getElementsBySelector('input[type="radio"]');
			elements.each(function(elem){			
				if(elem.checked){
					 ajaxplorer.user.setPreference(elem.name, elem.value);
				}
			});
			var userOldPass = null;
			var userPass = null;
			var passSeed = null;
			if($('user_change_ownpass1') && $('user_change_ownpass1').value != "" && $('user_change_ownpass2').value != "")
			{
				if($('user_change_ownpass1').value != $('user_change_ownpass2').value){
					alert(MessageHash[238]);
					return false;
				}
				if($('user_change_ownpass_old').value == ''){
					alert(MessageHash[239]);
					return false;					
				}
				passSeed = $('pass_seed').value;
				if(passSeed == '-1'){
					userPass = $('user_change_ownpass1').value;
					userOldPass = $('user_change_ownpass_old').value;
				}else{
					userPass = hex_md5($('user_change_ownpass1').value);
					userOldPass = hex_md5( hex_md5($('user_change_ownpass_old').value)+$('pass_seed').value);
				}				
			}
			var onComplete = function(transport){
				var oUser = ajaxplorer.user;
				if(oUser.getPreference('lang') != null 
					&& oUser.getPreference('lang') != "" 
					&& oUser.getPreference('lang') != ajaxplorer.currentLanguage)
				{
					ajaxplorer.loadI18NMessages(oUser.getPreference('lang'));
				}
					
				if(userPass != null){
					if(transport.responseText == 'PASS_ERROR'){
						alert(MessageHash[240]);
					}else if(transport.responseText == 'SUCCESS'){
						ajaxplorer.displayMessage('SUCCESS', MessageHash[197]);
						hideLightBox(true);
					}
				}else{
					ajaxplorer.displayMessage('SUCCESS', MessageHash[241]);
					hideLightBox(true);
				}
			};
			ajaxplorer.user.savePreferences(userOldPass, userPass, passSeed, onComplete);
			return false;		
		};
		
		modal.prepareHeader(MessageHash[195], ajxpResourcesFolder+'/images/crystal/actions/16/configure.png');
		modal.showDialogForm('Preferences', 'user_pref_form', onLoad, onComplete);
	},
	
	resize : function(){},
	showElement : function(show){}	
});