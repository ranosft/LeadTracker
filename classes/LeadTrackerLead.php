<?php
/**
 * LeadTrackerLead — ORM model for ps_leads table
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LeadTrackerLead extends ObjectModel
{
    public $mobile;
    public $mobile_normalized;
    public $source;
    public $session_id;
    public $ip_address;
    public $user_agent;
    public $gdpr_consent;
    public $created_at;
    public $updated_at;

    public static $definition = array(
        'table'   => 'leads',
        'primary' => 'id_lead',
        'fields'  => array(
            'mobile'            => array('type' => self::TYPE_STRING, 'size' => 20,  'required' => true),
            'mobile_normalized' => array('type' => self::TYPE_STRING, 'size' => 10,  'required' => true),
            'source'            => array('type' => self::TYPE_STRING, 'size' => 50),
            'session_id'        => array('type' => self::TYPE_STRING, 'size' => 100),
            'ip_address'        => array('type' => self::TYPE_STRING, 'size' => 45),
            'user_agent'        => array('type' => self::TYPE_HTML,   'size' => 500),
            'gdpr_consent'      => array('type' => self::TYPE_INT),
            'created_at'        => array('type' => self::TYPE_DATE),
            'updated_at'        => array('type' => self::TYPE_DATE),
        ),
    );

    /**
     * Normalize raw mobile to 10-digit Indian format.
     * Accepts: 919876543210 | +919876543210 | 09876543210 | 9876543210
     * Returns false if invalid.
     */
    public static function normalizeMobile($raw)
    {
        if (empty($raw)) {
            return false;
        }
        $digits = preg_replace('/\D/', '', trim((string)$raw));

        // 12 digits starting with 91 → strip country code
        if (strlen($digits) === 12 && substr($digits, 0, 2) === '91') {
            $digits = substr($digits, 2);
        }
        // 11 digits starting with 0 → strip trunk prefix
        if (strlen($digits) === 11 && $digits[0] === '0') {
            $digits = substr($digits, 1);
        }
        // Must be exactly 10 digits starting with 6-9
        if (strlen($digits) === 10 && preg_match('/^[6-9]/', $digits)) {
            return $digits;
        }
        return false;
    }

    /**
     * Find existing lead by normalized mobile or create a new one.
     * Returns the LeadTrackerLead object, or false on error.
     */
    public static function getOrCreate($mobile, $source, $sessionId = null, $ip = null, $ua = null)
    {
        $normalized = self::normalizeMobile($mobile);
        if (!$normalized) {
            return false;
        }

        $db  = Db::getInstance();
        $row = $db->getRow(
            'SELECT `id_lead` FROM `' . _DB_PREFIX_ . 'leads`
             WHERE `mobile_normalized` = \'' . pSQL($normalized) . '\' LIMIT 1'
        );

        if (!empty($row) && !empty($row['id_lead'])) {
            return new LeadTrackerLead((int)$row['id_lead']);
        }

        // Insert directly for reliability across PS versions
        $now = date('Y-m-d H:i:s');
        $ok  = $db->insert('leads', array(
            'mobile'            => pSQL((string)$mobile),
            'mobile_normalized' => pSQL($normalized),
            'source'            => pSQL((string)$source),
            'session_id'        => $sessionId ? pSQL((string)$sessionId) : null,
            'ip_address'        => $ip  ? pSQL((string)$ip)  : null,
            'user_agent'        => $ua  ? pSQL(substr((string)$ua, 0, 500)) : null,
            'gdpr_consent'      => 0,
            'created_at'        => pSQL($now),
            'updated_at'        => pSQL($now),
        ));

        if (!$ok) {
            return false;
        }

        $newId = (int)$db->Insert_ID();
        if (!$newId) {
            return false;
        }

        return new LeadTrackerLead($newId);
    }

    public static function getAll($filters = array(), $orderBy = 'created_at', $orderDir = 'DESC', $limit = 100, $offset = 0)
    {
        $where = '1=1';

        if (!empty($filters['mobile'])) {
            $where .= ' AND `mobile_normalized` LIKE \'%' . pSQL($filters['mobile']) . '%\'';
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND DATE(`created_at`) >= \'' . pSQL($filters['date_from']) . '\'';
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND DATE(`created_at`) <= \'' . pSQL($filters['date_to']) . '\'';
        }

        $orderDir = (strtoupper($orderDir) === 'ASC') ? 'ASC' : 'DESC';
        $allowed  = array('created_at', 'mobile_normalized', 'source');
        $orderBy  = in_array($orderBy, $allowed) ? $orderBy : 'created_at';

        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'leads`
             WHERE ' . $where . '
             ORDER BY `' . $orderBy . '` ' . $orderDir . '
             LIMIT ' . (int)$offset . ', ' . (int)$limit
        );
    }

    public static function countAll($filters = array())
    {
        $where = '1=1';
        if (!empty($filters['mobile'])) {
            $where .= ' AND `mobile_normalized` LIKE \'%' . pSQL($filters['mobile']) . '%\'';
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND DATE(`created_at`) >= \'' . pSQL($filters['date_from']) . '\'';
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND DATE(`created_at`) <= \'' . pSQL($filters['date_to']) . '\'';
        }
        return (int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'leads` WHERE ' . $where
        );
    }
}
