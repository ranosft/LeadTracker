<?php
/**
 * LeadTrackerActivity — ORM model for ps_lead_activity table
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LeadTrackerActivity extends ObjectModel
{
    public $id_lead;
    public $event_type;
    public $page_url;
    public $controller;
    public $product_id;
    public $product_name;
    public $cart_total;
    public $session_id;
    public $ip_address;
    public $extra_data;
    public $telegram_sent;
    public $created_at;

    public static $definition = array(
        'table'   => 'lead_activity',
        'primary' => 'id_activity',
        'fields'  => array(
            'id_lead'       => array('type' => self::TYPE_INT,    'required' => true),
            'event_type'    => array('type' => self::TYPE_STRING, 'size' => 50, 'required' => true),
            'page_url'      => array('type' => self::TYPE_STRING, 'size' => 512),
            'controller'    => array('type' => self::TYPE_STRING, 'size' => 100),
            'product_id'    => array('type' => self::TYPE_INT),
            'product_name'  => array('type' => self::TYPE_STRING, 'size' => 255),
            'cart_total'    => array('type' => self::TYPE_FLOAT),
            'session_id'    => array('type' => self::TYPE_STRING, 'size' => 100),
            'ip_address'    => array('type' => self::TYPE_STRING, 'size' => 45),
            'extra_data'    => array('type' => self::TYPE_HTML),
            'telegram_sent' => array('type' => self::TYPE_INT),
            'created_at'    => array('type' => self::TYPE_DATE),
        ),
    );

    /**
     * Log an activity event using direct DB insert for reliability.
     */
    public static function log($leadId, $eventType, $data = array())
    {
        $row = array(
            'id_lead'      => (int)$leadId,
            'event_type'   => pSQL((string)$eventType),
            'page_url'     => isset($data['page_url'])     ? pSQL(substr((string)$data['page_url'], 0, 512))     : null,
            'controller'   => isset($data['controller'])   ? pSQL(substr((string)$data['controller'], 0, 100))   : null,
            'product_id'   => isset($data['product_id'])   ? (int)$data['product_id']                            : null,
            'product_name' => isset($data['product_name']) ? pSQL(substr((string)$data['product_name'], 0, 255)) : null,
            'cart_total'   => isset($data['cart_total'])   ? (float)$data['cart_total']                          : null,
            'session_id'   => isset($data['session_id'])   ? pSQL((string)$data['session_id'])                   : null,
            'ip_address'   => isset($data['ip_address'])   ? pSQL((string)$data['ip_address'])                   : null,
            'extra_data'   => isset($data['extra'])        ? pSQL(json_encode($data['extra']))                   : null,
            'telegram_sent'=> 0,
            'created_at'   => date('Y-m-d H:i:s'),
        );

        // Remove NULL values so DB uses column defaults
        foreach ($row as $k => $v) {
            if ($v === null) {
                unset($row[$k]);
            }
        }

        return Db::getInstance()->insert('lead_activity', $row);
    }

    public static function getByLead($leadId, $filters = array(), $limit = 200)
    {
        $where = '`id_lead` = ' . (int)$leadId;

        if (!empty($filters['event_type'])) {
            $where .= ' AND `event_type` = \'' . pSQL($filters['event_type']) . '\'';
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND DATE(`created_at`) >= \'' . pSQL($filters['date_from']) . '\'';
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND DATE(`created_at`) <= \'' . pSQL($filters['date_to']) . '\'';
        }

        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'lead_activity`
             WHERE ' . $where . '
             ORDER BY `created_at` DESC
             LIMIT ' . (int)$limit
        );
    }

    public static function getAll($filters = array(), $limit = 500, $offset = 0)
    {
        $where = '1=1';

        if (!empty($filters['event_type'])) {
            $where .= ' AND a.`event_type` = \'' . pSQL($filters['event_type']) . '\'';
        }
        if (!empty($filters['mobile'])) {
            $where .= ' AND l.`mobile_normalized` LIKE \'%' . pSQL($filters['mobile']) . '%\'';
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND DATE(a.`created_at`) >= \'' . pSQL($filters['date_from']) . '\'';
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND DATE(a.`created_at`) <= \'' . pSQL($filters['date_to']) . '\'';
        }

        return Db::getInstance()->executeS(
            'SELECT a.*, l.`mobile_normalized`, l.`source`
             FROM `' . _DB_PREFIX_ . 'lead_activity` a
             LEFT JOIN `' . _DB_PREFIX_ . 'leads` l ON a.`id_lead` = l.`id_lead`
             WHERE ' . $where . '
             ORDER BY a.`created_at` DESC
             LIMIT ' . (int)$offset . ', ' . (int)$limit
        );
    }
}
