{$form_id = uniqid()}
<form action="{devblocks_url}{/devblocks_url}" method="post" id="{$form_id}">
<input type="hidden" name="c" value="profiles">
<input type="hidden" name="a" value="handleSectionAction">
<input type="hidden" name="section" value="saved_search">
<input type="hidden" name="action" value="savePeekJson">
<input type="hidden" name="view_id" value="{$view_id}">
{if !empty($model) && !empty($model->id)}<input type="hidden" name="id" value="{$model->id}">{/if}
<input type="hidden" name="do_delete" value="0">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

<fieldset class="peek" style="background:none;border:0;">
	
	<table cellspacing="0" cellpadding="2" border="0" width="98%">
		<tr>
			<td width="1%" nowrap="nowrap"><b>{'common.name'|devblocks_translate}:</b></td>
			<td width="99%">
				<input type="text" name="name" value="{$model->name}" style="width:98%;" autofocus="autofocus">
			</td>
		</tr>
		
		<tr>
			<td width="1%" nowrap="nowrap"><b>{'common.tag'|devblocks_translate}:</b></td>
			<td width="99%">
				<input type="text" name="tag" value="{$model->tag}" style="width:98%;" placeholder="e.g. eu-sales (letters, numbers, dash)">
			</td>
		</tr>
		
		<tr>
			<td width="1%" valign="top" nowrap="nowrap"><b>{'common.owner'|devblocks_translate|capitalize}:</b></td>
			<td width="99%">
				{include file="devblocks:cerberusweb.core::internal/peek/menu_actor_owner.tpl"}
			</td>
		</tr>
		
		<tr>
			<td width="1%" nowrap="nowrap" valign="top">
				<b>{'common.type'|devblocks_translate|capitalize}:</b><br>
			</td>
			<td width="99%">
				<select name="context">
					{foreach from=$contexts item=ctx key=k}
					<option value="{$k}" {if $model->context==$k}selected="selected"{/if}>{$ctx->name}</option>
					{/foreach}
				</select>
			</td>
		</tr>
		
		<tr>
			<td width="1%" valign="top" nowrap="nowrap"><b>{'common.query'|devblocks_translate|capitalize}:</b></td>
			<td width="99%">
				<input type="text" name="query" value="{$model->query}" style="width:100%;" class="cerb-query-trigger" data-context="{$model->context}">
			</td>
		</tr>
	</table>
	
</fieldset>

{if !empty($model->id)}
<fieldset style="display:none;" class="delete">
	<legend>{'common.delete'|devblocks_translate|capitalize}</legend>
	
	<div>
		Are you sure you want to permanently delete this saved search?
	</div>
	
	<button type="button" class="delete red"></span> {'common.yes'|devblocks_translate|capitalize}</button>
	<button type="button" onclick="$(this).closest('form').find('div.buttons').fadeIn();$(this).closest('fieldset.delete').fadeOut();"></span> {'common.no'|devblocks_translate|capitalize}</button>
</fieldset>
{/if}

<div class="status"></div>

<div class="buttons">
	<button type="button" class="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
	{if !empty($model->id)}<button type="button" onclick="$(this).parent().siblings('fieldset.delete').fadeIn();$(this).closest('div').fadeOut();"><span class="glyphicons glyphicons-circle-remove" style="color:rgb(200,0,0);"></span> {'common.delete'|devblocks_translate|capitalize}</button>{/if}
</div>

</form>

<script type="text/javascript">
$(function() {
	var $frm = $('#{$form_id}');
	var $popup = genericAjaxPopupFind($frm);
	
	$popup.one('popup_open', function(event,ui) {
		$popup.dialog('option','title',"{'Saved Search'|capitalize|escape:'javascript' nofilter}");
		$popup.css('overflow', 'inherit');
		
		var $context = $popup.find('select[name=context]');
		var $query = $popup.find('.cerb-query-trigger');
		
		// Change the query context when the dropdown changes
		
		$context.change(function(e) {
			$query.attr('data-context', $(this).val());
		});

		// Buttons
		
		$popup.find('button.submit').click(Devblocks.callbackPeekEditSave);
		$popup.find('button.delete').click({ mode: 'delete' }, Devblocks.callbackPeekEditSave);
		
		// Query builders
		
		$query
			.cerbQueryTrigger()
			.on('cerb-query-saved', function(e) {
				//var $trigger = $(this);
				//$trigger.val(e.worklist_quicksearch);
			})
		;
		
		// Owners
		
		var $owners_menu = $popup.find('ul.owners-menu');
		var $ul = $owners_menu.siblings('ul.chooser-container');
		
		$popup.find('.cerb-peek-trigger').cerbPeekTrigger();
		
		$ul.on('bubble-remove', function(e, ui) {
			e.stopPropagation();
			$(e.target).closest('li').remove();
			$ul.hide();
			$owners_menu.show();
		});
		
		$owners_menu.menu({
			select: function(event, ui) {
				var token = ui.item.attr('data-token');
				var label = ui.item.attr('data-label');
				
				if(undefined == token || undefined == label)
					return;
				
				$owners_menu.hide();
				
				// Build bubble
				
				var context_data = token.split(':');
				var $li = $('<li/>');
				var $label = $('<a href="javascript:;" class="cerb-peek-trigger no-underline" />').attr('data-context',context_data[0]).attr('data-context-id',context_data[1]).text(label);
				$label.cerbPeekTrigger().appendTo($li);
				var $hidden = $('<input type="hidden">').attr('name', 'owner').attr('value',token).appendTo($li);
				ui.item.find('img.cerb-avatar').clone().prependTo($li);
				var $a = $('<a href="javascript:;" onclick="$(this).trigger(\'bubble-remove\');"><span class="glyphicons glyphicons-circle-remove"></span></a>').appendTo($li);
				
				$ul.find('> *').remove();
				$ul.append($li);
				$ul.show();
			}
		});
		
	});
});
</script>
