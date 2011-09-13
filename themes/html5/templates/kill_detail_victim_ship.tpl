<div class="kl-detail-vicship">
{cycle reset=true print=false name=ccl values="kb-table-row-even,kb-table-row-odd"}
	<table class="kb-table">
		<col class="logo"/>
		<col class="attribute-name"/>
		<col class="attribute-data"/>
		<tr class="{cycle name="ccl"}" >
			<td class="logo" rowspan="3"><img src="{$victimShipImage}" alt="{$victimShipName}"/> </td>
			<td>Ship:</td>
			<td><a href="{$kb_host}/?a=invtype&amp;id={$victimShipID}">{$victimShipName}</a> ({$victimShipClassName})</td>
		</tr>
		<tr class="{cycle name="ccl"}">
			<td>Location:</td>
			<td><a href="{$systemURL}">{$system}</a> ({$systemSecurity})</td>
		</tr>
		<tr class="{cycle name="ccl"}">
			<td>Date:</td>
			<td>{$timeStamp}</td>
		</tr>
	{if $showiskd}
		<tr class="{cycle name="ccl"}">
			<td colspan="2">ISK Loss at time of kill:</td>
			<td>{$totalLoss}</td>
		</tr>
		<tr class="{cycle name="ccl"}">
			<td colspan="2">Total Damage Taken:</td>
			<td>{$victimDamageTaken|number_format}</td>
		</tr>
	{/if}
	</table>
</div>
