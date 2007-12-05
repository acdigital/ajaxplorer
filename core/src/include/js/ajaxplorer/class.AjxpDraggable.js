// TO ADD GLOBALLY
var timerClearObserver = {
	onEnd:function(){
		if(WebFXtimer) clearTimeout(WebFXtimer);
	}
}
Event.observe(window, "load", function(){
	Draggables.addObserver(timerClearObserver);
	//Event.observe('tree_container', "mouseout", function(){timerClearObserver.onEnd();});
});
Event.observe(window, "unload", function(){
	Draggables.removeObserver(timerClearObserver);
	ajaxplorer.filesList.allDraggables.each(function(el){
		el.destroy();
	});
	ajaxplorer.filesList.allDroppables.each(function(el){
		Droppables.remove(el);
	});
});

var AjxpDraggable = Class.create(Draggable, {
	
    startDrag : function(event){
	
    if(!this.delta)
    this.delta = this.currentDelta();

	this.dragging = true;
	this._draggingMultiple = false;

	if(this.options.zindex) {
		this.originalZ = parseInt(Element.getStyle(this.element,'z-index') || 0);
		this.element.style.zIndex = this.options.zindex;
	}

	if(this.options.ghosting) {
		var selection = ajaxplorer.filesList.getUserSelection();
		//console.log(selection);
		if(selection.isMultiple()){
			// Move all selection
			// Make new div for element, clone all others.			
			this._draggingMultiple = true;
			this._clone = document.createElement('div');			
			if(ajaxplorer.filesList._displayMode == 'thumb'){
				$(this._clone).addClassName('multiple_thumbnails_draggable');
			}else{
				$(this._clone).addClassName('multiple_selection_draggable');
			}
			this._clone.setAttribute('user_selection', 'true');
			if (browser == 'Internet Explorer'){
				$('browser').appendChild(this._clone);
				$(this._clone).setStyle({width:$(this.element).getWidth()+'px'});
			}else{
				this.element.parentNode.appendChild(this._clone);
			}
			this.original = this.element;
			this.element = this._clone;
			var selectedItems = ajaxplorer.filesList.getSelectedItems();			
			for(var i=0; i<selectedItems.length;i++)
			{	
				var objectToClone;
				if(ajaxplorer.filesList._displayMode == 'thumb'){
					 objectToClone = $(selectedItems[i]);
				}
				else {
					objectToClone = $(selectedItems[i]).getElementsBySelector('span.list_selectable_span')[0];
				}
				var newObj = refreshPNGImages(objectToClone.cloneNode(true));				
				this.element.appendChild(newObj);
				if(ajaxplorer.filesList._displayMode == 'thumb'){
					$(newObj).addClassName('simple_selection_draggable');
				}
			}
			Position.absolutize(this.element);
		}else{
			this._clone = this.element.cloneNode(true);
			refreshPNGImages(this._clone);
			Position.absolutize(this.element);
			this.element.parentNode.insertBefore(this._clone, this.element);
			$(this.element).addClassName('simple_selection_draggable');
			if(browser == 'Internet Explorer') // MOVE ELEMENT TO $('browser')
			{
				var newclone = this.element.cloneNode(true);
				refreshPNGImages(newclone);
				$('browser').appendChild(newclone);
				$(newclone).setStyle({width:$(this._clone).getWidth()+'px'});
				Element.remove(this.element);
				this.element = newclone;				
			}
		}
	}

	if(this.options.scroll) {
		if (this.options.scroll == window) {
			var where = this._getWindowScroll(this.options.scroll);
			this.originalScrollLeft = where.left;
			this.originalScrollTop = where.top;
		} else {
			this.originalScrollLeft = this.options.scroll.scrollLeft;
			this.originalScrollTop = this.options.scroll.scrollTop;
		}
	}

	Draggables.notify('onStart', this, event);

	if(this.options.starteffect){
		this.options.starteffect(this.element);
	}
	

},

	finishDrag : function(event, success) {
	this.dragging = false;

	if(this.options.quiet){
		Position.prepare();
		var pointer = [Event.pointerX(event), Event.pointerY(event)];
		Droppables.show(pointer, this.element);
	}

	if(this.options.ghosting && !this._draggingMultiple) {
		this.removeCopyClass();
		if(browser == 'Internet Explorer') // MOVE ELEMENT TO $('browser')
		{
			this._clone.parentNode.insertBefore(this.element, this._clone);
		}
		this.element.removeClassName('simple_selection_draggable');
		Position.relativize(this.element);
		Element.remove(this._clone);
		this._clone = null;
	}

	var dropped = false;
	if(success) {
		dropped = Droppables.fire(event, this.element);
		if (!dropped) dropped = false;
	}
	if(dropped && this.options.onDropped) this.options.onDropped(this.element);
	Draggables.notify('onEnd', this, event);

	var revert = this.options.revert;
	if(revert && typeof revert == 'function') revert = revert(this.element);

	var d = this.currentDelta();	
	if(revert && this.options.reverteffect) {
		if (dropped == 0 || revert != 'failure'){
			if(!this._draggingMultiple){
				this.options.reverteffect(this.element,	d[1]-this.delta[1], d[0]-this.delta[0]);
			}
		}
	} else {
		this.delta = d;
	}

	if(this.options.zindex){
		this.element.style.zIndex = this.originalZ;
	}

	if(this.options.endeffect){
			this.options.endeffect(this.element);
	}
	
	if(this._draggingMultiple){
		var selectDiv = this.element;
		this.element = this.original;
		Element.remove(selectDiv);
	}

	Draggables.deactivate(this);
	Droppables.reset();
}, 

	updateCtrlKey: function(event)
	{
		var ctrl = event['ctrlKey'];		
		if(ctrl){
			this.addCopyClass();
		}else{
			this.removeCopyClass();
		}
	},

	addCopyClass : function()
	{
		if(this._draggingMultiple && ajaxplorer.filesList._displayMode == 'thumb')
		{
			$(this.element).select('div.thumbnail_selectable_cell').each(function(el){
				el.addClassName('selection_ctrl_key');
			});
		}else{
			$(this.element).addClassName('selection_ctrl_key');
		}
	},
	
	removeCopyClass : function()
	{
		if(this._draggingMultiple && ajaxplorer.filesList._displayMode == 'thumb')
		{
			$(this.element).select('div.thumbnail_selectable_cell').each(function(el){
				el.removeClassName('selection_ctrl_key');
			});
		}else{
			$(this.element).removeClassName('selection_ctrl_key');
		}
	}
	
});


Draggables.addObserver({onDrag:function(eventName,element,event){
	if(element.updateCtrlKey){
		element.updateCtrlKey(event);
	}
}});