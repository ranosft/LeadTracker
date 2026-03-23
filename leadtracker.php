<?php
/**
 * LeadTracker - PrestaShop Lead Generation & Visitor Tracking Module
 * Version: 1.0.1
 * Compatible: PrestaShop 1.7.x and 8.x
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

// Auto-load module classes
require_once dirname(__FILE__) . '/classes/LeadTrackerLead.php';
require_once dirname(__FILE__) . '/classes/LeadTrackerActivity.php';
require_once dirname(__FILE__) . '/classes/LeadTrackerTelegram.php';

class LeadTracker extends Module
{
    public function __construct()
    {
        $this->name = 'leadtracker';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.1';
        $this->author = 'LeadTracker';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Lead Tracker & Visitor Analytics');
        $this->description = $this->l('Capture leads, track visitor activity, and send real-time Telegram notifications.');
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);
    }

    /* ─────────────────────────────────────────
       INSTALL / UNINSTALL
    ───────────────────────────────────────── */

    public function install()
    {
        if (!parent::install()) {
            $this->_errors[] = $this->l('parent::install() failed');
            return false;
        }
        if (!$this->installSql()) {
            $this->_errors[] = $this->l('SQL install failed');
            return false;
        }
        if (!$this->installTab()) {
            // Non-fatal — tab may already exist or parent not found
            // Don't block install, just log
        }
        if (!$this->registerHooks()) {
            $this->_errors[] = $this->l('Hook registration failed');
            return false;
        }
        $this->setDefaults();
        return true;
    }

    public function uninstall()
    {
        $this->uninstallTab();
        $this->uninstallSql();
        $this->deleteConfig();
        return parent::uninstall();
    }

    private function installSql()
    {
        // Run each CREATE TABLE separately to avoid parser issues
        $db = Db::getInstance();
        $p  = _DB_PREFIX_;

        $sql1 = "CREATE TABLE IF NOT EXISTS `{$p}leads` (
            `id_lead`           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `mobile`            VARCHAR(20)  NOT NULL DEFAULT '',
            `mobile_normalized` VARCHAR(10)  NOT NULL DEFAULT '',
            `source`            VARCHAR(50)  NOT NULL DEFAULT 'unknown',
            `session_id`        VARCHAR(100) DEFAULT NULL,
            `ip_address`        VARCHAR(45)  DEFAULT NULL,
            `user_agent`        TEXT         DEFAULT NULL,
            `gdpr_consent`      TINYINT(1)   NOT NULL DEFAULT 0,
            `created_at`        DATETIME     NOT NULL,
            `updated_at`        DATETIME     NOT NULL,
            PRIMARY KEY (`id_lead`),
            UNIQUE KEY `mobile_normalized` (`mobile_normalized`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $sql2 = "CREATE TABLE IF NOT EXISTS `{$p}lead_activity` (
            `id_activity`  INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_lead`      INT(11) UNSIGNED NOT NULL,
            `event_type`   VARCHAR(50)  NOT NULL DEFAULT '',
            `page_url`     VARCHAR(512) DEFAULT NULL,
            `controller`   VARCHAR(100) DEFAULT NULL,
            `product_id`   INT(11) UNSIGNED DEFAULT NULL,
            `product_name` VARCHAR(255) DEFAULT NULL,
            `cart_total`   DECIMAL(10,2) DEFAULT NULL,
            `session_id`   VARCHAR(100) DEFAULT NULL,
            `ip_address`   VARCHAR(45)  DEFAULT NULL,
            `extra_data`   TEXT         DEFAULT NULL,
            `telegram_sent` TINYINT(1)  NOT NULL DEFAULT 0,
            `created_at`   DATETIME     NOT NULL,
            PRIMARY KEY (`id_activity`),
            KEY `idx_lead`    (`id_lead`),
            KEY `idx_event`   (`event_type`),
            KEY `idx_created` (`created_at`),
            CONSTRAINT `fk_lt_activity_lead`
                FOREIGN KEY (`id_lead`) REFERENCES `{$p}leads` (`id_lead`)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return $db->execute($sql1) && $db->execute($sql2);
    }

    private function uninstallSql()
    {
        $db = Db::getInstance();
        $p  = _DB_PREFIX_;
        // Drop child first to respect FK
        $db->execute("DROP TABLE IF EXISTS `{$p}lead_activity`");
        $db->execute("DROP TABLE IF EXISTS `{$p}leads`");
    }

    private function installTab()
    {
        // Avoid duplicate
        if (Tab::getIdFromClassName('AdminLeadTracker')) {
            return true;
        }

        // Try common parent class names across PS 1.7 and PS 8
        $parentCandidates = array('AdminStats', 'AdminParentStats', 'DEFAULT');
        $idParent = -1; // -1 = hidden in PS8; fallback

        foreach ($parentCandidates as $candidate) {
            $found = (int) Tab::getIdFromClassName($candidate);
            if ($found > 0) {
                $idParent = $found;
                break;
            }
        }

        $tab = new Tab();
        $tab->active     = 1;
        $tab->class_name = 'AdminLeadTracker';
        $tab->module     = $this->name;
        $tab->id_parent  = $idParent;
        $tab->icon       = 'track_changes'; // Material icon for PS8 new theme

        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Lead Tracker';
        }

        return $tab->add();
    }

    private function uninstallTab()
    {
        $id_tab = (int) Tab::getIdFromClassName('AdminLeadTracker');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        return true;
    }

    private function registerHooks()
    {
        $hooks = array(
            'displayHeader',
            'displayFooter',
            'actionFrontControllerSetMedia',
            'actionCartSave',
            'displayOrderConfirmation',
            'additionalCustomerFormFields', // Injects the field
            'actionCustomerAccountAdd' ,     // Saves the data
        );

        foreach ($hooks as $hook) {
            // registerHook returns false if hook doesn't exist — only fail on known hooks
            $this->registerHook($hook);
        }

        return true; // Never block install due to missing hooks
    }

    private function setDefaults()
    {
        $defaults = array(
            'LEADTRACKER_ENABLED'          => 1,
            'LEADTRACKER_TELEGRAM_TOKEN'   => '',
            'LEADTRACKER_TELEGRAM_CHAT_ID' => '',
            'LEADTRACKER_TRACK_PAGEVIEW'   => 1,
            'LEADTRACKER_TRACK_PRODUCT'    => 1,
            'LEADTRACKER_TRACK_CART'       => 1,
            'LEADTRACKER_TRACK_CHECKOUT'   => 1,
            'LEADTRACKER_COOKIE_DAYS'      => 30,
            'LEADTRACKER_SHOW_POPUP'       => 1,
            'LEADTRACKER_GDPR_MODE'        => 0,
        );
        foreach ($defaults as $key => $val) {
            Configuration::updateValue($key, $val);
        }
    }

    private function deleteConfig()
    {
        $keys = array(
            'LEADTRACKER_ENABLED', 'LEADTRACKER_TELEGRAM_TOKEN', 'LEADTRACKER_TELEGRAM_CHAT_ID',
            'LEADTRACKER_TRACK_PAGEVIEW', 'LEADTRACKER_TRACK_PRODUCT', 'LEADTRACKER_TRACK_CART',
            'LEADTRACKER_TRACK_CHECKOUT', 'LEADTRACKER_COOKIE_DAYS', 'LEADTRACKER_SHOW_POPUP',
            'LEADTRACKER_GDPR_MODE',
        );
        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }
    }

    /* ─────────────────────────────────────────
       CONFIGURATION PAGE
    ───────────────────────────────────────── */

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitLeadTrackerConfig')) {
            $output .= $this->postProcess();
        }

        return $output . $this->renderConfigForm();
    }

    private function postProcess()
    {
        $fields = array(
            'LEADTRACKER_ENABLED'          => 'bool',
            'LEADTRACKER_TELEGRAM_TOKEN'   => 'string',
            'LEADTRACKER_TELEGRAM_CHAT_ID' => 'string',
            'LEADTRACKER_TRACK_PAGEVIEW'   => 'bool',
            'LEADTRACKER_TRACK_PRODUCT'    => 'bool',
            'LEADTRACKER_TRACK_CART'       => 'bool',
            'LEADTRACKER_TRACK_CHECKOUT'   => 'bool',
            'LEADTRACKER_COOKIE_DAYS'      => 'int',
            'LEADTRACKER_SHOW_POPUP'       => 'bool',
            'LEADTRACKER_GDPR_MODE'        => 'bool',
        );

        foreach ($fields as $key => $type) {
            $val = Tools::getValue($key);
            if ($type === 'bool') {
                $val = (int)(bool)$val;
            } elseif ($type === 'int') {
                $val = max(1, (int)$val);
            } else {
                $val = trim(Tools::getValue($key, ''));
            }
            Configuration::updateValue($key, $val);
        }

        return $this->displayConfirmation($this->l('Settings saved successfully.'));
    }

    private function renderConfigForm()
    {
        // Build switch inputs with UNIQUE IDs per field (required by PS HelperForm)
        $inputs = array(
            $this->makeSwitchInput('LEADTRACKER_ENABLED',        $this->l('Enable Tracking')),
            array(
                'type'  => 'text',
                'label' => $this->l('Telegram Bot Token'),
                'name'  => 'LEADTRACKER_TELEGRAM_TOKEN',
                'size'  => 60,
                'desc'  => $this->l('Obtain from @BotFather on Telegram'),
            ),
            array(
                'type'  => 'text',
                'label' => $this->l('Telegram Chat ID'),
                'name'  => 'LEADTRACKER_TELEGRAM_CHAT_ID',
                'size'  => 30,
                'desc'  => $this->l('Use negative value for groups, e.g. -1001234567890'),
            ),
            $this->makeSwitchInput('LEADTRACKER_TRACK_PAGEVIEW',  $this->l('Track Page Views')),
            $this->makeSwitchInput('LEADTRACKER_TRACK_PRODUCT',   $this->l('Track Product Views')),
            $this->makeSwitchInput('LEADTRACKER_TRACK_CART',      $this->l('Track Add to Cart')),
            $this->makeSwitchInput('LEADTRACKER_TRACK_CHECKOUT',  $this->l('Track Checkout')),
            array(
                'type'  => 'text',
                'label' => $this->l('Cookie Duration (days)'),
                'name'  => 'LEADTRACKER_COOKIE_DAYS',
                'size'  => 5,
                'desc'  => $this->l('How long to remember a visitor\'s mobile number'),
            ),
            $this->makeSwitchInput('LEADTRACKER_SHOW_POPUP',      $this->l('Show Mobile Capture Popup')),
            $this->makeSwitchInput('LEADTRACKER_GDPR_MODE',       $this->l('GDPR Mode (require consent)')),
        );

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Lead Tracker Settings'),
                    'icon'  => 'icon-cog',
                ),
                'input'  => $inputs,
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar        = false;
        $helper->table               = $this->table;
        $helper->module              = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->identifier          = $this->identifier;
        $helper->submit_action       = 'submitLeadTrackerConfig';
        $helper->currentIndex        = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
        $helper->token               = Tools::getAdminTokenLite('AdminModules');
        $helper->fields_value        = $this->getConfigValues();

        return $helper->generateForm(array($fields_form));
    }

    /**
     * Build a PS-compatible switch input with unique radio IDs per field name.
     * PS HelperForm uses name+'_on' / name+'_off' as HTML radio ids — they must be unique.
     */
    private function makeSwitchInput($name, $label)
    {
        // Convert LEADTRACKER_FOO_BAR -> lt_foo_bar for short unique id prefix
        $idPrefix = strtolower(str_replace('LEADTRACKER_', 'lt_', $name));

        return array(
            'type'    => 'switch',
            'label'   => $label,
            'name'    => $name,
            'is_bool' => true,
            'values'  => array(
                array(
                    'id'    => $idPrefix . '_on',
                    'value' => 1,
                    'label' => $this->l('Yes'),
                ),
                array(
                    'id'    => $idPrefix . '_off',
                    'value' => 0,
                    'label' => $this->l('No'),
                ),
            ),
        );
    }

    private function getConfigValues()
    {
        return array(
            'LEADTRACKER_ENABLED'          => (int) Configuration::get('LEADTRACKER_ENABLED'),
            'LEADTRACKER_TELEGRAM_TOKEN'   => Configuration::get('LEADTRACKER_TELEGRAM_TOKEN'),
            'LEADTRACKER_TELEGRAM_CHAT_ID' => Configuration::get('LEADTRACKER_TELEGRAM_CHAT_ID'),
            'LEADTRACKER_TRACK_PAGEVIEW'   => (int) Configuration::get('LEADTRACKER_TRACK_PAGEVIEW'),
            'LEADTRACKER_TRACK_PRODUCT'    => (int) Configuration::get('LEADTRACKER_TRACK_PRODUCT'),
            'LEADTRACKER_TRACK_CART'       => (int) Configuration::get('LEADTRACKER_TRACK_CART'),
            'LEADTRACKER_TRACK_CHECKOUT'   => (int) Configuration::get('LEADTRACKER_TRACK_CHECKOUT'),
            'LEADTRACKER_COOKIE_DAYS'      => (int) Configuration::get('LEADTRACKER_COOKIE_DAYS'),
            'LEADTRACKER_SHOW_POPUP'       => (int) Configuration::get('LEADTRACKER_SHOW_POPUP'),
            'LEADTRACKER_GDPR_MODE'        => (int) Configuration::get('LEADTRACKER_GDPR_MODE'),
        );
    }

    /* ─────────────────────────────────────────
       HOOKS
    ───────────────────────────────────────── */
   public function hookAdditionalCustomerFormFields($params)
{
    $label = $this->l('Mobile Number');
    
    $formField = (new FormField)
        ->setName('mobile_lead')
        ->setType('text')
        ->setLabel($label)
        ->setRequired(true); // Makes it mandatory for guests

    return array($formField);
}

public function hookActionCustomerAccountAdd($params)
{
    $mobile = Tools::getValue('mobile_lead');
    $customer = $params['new_customer'];

    if ($mobile && $customer->id) {
        // Save to your module's table to keep core tables clean
        $db = Db::getInstance();
        $db->insert('leads', array(
            'mobile'            => pSQL($mobile),
            'mobile_normalized' => pSQL(preg_replace('/[^0-9]/', '', $mobile)),
            'source'            => 'checkout_registration',
            'session_id'        => session_id(),
            'ip_address'        => Tools::getRemoteAddr(),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ));
        
        // Also send your Telegram notification here if enabled
         $this->sendTelegramNotification($mobile, $customer->email);
    }
}
    public function hookDisplayHeader($params)
    {
        if (!Configuration::get('LEADTRACKER_ENABLED')) {
            return '';
        }

        $customer       = $this->context->customer;
        $customerMobile = '';

        if (isset($customer) && $customer->isLogged()) {
            $customerMobile = !empty($customer->phone_mobile)
                ? $customer->phone_mobile
                : (string) $customer->phone;
        }

        // Build AJAX url — use https if available
        $ajaxUrl = $this->context->link->getModuleLink('leadtracker', 'track', array(), true);

        $pageUrl    = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $controller = '';
        if (isset($this->context->controller) && isset($this->context->controller->php_self)) {
            $controller = $this->context->controller->php_self;
        }

        $this->context->smarty->assign(array(
            'lt_ajax_url'        => $ajaxUrl,
            'lt_customer_mobile' => $customerMobile,
            'lt_cookie_days'     => (int) Configuration::get('LEADTRACKER_COOKIE_DAYS'),
            'lt_show_popup'      => (int) Configuration::get('LEADTRACKER_SHOW_POPUP'),
            'lt_gdpr_mode'       => (int) Configuration::get('LEADTRACKER_GDPR_MODE'),
            'lt_track_pageview'  => (int) Configuration::get('LEADTRACKER_TRACK_PAGEVIEW'),
            'lt_track_product'   => (int) Configuration::get('LEADTRACKER_TRACK_PRODUCT'),
            'lt_track_cart'      => (int) Configuration::get('LEADTRACKER_TRACK_CART'),
            'lt_track_checkout'  => (int) Configuration::get('LEADTRACKER_TRACK_CHECKOUT'),
            'lt_session_id'      => session_id(),
            'lt_page_url'        => $pageUrl,
            'lt_controller'      => $controller,
        ));

        return $this->fetch('module:leadtracker/views/templates/front/leadtracker/header.tpl');
    }

    public function hookDisplayFooter($params)
    {
        if (!Configuration::get('LEADTRACKER_ENABLED')) {
            return '';
        }
        return $this->fetch('module:leadtracker/views/templates/front/leadtracker/footer.tpl');
    }

    public function hookActionFrontControllerSetMedia($params)
    {
        if (!Configuration::get('LEADTRACKER_ENABLED')) {
            return;
        }
        $this->context->controller->addJS($this->_path . 'views/js/leadtracker.js');
        $this->context->controller->addCSS($this->_path . 'views/css/leadtracker.css');
    }

    public function hookActionCartSave($params)
    {
        // JS handles AJAX cart tracking; this hook is a server-side placeholder
        return;
    }

    public function hookDisplayOrderConfirmation($params)
    {
        // Could be used for server-side order confirmation tracking
        return;
    }
}
