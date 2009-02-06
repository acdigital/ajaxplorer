AdminPageManager = Class.create({

	initialize: function(){
		this.loadUsers();
		this.loadDrivers();
		this.loadRepList();
		this.loadLogList();
		this.loadInstallLog();
		this.usersPanel = $('users_management');
		this.repoPanel = $('repositories_management');		
		this.logsPanel = $('logs_management');
		this.installPanel = $('install_management');
		this.toggleSidePanel('users');
		if(Prototype.Browser.IE) $('repo_create_form').setStyle({height:'62px'});
	},
	
	toggleSidePanel: function(srcName){	
		if(srcName == 'users'){
			this.repoPanel.hide();
			$('repositories_header').addClassName("toggleInactive");
			$('repositories_header').getElementsBySelector("img")[0].hide();
			this.logsPanel.hide();
			$('logs_header').addClassName("toggleInactive");
			$('logs_header').getElementsBySelector("img")[0].hide();
			this.installPanel.hide();
			$('install_header').addClassName("toggleInactive");
			$('install_header').getElementsBySelector("img")[0].hide();
			this.usersPanel.show();
			$('users_header').removeClassName("toggleInactive");
			$('users_header').getElementsBySelector("img")[0].show();
		}
		else if(srcName == 'repositories'){
			this.repoPanel.show();
			$('repositories_header').removeClassName("toggleInactive");
			$('repositories_header').getElementsBySelector("img")[0].show();
			this.usersPanel.hide();
			$('users_header').addClassName("toggleInactive");
			$('users_header').getElementsBySelector("img")[0].hide();			
			this.installPanel.hide();
			$('install_header').addClassName("toggleInactive");
			$('install_header').getElementsBySelector("img")[0].hide();
			this.logsPanel.hide();
			$('logs_header').addClassName("toggleInactive");
			$('logs_header').getElementsBySelector("img")[0].hide();
		}
		else if(srcName == 'logs'){
			this.repoPanel.hide();
			$('repositories_header').addClassName("toggleInactive");
			$('repositories_header').getElementsBySelector("img")[0].hide();
			this.usersPanel.hide();
			$('users_header').addClassName("toggleInactive");
			$('users_header').getElementsBySelector("img")[0].hide();			
			this.installPanel.hide();
			$('install_header').addClassName("toggleInactive");
			$('install_header').getElementsBySelector("img")[0].hide();
			this.logsPanel.show();
			$('logs_header').removeClassName("toggleInactive");
			$('logs_header').getElementsBySelector("img")[0].show();
		}
		else if(srcName == 'install'){
			this.repoPanel.hide();
			$('repositories_header').addClassName("toggleInactive");
			$('repositories_header').getElementsBySelector("img")[0].hide();
			this.usersPanel.hide();
			$('users_header').addClassName("toggleInactive");
			$('users_header').getElementsBySelector("img")[0].hide();			
			this.logsPanel.hide();
			$('logs_header').addClassName("toggleInactive");
			$('logs_header').getElementsBySelector("img")[0].hide();
			this.installPanel.show();
			$('install_header').removeClassName("toggleInactive");
			$('install_header').getElementsBySelector("img")[0].show();
		}
		this.currentSideToggle = srcName;
	},
	
	
	loadUsers: function(){
		var p = new Hash();
		p.set('get_action','users_list');
		this.loadHtmlToDiv($('users_list'), p);	
	},
	
	loadDrivers : function(){
		this.submitForm('drivers_list', new Hash());
	},
	
	loadInstallLog : function(){
		this.loadHtmlToDiv($('install_log'), new Hash({'get_action':'install_log'}));
	},
	
	updateDriverSelector : function(){
		if(!this.drivers || !$('drivers_selector')) return;
		this.drivers.each(function(pair){
			var option = new Element('option');
			option.setAttribute('value', pair.key);
			option.update(pair.value.get('label'));
			$('drivers_selector').insert({'bottom':option});
		});
		$('drivers_selector').onchange = this.driverSelectorChange.bind(this);
	},
	
	driverSelectorChange : function(){
		var height = (Prototype.Browser.IE?62:32);
		var dName = $('drivers_selector').getValue();
		this.createDriverForm(dName);
		if(dName != "0"){
			var height = 32 + $('driver_form').getHeight() + (Prototype.Browser.IE?15:0);
		}
		
		new Effect.Morph('repo_create_form',{
			style:'height:'+height + 'px',
			duration:0.5
		});		
	},
	
	createDriverForm : function(driverName){
		if(driverName == "0"){
			$('driver_form').update('');
			return;
		}
		var dOpt = this.drivers.get(driverName);
		$('driver_form').update('<div style="padding-top:4px;color:#79f;"><b style="color:#79f;">'+dOpt.get('label') + '</b> : ' + dOpt.get('description')+'<br></div>');
		this.createParametersInputs($('driver_form'), dOpt.get('params'), true);
		var buttons = '<div align="center" style="clear:left;padding-top:5px;"><input type="button" value="Save" class="button" onclick="return manager.repoButtonClick(true);"> <input type="button" value="Cancel" class="button" onclick="return manager.repoButtonClick(false);"></div>';
		$('driver_form').insert({'bottom':buttons});
	},
	
	repoButtonClick  : function(validate){
		if(!validate) {
			$('driver_label').value = '';
			$('drivers_selector').selectedIndex = 0;
			this.driverSelectorChange();
			return false;		
		}
		var toSubmit = new Hash();
		var missingMandatory = false;
		if($('driver_label').value == ''){
			missingMandatory = true;
		}else{
			toSubmit.set('DISPLAY', $('driver_label').value);
		}
		toSubmit.set('DRIVER', $('drivers_selector').options[$('drivers_selector').selectedIndex].value);
		
		if(missingMandatory || this.submitParametersInputs($('driver_form'), toSubmit, 'DRIVER_OPTION_')){
			this.displayMessage("ERROR", "Mandatory fields are missing!");
			return false;
		}		
		this.submitForm('create_repository', toSubmit, null, function(){
			this.repoButtonClick(false);
			this.loadRepList();
			this.loadUsers();
		}.bind(this));
		return false;		
	},
	
	createParametersInputs : function(form, parametersDefinitions, showTip, values){
		parametersDefinitions.each(function(param){		
			var label = param.get('label');
			var name = param.get('name');
			var type = param.get('type');
			var desc = param.get('description');
			var mandatory = false;
			if(param.get('mandatory') && param.get('mandatory')=='true') mandatory = true;
			var defaultValue = (values?'':(param.get('default') || ""));
			if(values && values.get(name)){
				defaultValue = values.get(name);
			}
			var element;
			if(type == 'string'){
				element = '<input type="text" ajxp_mandatory="'+(mandatory?'true':'false')+'" name="'+name+'" class="text" value="'+defaultValue+'">';
			}else if(type == 'boolean'){
				var selectTrue, selectFalse;
				if(defaultValue){
					if(defaultValue == "true" || defaultValue == "1") selectTrue = true;
					if(defaultValue == "false" || defaultValue == "0") selectFalse = true;
				}
				element = '<input type="radio" class="radio" name="'+name+'" value="true" '+(selectTrue?'checked':'')+'> Yes';
				element = element + '<input type="radio" class="radio" name="'+name+'" '+(selectFalse?'checked':'')+' value="false"> No';
			}
			var div = new Element('div', {style:"padding:2px; clear:left"}).update('<div style="float:left; width:30%;text-align:right;"><b>'+label+(mandatory?'*':'')+'</b>&nbsp;:&nbsp;</div><div style="float:left;width:70%">'+element+(showTip?' &nbsp;<small style="color:#AAA;">'+desc+'</small>':'')+'</div>');
			form.insert({'bottom':div});
		});
	},
	
	submitParametersInputs : function(form, parametersHash, prefix){
		prefix = prefix || '';
		var missingMandatory = false;
		form.select('input').each(function(el){			
			if(el.type == "text"){
				if(el.getAttribute('ajxp_mandatory') == 'true' && el.value == ''){
					missingMandatory = true;
				}
				parametersHash.set(prefix+el.name, el.value);				
			}
			else if(el.type=="radio" && el.checked){
				parametersHash.set(prefix+el.name, el.value)
			};			
		});		
		return missingMandatory;
	},
		
	loadRepList : function(){
		$('repo_detail_panel').update('');
		this.submitForm('repository_list', new Hash());
	},
	
	updateRepList : function(){
		if(!this.repositories) return;
		$('repo_list').update('');
		this.repositories.each(function(pair){
			var deleteButton = '';
			var index = pair.key;
			var xmlNode = pair.value;
			var item = new Element('div', {className:'user user_id'}).update('<img align="absmiddle" src="'+ajxpResourcesFolder+'/images/crystal/actions/32/folder_red.png" width="32" height="32" style="padding-right:5px;"><b>'+xmlNode.getAttribute("display")+'</b>');
			item.observe("click", function(e){
				$$('div.user').each(function(div){
					div.setStyle({backgroundColor:"#fff",border:"1px solid #fff",borderWidth:"1 0 1 1"});
				});
				item.setStyle({backgroundColor:"#ddd",border:"1px solid #bbb",borderWidth:"1 0 1 1"});
				this.loadRepository(index);
			}.bind(this));
			$('repo_list').insert({"bottom":item});
		}.bind(this));
	},
	
	loadRepository : function(repId){

		var repo = this.repositories.get(repId);
		
		var driverName = repo.getAttribute("accessType");
		var driver = this.drivers.get(driverName);
				
		var fieldset = new Element('fieldset');
		var form = new Element('div');
		fieldset.update(new Element('legend').update(driverName.toUpperCase()+' Driver Options'));
		fieldset.insert({bottom:form});
		
		var paramsValues = new Hash();
		$A(repo.childNodes).each(function(child){
			if(child.nodeName != 'param') return;
			paramsValues.set(child.getAttribute('name'), child.getAttribute('value'));
		});
		
		this.createParametersInputs(form, driver.get('params'), true, paramsValues);

		var submitButton = new Element("input", {type:"button",value:"SAVE CHANGES"});
		submitButton.observe("click", function(e){
			var toSubmit = new Hash();
			toSubmit.set("repository_id", repId);
			this.submitParametersInputs(form, toSubmit, 'DRIVER_OPTION_');
			this.submitForm('edit_repository', toSubmit, null, function(){
				this.loadRepList();
				this.loadUsers();
			}.bind(this));			
		}.bind(this));
		fieldset.insert({bottom:new Element('div', {align:'right'}).update(submitButton)});
		
		$('repo_detail_panel').update(fieldset);
		
		var writeable = repo.getAttribute("writeable");
		if(!writeable || writeable != "1") return;
		
		var labelSet = new Element('fieldset');
		labelSet.update('<legend>Repository Label</legend>');
		labelInput = new Element('input', {type:"text", value:repo.getAttribute("display")});
		labelSave = new Element('input', {type:"button", value:"SAVE"});
		labelSet.insert(labelInput);
		labelSet.insert(labelSave);
		labelSave.observe("click", function(){
			this.submitForm('edit_repository', new Hash({repository_id:repId,newLabel:labelInput.getValue()}), null, function(){
				this.loadRepList();
				this.loadUsers();
			}.bind(this) );
		}.bind(this));
		
		$('repo_detail_panel').insert({top:labelSet});
		
		
		var deleteSet = new Element('fieldset').update('<legend>Delete Repository</legend>Check the box to confirm deletion :');
		var deleteBox = new Element('input', {type:"checkbox"});
		var deleteButton = new Element('input', {type:"button", value:"Delete"});
		deleteSet.insert(deleteBox);
		deleteSet.insert(deleteButton);
		
		deleteButton.observe('click', function(){
			if(!deleteBox.checked) {
				alert("Please check the box to confirm!");
				return;
			}
			this.deleteRepository(repId);
		}.bind(this));
		
		$('repo_detail_panel').insert(deleteSet);
	},
	
	deleteRepository : function(repLabel){
		var params = new Hash();
		params.set('repository_id', repLabel);
		this.submitForm('delete_repository', params, null, function(){
			this.loadRepList();
			this.loadUsers();
		}.bind(this));
	},
	
	loadLogList : function(){
		this.submitForm('list_logs', new Hash());
	},
	
	updateLogsSelector : function(){
		var selector = $('log_selector');
		if(!this.logFiles || !selector) return;
		this.logFiles.each(function(pair){
			var option = new Element('option');
			option.setAttribute('value', pair.key);
			option.update(pair.value);
			selector.insert({'top':option});
		});
		selector.onchange = this.logSelectorChange.bind(this);
		// Select first
		selector.selectedIndex = 0;
		this.logSelectorChange();
		
	},
	
	logSelectorChange : function(){
		if($('log_selector').getValue()) this.loadLogs($('log_selector').getValue());
	},
	
	loadLogs : function(date){
		var param = new Hash();
		param.set('date', date);
		this.submitForm('read_log', param, null, this.updateLogBrowser.bind(this));
	},
	
	updateLogBrowser : function(xmlResponse){
		if(xmlResponse == null || xmlResponse.documentElement == null) return;
		browser = $('log_browser');
		var childs = xmlResponse.documentElement.childNodes;
		this.even = true;
		var table = new Element('table', {width:'100%', className:'logs_table',cellPadding:'0',cellSpacing:'1'});
		browser.update(table);
		this.insertRow(table, ["Date","IP","Level","User", "Action", "Parameters"], true);
		for(var i=0;i<childs.length;i++){
			var child = childs[i];
			this.insertRow(table, [
				child.getAttribute("date"),
				child.getAttribute("ip"),
				child.getAttribute("level"),
				child.getAttribute("user"),
				child.getAttribute("action"),
				child.getAttribute("params"),
			], false);			
			if(i>1 && i%8==0){
				this.insertRow(table, ["Date","IP","Level","User", "Action", "Parameters"], true);
			}
		}
		var transp = new Element('div');
		browser.insert({'bottom':transp});
		browser.scrollTop = transp.offsetTop;
	},
	
	insertRow : function(table, values, isHeader){		
		var tdSt = '<tr>';
		var className="";
		if(!isHeader && !this.even){className="odd"};
		this.even = !this.even;
		values.each(function(cell){
			if(cell){
			while(cell.indexOf(';')>-1) cell = cell.replace(';', '<br>');
			while(cell.indexOf(',')>-1) cell = cell.replace(',', '<br>');
			tdSt = tdSt + '<td class="'+(isHeader?'header':className)+'">'+cell+'</td>';
			}
		});
		tsSt = tdSt+'</tr>';
		table.insert({'bottom':tdSt});
	},
	
	changeUserRight: function(oChckBox, userId, repositoryId, rightName){	
		var changedBox = rightName;
		var newState = oChckBox.checked;
		oChckBox.checked = !oChckBox.checked;
		oChckBox.disabled = true;
		
		var rightString;
		
		if(rightName == 'read') 
		{
			$('chck_'+userId+'_'+repositoryId+'_write').disabled = true;
			rightString = (newState?'r':'');
		}
		else 
		{
			$('chck_'+userId+'_'+repositoryId+'_read').disabled = true;
			rightString = (newState?'rw':($('chck_'+userId+'_'+repositoryId+'_read').checked?'r':''));
		}
				
		var parameters = new Hash();
		parameters.set('user_id', userId);
		parameters.set('repository_id', repositoryId);
		parameters.set('right', rightString);
		this.submitForm('update_user_right', parameters, null);
	},
	
	changeAdminRight: function(oChckBox, userId){
		var boxValue = oChckBox.checked;
		var parameters = new Hash();
		parameters.set('user_id', userId);
		parameters.set('right_value', (boxValue?'1':'0'));
		this.submitForm('change_admin_right', parameters, null);
	},
	
	changePassword: function(userId){
		var newPass = $('new_pass_'+userId);
		var newPassConf = $('new_pass_confirm_'+userId);
		if(newPass.value == '') return;
		if(newPass.value != newPassConf.value){
			 this.displayMessage('ERROR', 'Warning, password and confirmation differ!');
			 return;
		}
		parameters = new Hash();
		parameters.set('user_id', userId);
		parameters.set('user_pwd', hex_md5(newPass.value));
		this.submitForm('update_user_pwd', parameters, null);
		newPass.value = '';
		newPassConf.value = '';
	},
	
	createUser: function (){
		var login = $('new_user_login');
		var pass = $('new_user_pwd');
		var passConf = $('new_user_pwd_conf');
		if(login.value == ''){
			this.displayMessage("ERROR", "Please fill the login field!");
			return;
		}
		if(pass.value == '' || passConf.value == ''){
			this.displayMessage("ERROR", "Please fill both password fields!");
			return;
		}
		if(pass.value != passConf.value){
			this.displayMessage("ERROR", "Password and confirmation differ!");
			return;
		}
		
		var parameters = new Hash();
		parameters.set('new_login', login.value);
		parameters.set('new_pwd', pass.value);
		this.submitForm('create_user', parameters, null);
		login.value = pass.value = passConf.value = '';
		return;
		
	},
	
	addRepositoryUserParams : function(userId){
		if($('user_data_'+userId).getAttribute('repoParams')) return;				
		this.drivers.each(function(pair){
			if(!pair.value.get('user_params') || !pair.value.get('user_params').length) return;
			$('user_data_'+userId).select("td[driver_name='"+pair.key+"']").each(function(cell){
				var repoId = cell.getAttribute('repository_id');
				var newTd = new Element('td', {colspan:2, className:'driver_form', id:'repo_user_params_'+userId+'_'+repoId});
				var newTr = new Element('tr').update(newTd);
				cell.up('tr').insert({after:newTr});
				
				var repoValues = $H({});
				$('user_data_'+userId).select("wallet_data[repo_id='"+repoId+"']").each(function(tag){					
					repoValues.set(tag.getAttribute('option_name'), tag.getAttribute('option_value'));
				});				
				this.createParametersInputs(newTd, pair.value.get('user_params'), false, repoValues);
				var submitButton = new Element('input', {type:'submit', value:'SAVE', className:'submit'});
				submitButton.observe("click", function(){
					this.submitUserParamsForm(userId, repoId);
				}.bind(this));
				newTd.insert(submitButton);
			}.bind(this));
		}.bind(this));
		$('user_data_'+userId).writeAttribute('repoParams', 'true');
	},
	
	submitUserParamsForm : function(userId, repositoryId){
		var parameters = new Hash();
		parameters.set('user_id', userId);
		parameters.set('repository_id', repositoryId);
		if(this.submitParametersInputs($('repo_user_params_'+userId+'_'+repositoryId), parameters, "DRIVER_OPTION_")){
			this.displayMessage("ERROR", "Mandatory fields are missing!");
			return false;
		}
		this.submitForm('save_repository_user_params', parameters, null);
	},
	
	deleteUser: function(userId){
		var chck = $('delete_confirm_'+userId);
		if(!chck.checked){
			this.displayMessage("ERROR", "Please check the box to confirm!");
			return;
		}
		parameters = new Hash();
		parameters.set('user_id', userId);
		this.submitForm('delete_user', parameters, null);
		chck.checked = false;
	},
	
	toggleUser: function (userId){
		var color;
		if($('user_data_'+userId).visible())
		{
			// closing
			color = "#fff";
			border = "#fff";
		}
		else
		{
			// opening
			$$('div.user').each(function(element){
				element.setStyle({
					backgroundColor:"#fff",
					borderColor: "#fff"
				});
			});
			$$('div.user_data').each(function(element){
				element.hide();
			});
			color = "#ddd";
			border = "#bbb";
		}
		$('user_block_'+userId).setStyle({
			backgroundColor:color,
			borderColor: border
		});
		//$('user_data_'+userId).toggle();	
		$('users_detail_panel').insert({top:$('user_data_'+userId)});
		$('user_data_'+userId).toggle();
		this.addRepositoryUserParams(userId);
	},
	
	submitForm: function(action, parameters, formName, callback){
		var connexion = new Connexion('admin.php');
		if(formName)
		{
			$(formName).getElements().each(function(fElement){
				connexion.addParameter(fElement.name, fElement.getValue());
			});	
		}
		if(parameters)
		{
			parameters.set('get_action', action);
			connexion.setParameters(parameters);
		}
		if(!callback){
			connexion.onComplete = function(transport){this.parseXmlMessage(transport.responseXML);}.bind(this);
		}else{
			connexion.onComplete = function(transport){
				this.parseXmlMessage(transport.responseXML);
				callback(transport.responseXML);
			}.bind(this);
		}
		connexion.sendAsync();
	},
	
	loadHtmlToDiv: function(div, parameters, completeFunc){
		var connexion = new Connexion('admin.php');
		parameters.each(function(pair){
			connexion.addParameter(pair.key, pair.value);
		});
		connexion.onComplete = function(transport){		
			$(div).update(transport.responseText);
			if(completeFunc) completeFunc();
		};
		connexion.sendAsync();	
	},
	
	
	parseXmlMessage: function(xmlResponse){
		//var messageBox = ajaxplorer.messageBox;
		if(xmlResponse == null || xmlResponse.documentElement == null) return;
		var childs = xmlResponse.documentElement.childNodes;	
		var driversList = false;
		var driversAtts = $A(['name', 'type', 'label', 'description', 'default', 'mandatory']);
		var repList = false;
		var logFilesList = false;
		var logsList = false;
		
		for(var i=0; i<childs.length;i++)
		{
			if(childs[i].nodeName == "message")
			{
				this.displayMessage(childs[i].getAttribute('type'), childs[i].firstChild.nodeValue);
				//alert(childs[i].firstChild.nodeValue);
			}
			else if(childs[i].nodeName == "update_checkboxes")
			{
				var userId = childs[i].getAttribute('user_id');
				var repositoryId = childs[i].getAttribute('repository_id');
				var read = childs[i].getAttribute('read');
				var write = childs[i].getAttribute('write');
				if(read != 'old') $('chck_'+userId+'_'+repositoryId+'_read').checked = (read=='1'?true:false);
				$('chck_'+userId+'_'+repositoryId+'_read').disabled = false;
				if(write != 'old') $('chck_'+userId+'_'+repositoryId+'_write').checked = (write=='1'?true:false);
				$('chck_'+userId+'_'+repositoryId+'_write').disabled = false;
			}
			else if(childs[i].nodeName == "refresh_user_list")
			{
				this.loadUsers();
			}
			else if(childs[i].nodeName == "ajxpdriver")
			{
				driversList = true;
				if(!this.drivers) this.drivers = new Hash();
				var dOption = new Hash();
				var dName = childs[i].getAttribute('name');
				dOption.set('label', childs[i].getAttribute('label'));
				dOption.set('description', childs[i].getAttribute('description'));
				var params = $A([]);
				var userParams = $A([]);
				var dChilds = childs[i].childNodes;
				for(var j=0;j<dChilds.length;j++){
					var childNodeName = dChilds[j].nodeName;
					if(childNodeName == 'param' || childNodeName == 'user_param'){
						var paramProp = new Hash();
						driversAtts.each(function(attName){
							paramProp.set(attName, (dChilds[j].getAttribute(attName) || ''));
						});
						if(childNodeName == 'param') params.push(paramProp);
						else userParams.push(paramProp);
					}
				}
				dOption.set('params', params);
				dOption.set('user_params', userParams);
				this.drivers.set(dName, dOption);
			}
			else if(childs[i].nodeName == "repository")
			{
				if(!this.repositories || !repList) this.repositories = new Hash();
				repList = true;
				this.repositories.set(childs[i].getAttribute('index'), childs[i]);
			}
			else if(childs[i].nodeName == "file"){
				if(!this.logFiles) this.logFiles = new Hash();
				logFilesList = true;
				this.logFiles.set(childs[i].getAttribute('date'), childs[i].getAttribute('display'));
			}
		}
		if(driversList){
			this.updateDriverSelector();
		}
		if(repList){
			this.updateRepList();
		}
		if(logFilesList){
			this.updateLogsSelector();
		}
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
		setTimeout(function(){this.closeMessageDiv();}.bind(this), 3000);
	},
	
	displayMessage: function(messageType, message){
		this.messageBox = $('message_div');
		message = message.replace(new RegExp("(\\n)", "g"), "<br>");
		if(messageType == "ERROR"){ this.messageBox.removeClassName('logMessage');  this.messageBox.addClassName('errorMessage');}
		else { this.messageBox.removeClassName('errorMessage');  this.messageBox.addClassName('logMessage');}
		$('message_content').innerHTML = message;
		this.messageBox.style.top = '80%';
		this.messageBox.style.left = '60%';
		this.messageBox.style.width = '30%';
		new Effect.Corner(this.messageBox,"round");
		new Effect.Appear(this.messageBox);
		this.tempoMessageDivClosing();
	}
});