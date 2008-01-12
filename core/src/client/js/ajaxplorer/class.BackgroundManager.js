BackgroundManager = Class.create({
	queue: $A([]),
	initialize:function(actionManager){
		this.actionManager = actionManager;
		this.panel = new Element('<div>').addClassName('backgroundPanel');
		this.panel.hide();
		this.working = false;
		document.body.insert(this.panel);
	},
	
	queueAction:function(actionName, parameters,messageId){
		var actionDef = new Hash();
		actionDef.set('name', actionName);
		actionDef.set('messageId', messageId);
		actionDef.set('parameters', parameters);
		this.queue.push(actionDef);
	},
	
	next:function(){
		if(!this.queue.size()){
			 this.finished();
			 return;
		}
		if(this.working) return;
		var actionDef = this.queue[0];
		var connexion = new Connexion();
		connexion.setParameters(actionDef.get('parameters'));
		connexion.addParameter('get_action', actionDef.get('name'));		
		connexion.onComplete = function(transport){
			this.parseAnswer(transport.responseXML);
		}.bind(this);
		connexion.sendAsync();		
		this.panel.update(actionDef.get('messageId'));
		this.panel.show();
		new Effect.Corner(this.panel, "round 3px");		
		this.queue.shift();
		this.working = true;
	},
	
	parseAnswer:function(xmlResponse){
		if(xmlResponse == null || xmlResponse.documentElement == null) return;
		var childs = xmlResponse.documentElement.childNodes;	
		
		for(var i=0; i<childs.length;i++)
		{
			if(childs[i].tagName == "message")
			{
				var type = childs[i].getAttribute('type');
				if(type != 'SUCCESS'){
					return this.interruptOnError(childs[i].firstChild.nodeValue);
				}
			}
			else if(childs[i].tagName == "trigger_bg_action"){
				var name = childs[i].getAttribute("name");
				var messageId = childs[i].getAttribute("messageId");
				var parameters = new Hash();
				for(var j=0;j<childs[i].childNodes.length;j++){
					var paramChild = childs[i].childNodes[j];
					if(paramChild.tagName == 'param'){
						parameters.set(paramChild.getAttribute("name"), paramChild.getAttribute("value"));
					}
				}
				this.queueAction(name, parameters, messageId);
			}
		}
		this.working = false;
		this.next();
	},
	
	interruptOnError:function(errorMessage){
		if(this.queue.size()) this.queue = $A([]);
		
		this.panel.update(errorMessage);
		this.panel.insert(this.makeCloseLink());
		this.working = false;
	},
	
	finished:function(){		
		this.working = false;
		this.panel.hide();
	},
	
	makeCloseLink:function(){
		var link = new Element('a', {href:'#'}).update('Close').observe('click', function(e){
			Event.stop(e);
			this.panel.hide();
		}.bind(this));
		return link;
	},
	
	addStub: function(){		
		this.queueAction('local_to_remote', new Hash(), 'Stubing a 10s bg action');
	}
});