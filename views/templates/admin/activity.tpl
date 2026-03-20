{* Admin: Lead Activity Timeline *}
<div class="panel">
  <div class="panel-heading" style="display:flex;justify-content:space-between;align-items:center;">
    <span><i class="icon-list"></i> Activity Timeline</span>
    <a href="{$back_url|escape:'html'}" class="btn btn-default btn-sm">← Back to Leads</a>
  </div>
  <div class="panel-body">

    {if $error}
    <div class="alert alert-danger">{$error|escape:'html'}</div>
    {else}

    {* Lead info cards *}
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
      <div style="background:#f0f4ff;border-left:4px solid #1a237e;border-radius:6px;padding:10px 14px;">
        <div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:700;">Mobile</div>
        <div style="font-weight:700;font-size:16px;">+91 {$lead->mobile_normalized|escape:'html'}</div>
      </div>
      <div style="background:#f8f8f8;border-left:4px solid #aaa;border-radius:6px;padding:10px 14px;">
        <div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:700;">Source</div>
        <div style="font-weight:600;">{$lead->source|escape:'html'}</div>
      </div>
      <div style="background:#f8f8f8;border-left:4px solid #aaa;border-radius:6px;padding:10px 14px;">
        <div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:700;">IP Address</div>
        <div style="font-weight:600;">{$lead->ip_address|default:'Unknown'|escape:'html'}</div>
      </div>
      <div style="background:#f8f8f8;border-left:4px solid #aaa;border-radius:6px;padding:10px 14px;">
        <div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:700;">First Seen</div>
        <div style="font-weight:600;">{$lead->created_at|escape:'html'}</div>
      </div>
    </div>

    {* Timeline *}
    {if $activities}
    {assign var=eventColors value=['pageview'=>'#90a4ae','product_view'=>'#42a5f5','add_to_cart'=>'#ef6c00','checkout'=>'#7b1fa2','order'=>'#2e7d32']}
    {assign var=eventLabels value=['pageview'=>'Page View','product_view'=>'Product View','add_to_cart'=>'Add to Cart','checkout'=>'Checkout','order'=>'Order']}

    <div style="position:relative;padding-left:28px;">
      <div style="position:absolute;left:8px;top:0;bottom:0;width:2px;background:#e0e0e0;"></div>

      {foreach from=$activities item=act}
      {assign var=evt value=$act.event_type}
      {assign var=color value=$eventColors[$evt]|default:'#bbb'}
      {assign var=label value=$eventLabels[$evt]|default:$evt}

      <div style="position:relative;margin-bottom:16px;">
        <div style="position:absolute;left:-24px;top:14px;width:12px;height:12px;border-radius:50%;
                    background:{$color};border:2px solid #fff;box-shadow:0 0 0 2px {$color};"></div>

        <div style="background:#fff;border:1px solid #e8e8e8;border-left:3px solid {$color};
                    border-radius:6px;padding:10px 14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
            <strong style="color:{$color};font-size:13px;">{$label|escape:'html'}</strong>
            <span style="font-size:11px;color:#aaa;">{$act.created_at|escape:'html'}</span>
          </div>
          {if $act.product_name}
          <div style="font-size:12px;color:#555;margin-top:2px;">
            Product: <strong>{$act.product_name|escape:'html'}</strong>
          </div>
          {/if}
          {if $act.page_url}
          <div style="font-size:11px;color:#999;margin-top:2px;word-break:break-all;">
            {$act.page_url|escape:'html'}
          </div>
          {/if}
          {if $act.cart_total}
          <div style="font-size:12px;color:#2e7d32;margin-top:2px;font-weight:600;">
            Cart: &#x20B9;{$act.cart_total|string_format:"%.2f"}
          </div>
          {/if}
        </div>
      </div>
      {/foreach}
    </div>

    {else}
    <div class="alert alert-info">No activity recorded for this lead yet.</div>
    {/if}

    {/if}
  </div>
</div>
