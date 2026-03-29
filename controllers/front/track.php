<?php
/**
 * LeadTracker Front AJAX Controller
 * Compatible with PrestaShop 1.7.x and 8.x
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/LeadTrackerLead.php';
require_once dirname(__FILE__) . '/../../classes/LeadTrackerActivity.php';
require_once dirname(__FILE__) . '/../../classes/LeadTrackerTelegram.php';

class LeadTrackerTrackModuleFrontController extends ModuleFrontController
{
    public $ajax = true;
    public $display_column_left  = false;
    public $display_column_right = false;

    public function initContent()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Configuration::get('LEADTRACKER_ENABLED')) {
            $this->jsonExit(array('success' => false, 'error' => 'Module disabled'));
        }

        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonExit(array('success' => false, 'error' => 'POST required'));
        }

        $action = Tools::getValue('action', '');

        switch ($action) {
            case 'capture':
                $this->actionCapture();
                break;
            case 'track':
                $this->actionTrack();
                break;
            default:
                $this->jsonExit(array('success' => false, 'error' => 'Unknown action'));
        }
    }

    private function actionCapture()
    {
        $mobile = trim((string) Tools::getValue('mobile', ''));
        $source = $this->sanitizeSource(Tools::getValue('source', 'manual'));

        if ($mobile === '') {
            $this->jsonExit(array('success' => false, 'error' => 'Mobile required'));
        }

        $normalized = LeadTrackerLead::normalizeMobile($mobile);
        if (!$normalized) {
            $this->jsonExit(array('success' => false, 'error' => 'Invalid mobile number'));
        }

        $ip        = $this->getClientIp();
        $ua        = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';
        $sessionId = session_id();

        
        $existing = Db::getInstance()->getRow(
            "SELECT `id_lead` FROM `" . _DB_PREFIX_ . "leads` WHERE `mobile_normalized` = '" . pSQL($normalized) . "'"
        );
        $isNew = empty($existing);

        $lead = LeadTrackerLead::getOrCreate($mobile, $source, $sessionId, $ip, $ua);

        if (!$lead || !$lead->id) {
            $this->jsonExit(array('success' => false, 'error' => 'Could not create lead'));
        }

        if ($isNew) {
            $tg = new LeadTrackerTelegram();
            if ($tg->isConfigured()) {
                $tg->sendNewLead($normalized, $source);
            }
        }

        $this->jsonExit(array(
            'success'    => true,
            'normalized' => $normalized,
            'lead_id'    => (int) $lead->id,
            'is_new'     => $isNew,
        ));
    }

    private function actionTrack()
    {
        $mobile    = trim((string) Tools::getValue('mobile', ''));
        $source    = $this->sanitizeSource(Tools::getValue('source', 'cookie'));
        $eventType = $this->sanitizeEvent(Tools::getValue('event_type', ''));

        if ($mobile === '' || !$eventType) {
            $this->jsonExit(array('success' => false, 'error' => 'Missing required fields'));
        }

        $configMap = array(
            'pageview'     => 'LEADTRACKER_TRACK_PAGEVIEW',
            'product_view' => 'LEADTRACKER_TRACK_PRODUCT',
            'add_to_cart'  => 'LEADTRACKER_TRACK_CART',
            'checkout'     => 'LEADTRACKER_TRACK_CHECKOUT',
        );
        if (isset($configMap[$eventType]) && !Configuration::get($configMap[$eventType])) {
            $this->jsonExit(array('success' => false, 'error' => 'Event type disabled'));
        }

        $normalized = LeadTrackerLead::normalizeMobile($mobile);
        if (!$normalized) {
            $this->jsonExit(array('success' => false, 'error' => 'Invalid mobile'));
        }

        $ip        = $this->getClientIp();
        $sessionId = session_id();

        $lead = LeadTrackerLead::getOrCreate($mobile, $source, $sessionId, $ip, '');
        if (!$lead || !$lead->id) {
            $this->jsonExit(array('success' => false, 'error' => 'Lead error'));
        }

        $pageUrl     = pSQL(substr((string) Tools::getValue('page_url', ''), 0, 512));
        $controller  = pSQL(substr((string) Tools::getValue('controller', ''), 0, 100));
        $productId   = (int) Tools::getValue('product_id', 0) ?: null;
        $productName = pSQL(substr((string) Tools::getValue('product_name', ''), 0, 255));
        $cartTotal   = (float) Tools::getValue('cart_total', 0) ?: null;

        $data = array(
            'page_url'     => $pageUrl,
            'controller'   => $controller,
            'product_id'   => $productId,
            'product_name' => $productName,
            'cart_total'   => $cartTotal,
            'session_id'   => $sessionId,
            'ip_address'   => $ip,
        );

        LeadTrackerActivity::log((int) $lead->id, $eventType, $data);

        $notifyEvents = array('add_to_cart', 'checkout', 'order');
        if (in_array($eventType, $notifyEvents)) {
            $tg = new LeadTrackerTelegram();
            if ($tg->isConfigured()) {
                $tg->sendActivity($normalized, $eventType, array(
                    'product_name' => $productName,
                    'page_url'     => $pageUrl,
                    'cart_total'   => $cartTotal,
                ));
            }
        }

        $this->jsonExit(array('success' => true));
    }

    private function jsonExit($data)
    {
        echo json_encode($data);
        exit;
    }

    private function sanitizeSource($source)
    {
        $allowed = array('url_param', 'customer', 'cookie', 'manual');
        return in_array((string)$source, $allowed) ? (string)$source : 'manual';
    }

    private function sanitizeEvent($event)
    {
        $allowed = array('pageview', 'product_view', 'add_to_cart', 'checkout', 'order');
        return in_array((string)$event, $allowed) ? (string)$event : '';
    }

    private function getClientIp()
    {
        foreach (array('HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR') as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
