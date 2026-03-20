{* LeadTracker - Header template: injects JS configuration *}
<script>
window.LeadTrackerConfig = {
    ajaxUrl:        "{$lt_ajax_url|escape:'javascript'}",
    customerMobile: "{$lt_customer_mobile|escape:'javascript'}",
    cookieDays:     {$lt_cookie_days|intval},
    showPopup:      {if $lt_show_popup}true{else}false{/if},
    gdprMode:       {if $lt_gdpr_mode}true{else}false{/if},
    trackPageview:  {if $lt_track_pageview}true{else}false{/if},
    trackProduct:   {if $lt_track_product}true{else}false{/if},
    trackCart:      {if $lt_track_cart}true{else}false{/if},
    trackCheckout:  {if $lt_track_checkout}true{else}false{/if},
    sessionId:      "{$lt_session_id|escape:'javascript'}",
    pageUrl:        "{$lt_page_url|escape:'javascript'}",
    controller:     "{$lt_controller|escape:'javascript'}"
};
</script>
