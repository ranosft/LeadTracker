{* LeadTracker - Footer template: mobile capture modal *}
<div id="lt-mobile-modal" class="lt-modal" role="dialog" aria-modal="true" aria-labelledby="lt-modal-title" style="display:none;">
    <div class="lt-modal-backdrop"></div>
    <div class="lt-modal-box">
        <div class="lt-modal-header">
            <div class="lt-modal-icon">📱</div>
            <h3 id="lt-modal-title">{l s='Stay Connected!' mod='leadtracker'}</h3>
            <p>{l s='Enter your mobile number to get exclusive offers and order updates.' mod='leadtracker'}</p>
        </div>
        <div class="lt-modal-body">
            <div class="lt-input-group">
                <span class="lt-prefix">+91</span>
                <input
                    type="tel"
                    id="lt-mobile-input"
                    placeholder="{l s='10-digit mobile number' mod='leadtracker'}"
                    maxlength="10"
                    inputmode="numeric"
                    pattern="[6-9][0-9]{9}"
                    autocomplete="tel"
                />
            </div>
            <div id="lt-mobile-error" class="lt-error" style="display:none;"></div>
            <button id="lt-submit-btn" class="lt-btn-primary">
                <span class="lt-btn-text">{l s='Get Offers' mod='leadtracker'}</span>
                <span class="lt-btn-spinner" style="display:none;">⏳</span>
            </button>
            <button id="lt-skip-btn" class="lt-btn-skip">{l s='Maybe later' mod='leadtracker'}</button>
        </div>
        <div class="lt-modal-footer">
            <small>🔒 {l s='We respect your privacy. No spam, ever.' mod='leadtracker'}</small>
        </div>
    </div>
</div>
