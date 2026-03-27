{* Admin: Leads List *}
<div class="panel">
  <div class="panel-heading">
    <i class="icon-group"></i> {l s='Captured Leads' mod='leadtracker'}
  </div>

  {* Stats row *}
  <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;padding:16px 0 0;">
    {foreach from=[
      ['label'=>'Total Leads',   'value'=>$stats.total_leads,    'color'=>'#1a237e'],
      ['label'=>'Today',         'value'=>$stats.today_leads,    'color'=>'#00695c'],
      ['label'=>'Cart Events',   'value'=>$stats.total_carts,    'color'=>'#e65100'],
      ['label'=>'Checkouts',     'value'=>$stats.total_checkouts,'color'=>'#6a1b9a'],
    ] item=s}
    <div style="flex:1;min-width:130px;background:#f8f8f8;border:1px solid #ddd;border-left:4px solid {$s.color};
                border-radius:6px;padding:12px 14px;text-align:center;">
      <div style="font-size:22px;font-weight:700;color:{$s.color};">{$s.value}</div>
      <div style="font-size:11px;color:#777;margin-top:2px;">{$s.label}</div>
    </div>
    {/foreach}
  </div>

  {* Filters *}
  <form method="GET" action="{$current_url}" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:flex-end;background:#f8f8f8;padding:12px;border-radius:6px;border:1px solid #e0e0e0;">
    <input type="hidden" name="controller" value="AdminLeadTracker" />
    <input type="hidden" name="token" value="{$smarty.get.token|escape:'html'}" />

    <div>
      <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;color:#555;">Mobile number</label>
      <input type="text" name="filter_mobile" value="{$filters.mobile|escape:'html'}" placeholder="e.g. 9876543210"
             class="form-control" style="width:160px;height:32px;" />
    </div>
    <div>
      <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;color:#555;">From</label>
      <input type="date" name="filter_date_from" value="{$filters.date_from|escape:'html'}" class="form-control" style="height:32px;" />
    </div>
    <div>
      <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;color:#555;">To</label>
      <input type="date" name="filter_date_to" value="{$filters.date_to|escape:'html'}" class="form-control" style="height:32px;" />
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="{$current_url}" class="btn btn-default btn-sm">Clear</a>
  </form>

  {* Table *}
  {if $leads}
  <div class="table-responsive">
    <table class="table table-bordered table-striped table-hover" style="font-size:13px;">
      <thead>
        <tr style="background:#1a237e;color:#fff;">
          <th>#</th>
          <th>Mobile</th>
          <th>Source</th>
          <th>IP Address</th>
          <th style="text-align:center;">Events</th>
          <th>Captured At</th>
          <th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
      {foreach from=$leads item=lead}
        <tr>
          <td style="color:#999;">{$lead.id_lead}</td>
          <td><strong>+91 {$lead.mobile_normalized|escape:'html'}</strong></td>
          <td>
            {if $lead.source == 'url_param'}<span class="badge" style="background:#1565c0;">URL</span>
            {elseif $lead.source == 'customer'}<span class="badge" style="background:#2e7d32;">Customer</span>
            {elseif $lead.source == 'cookie'}<span class="badge" style="background:#00838f;">Cookie</span>
            {else}<span class="badge" style="background:#555;">Manual</span>
            {/if}
          </td>
          <td style="color:#666;font-size:12px;">{$lead.ip_address|default:'—'|escape:'html'}</td>
          <td style="text-align:center;">
            <span class="badge" style="background:#e65100;">{$lead.activity_count}</span>
          </td>
          <td style="font-size:12px;white-space:nowrap;">{$lead.created_at|escape:'html'}</td>
          <td style="text-align:center;">
            <a href="{$lead.view_url|escape:'html'}" class="btn btn-xs btn-info">
              View Timeline
            </a>
          </td>
        </tr>
      {/foreach}
      </tbody>
    </table>
  </div>

  {* Pagination *}
  {if $total_pages > 1}
  <div style="text-align:center;margin-top:12px;">
    {section name=pg loop=$total_pages}
      {assign var=pnum value=$smarty.section.pg.index+1}
      <a href="{$current_url}&p={$pnum}{if $filters.mobile}&filter_mobile={$filters.mobile|escape:'url'}{/if}{if $filters.date_from}&filter_date_from={$filters.date_from|escape:'url'}{/if}{if $filters.date_to}&filter_date_to={$filters.date_to|escape:'url'}{/if}"
         class="btn btn-sm {if $pnum == $page}btn-primary{else}btn-default{/if}" style="margin:2px;">{$pnum}</a>
    {/section}
  </div>
  {/if}

  <p style="color:#aaa;font-size:11px;margin-top:8px;">Showing {$leads|count} of {$total_leads} leads</p>

  {else}
  <div class="alert alert-info" style="margin-top:12px;">
    No leads found yet. Once visitors land on your store and their mobile is resolved, leads will appear here.
  </div>
  {/if}
</div>