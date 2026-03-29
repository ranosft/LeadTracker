<?php
/**
 * Telegram notification service
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LeadTrackerTelegram
{
    private $token;
    private $chatId;
    private $apiBase = 'https://api.telegram.org/bot';

    public function __construct()
    {
        $this->token  = Configuration::get('LEADTRACKER_TELEGRAM_TOKEN');
        $this->chatId = Configuration::get('LEADTRACKER_TELEGRAM_CHAT_ID');
    }

    public function isConfigured()
    {
        return !empty($this->token) && !empty($this->chatId);
    }

    public function sendNewLead($mobile, $source)
    {
        // Using HTML <b> for bold and <code> for monospace
        $message = "🎯 <b>New Lead Captured</b>\n"
            . "📱 Mobile: <code>" . htmlspecialchars($mobile) . "</code>\n"
            . "🔍 Source: " . htmlspecialchars($source) . "\n"
            . "🕐 Time: " . date('d M Y H:i:s');

        return $this->send($message);
    }

    public function sendActivity($mobile, $eventType, $data = [])
    {
        $icons = [
            'pageview'      => '👁',
            'product_view'  => '🛍',
            'add_to_cart'   => '🛒',
            'checkout'      => '💳',
            'order'         => '✅',
        ];

        $icon  = $icons[$eventType] ?? '📌';
        $event = ucwords(str_replace('_', ' ', $eventType));

        $message = $icon . " <b>" . $event . "</b>\n"
            . "📱 Mobile: <code>" . htmlspecialchars($mobile) . "</code>\n";

        // htmlspecialchars() prevents random characters like < or & from breaking the HTML parser
        if (!empty($data['product_name'])) {
            $message .= "📦 Product: " . htmlspecialchars($data['product_name']) . "\n";
        }
        if (!empty($data['page_url'])) {
            $message .= "🔗 Page: " . htmlspecialchars($data['page_url']) . "\n";
        }
        if (!empty($data['cart_total'])) {
            $message .= "💰 Cart Total: ₹" . number_format($data['cart_total'], 2) . "\n";
        }
        $message .= "🕐 " . date('d M Y H:i:s');

        return $this->send($message);
    }

    private function send($text)
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $url = $this->apiBase . $this->token . '/sendMessage';
        $payload = json_encode([
            'chat_id'    => $this->chatId,
            'text'       => $text,
            'parse_mode' => 'HTML', // Switched from Markdown to HTML
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3, // Lowered to 3s to protect storefront performance
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Optional: If you want to log errors to PrestaShop's log when it fails, uncomment this:
        // if ($httpCode !== 200) {
        //     PrestaShopLogger::addLog('LeadTracker Telegram Error: ' . $result, 3);
        // }

        return $httpCode === 200;
    }
}