{$uniq_id = uniqid()}
<b>Find records of this type:</b>
<div style="margin:0px 0px 5px 10px;">
	<select name="{$namePrefix}[context]">
		<option value=""></option>
		{foreach from=$trigger->variables item=var key=var_key}
		{if $var.type == Model_CustomField::TYPE_SINGLE_LINE}
		<option value="{$var_key}" {if $params.context==$var_key}selected="selected"{/if}>(variable) {$var.label}</option>
		{/if}
		{/foreach}
		{foreach from=$contexts_list item=context key=context_id}
		<option value="{$context_id}" {if $params.context==$context_id}selected="selected"{/if}>{$context->name}</option>
		{/foreach}
	</select>
</div>

<b>And filter using this quick search:</b>
<div style="margin:0px 0px 5px 10px;">
	<input type="hidden" name="{$namePrefix}[search_mode]" value="quick_search">
	<textarea name="{$namePrefix}[quick_search]" class="quicksearch placeholders" style="width:95%;border-radius:5px;" autocomplete="off" spellcheck="off">{$params.quick_search}</textarea>
</div>

<b>Limit to:</b>
<div style="margin:0px 0px 5px 10px;">
	<select name="{$namePrefix}[limit]" id="select{$uniq_id}">
		<option value="" {if empty($params.limit)}selected="selected"{/if}>All objects</option>
		<option value="first" {if $params.limit=='first'}selected="selected"{/if}>First</option>
		<option value="last" {if $params.limit=='last'}selected="selected"{/if}>Last</option>
		<option value="random" {if $params.limit=='random'}selected="selected"{/if}>Random</option>
	</select>
	<span style="{if empty($params.limit)}display:none;{/if}">
		<input type="text" name="{$namePrefix}[limit_count]" size="2" maxlength="2" value="{$params.limit_count|default:'10'}">
	</span>
	<br>
</div>

<b>Then:</b>
<div style="margin:0px 0px 5px 10px;">
	<select name="{$namePrefix}[mode]">
		<option value="add" {if !isset($params.mode) || $params.mode=='add'}selected="selected"{/if}>Add these objects to the variable</option>
		<option value="subtract" {if $params.mode=='subtract'}selected="selected"{/if}>Remove these objects from the variable</option>
		<option value="replace" {if $params.mode=='replace'}selected="selected"{/if}>Replace the variable with these objects</option>
	</select>
</div>

{*
<script type="text/javascript">
$(function() {
	var $div = $('#popup{$uniq_id}');
	var $parent = $div.parent();
	var $popup = $div.closest('.ui-dialog');
	
	$parent.find('textarea').autosize();
	
	$div.click(function(e) {
		var width = $(window).width()-100;
		var $mode = $popup.parent().find('input.mode');
		var q = '';
		
		if($mode.is(':checked')) {
			q = $parent.find('input.quicksearch').val();
		}
		
		var $chooser = genericAjaxPopup("chooser{uniqid()}",'c=internal&a=chooserOpenParams&context={$context}&view_id={$view->id}&trigger_id={$trigger->id}&q=' + encodeURIComponent(q),null,true,width);
		
		$chooser.on('chooser_save',function(event) {
			if(null != event.worklist_model) {
				$div.find('span.name').text(event.view_name);
				$parent.find('input:hidden.model').val(event.worklist_model);
				$parent.find('input.quicksearch').val(event.worklist_quicksearch);
			}
		});
	});
	
	var $select = $('#select{$uniq_id}');
	
	$select.change(function(e) {
		var val = $(this).val();
		
		if(val.length > 0)
			$(this).next('span').show();
		else
			$(this).next('span').hide();
	});
	
	$popup.css('overflow', 'inherit');
});
</script>
*}