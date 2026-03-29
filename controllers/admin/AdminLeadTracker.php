<?php
/**
 * AdminLeadTracker — Back office controller
 * Leads list, activity timeline, filters, CSV export
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

// Load classes — path resolution from admin controller location
$modulePath = dirname(__FILE__) . '/../../';
require_once $modulePath . 'classes/LeadTrackerLead.php';
require_once $modulePath . 'classes/LeadTrackerActivity.php';

class AdminLeadTrackerController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap  = true;
        $this->table      = 'leads';
        $this->className  = 'LeadTrackerLead';
        $this->identifier = 'id_lead';
        $this->lang       = false;
        $this->deleted    = false;

        // Disable built-in list — we render our own template
        $this->list_no_link = true;
        
        parent::__construct();

        $this->meta_title = $this->module->l('Lead Tracker');
    }

    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_btn['export_csv'] = array(
            'href'  => self::$currentIndex . '&action=exportCsv&token=' . $this->token,
            'desc'  => $this->module->l('Export CSV'),
            'icon'  => 'process-icon-export',
        );

        if (Tools::getValue('view_lead')) {
            $this->page_header_toolbar_btn['back'] = array(
                'href'  => self::$currentIndex . '&token=' . $this->token,
                'desc'  => $this->module->l('Back to Leads'),
                'icon'  => 'process-icon-back',
            );
        }

        parent::initPageHeaderToolbar();
    }

    public function initProcess()
    {
        parent::initProcess();

        if (Tools::getValue('action') === 'exportCsv') {
            $this->exportCsv();
        }
    }

    public function initContent()
    {
        parent::initContent();

        if (Tools::getValue('view_lead')) {
            $this->context->smarty->assign($this->getActivityData((int) Tools::getValue('view_lead')));
            $tpl = $this->context->smarty->createTemplate(
                _PS_MODULE_DIR_ . 'leadtracker/views/templates/admin/activity.tpl',
                $this->context->smarty
            );
            $this->content = $tpl->fetch();
        } else {
            $data = $this->getLeadsData();
            $this->context->smarty->assign($data);
            $stats = $data['stats']; 

            $stats_items = [
                ['label' => 'Total Leads',   'value' => $stats['total_leads'],    'color' => '#1a237e'],
                ['label' => 'Today',          'value' => $stats['today_leads'],    'color' => '#00695c'],
                ['label' => 'Cart Events',   'value' => $stats['total_carts'],    'color' => '#e65100'],
                ['label' => 'Checkouts',      'value' => $stats['total_checkouts'], 'color' => '#6a1b9a'],
            ];
            $this->context->smarty->assign('stats_items', $stats_items);

            $tpl = $this->context->smarty->createTemplate(
                _PS_MODULE_DIR_ . 'leadtracker/views/templates/admin/leads.tpl',
                $this->context->smarty
            );
            $this->content = $tpl->fetch();
        }

        $this->context->smarty->assign('content', $this->content);
    }

    private function getLeadsData()
    {
        $filters = array(
            'mobile'    => Tools::getValue('filter_mobile', ''),
            'date_from' => Tools::getValue('filter_date_from', ''),
            'date_to'   => Tools::getValue('filter_date_to', ''),
        );

        $page   = max(1, (int) Tools::getValue('p', 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;

        $leads = LeadTrackerLead::getAll($filters, 'created_at', 'DESC', $limit, $offset);
        $total = LeadTrackerLead::countAll($filters);

        // Enrich with activity count and view URL
        foreach ($leads as &$lead) {
            $lead['activity_count'] = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'lead_activity`
                 WHERE `id_lead` = ' . (int)$lead['id_lead']
            );
            $lead['view_url'] = self::$currentIndex
                . '&view_lead=' . (int)$lead['id_lead']
                . '&token=' . $this->token;
        }
        unset($lead);

        return array(
            'leads'       => $leads,
            'total_leads' => $total,
            'page'        => $page,
            'total_pages' => $total > 0 ? (int) ceil($total / $limit) : 1,
            'filters'     => $filters,
            'current_url' => self::$currentIndex . '&token=' . $this->token,
            'stats'       => $this->getStats(),
        );
    }

    private function getActivityData($leadId)
    {
        $lead = new LeadTrackerLead($leadId);

        if (!Validate::isLoadedObject($lead)) {
            return array(
                'lead'       => null,
                'activities' => array(),
                'back_url'   => self::$currentIndex . '&token=' . $this->token,
                'error'      => $this->module->l('Lead not found'),
            );
        }

        return array(
            'lead'       => $lead,
            'activities' => LeadTrackerActivity::getByLead($leadId),
            'back_url'   => self::$currentIndex . '&token=' . $this->token,
            'error'      => null,
        );
    }

    private function getStats()
    {
        $db = Db::getInstance();
        $p  = _DB_PREFIX_;
        return array(
            'total_leads'     => (int) $db->getValue("SELECT COUNT(*) FROM `{$p}leads`"),
            'today_leads'     => (int) $db->getValue("SELECT COUNT(*) FROM `{$p}leads` WHERE DATE(`created_at`) = CURDATE()"),
            'total_carts'     => (int) $db->getValue("SELECT COUNT(*) FROM `{$p}lead_activity` WHERE `event_type` = 'add_to_cart'"),
            'total_checkouts' => (int) $db->getValue("SELECT COUNT(*) FROM `{$p}lead_activity` WHERE `event_type` = 'checkout'"),
        );
    }

    private function exportCsv()
    {
        $leads = LeadTrackerLead::getAll(array(), 'created_at', 'DESC', 10000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $fp = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($fp, array('ID', 'Mobile (10-digit)', 'Source', 'IP Address', 'Captured At'));

        foreach ($leads as $lead) {
            fputcsv($fp, array(
                $lead['id_lead'],
                $lead['mobile_normalized'],
                $lead['source'],
                $lead['ip_address'],
                $lead['created_at'],
            ));
        }
        fclose($fp);
        exit;
    }
}
