<?php
namespace App\Services;

use App\Repositories\SettingsRepository;
use Monolog\Logger;

class GoldPriceService {
    private SettingsRepository $settingsRepository;
    private Logger $logger;

    public function __construct(SettingsRepository $settingsRepository, Logger $logger) {
        $this->settingsRepository = $settingsRepository;
        $this->logger = $logger;
    }

    /**
     * دریافت قیمت فروش یک گرم طلای ۱۸ عیار از API
     * @return float|null
     */
    public function fetchLatestGoldPrice(): ?float {
        $url = $this->settingsRepository->get('gold_price_api_url') ?? '';
        $params = $this->settingsRepository->get('gold_price_api_params') ?? '';
        $apiKey = $this->settingsRepository->get('gold_price_api_key') ?? '';
        $username = $this->settingsRepository->get('gold_price_api_username') ?? '';
        $password = $this->settingsRepository->get('gold_price_api_password') ?? '';

        if (!$url) {
            $this->logger->warning("GoldPriceService: API URL is empty.");
            return null;
        }
        if ($params) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . $params;
        }

        $headers = [];
        if ($apiKey) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        if ($username && $password) {
            $headers[] = 'Authorization: Basic ' . base64_encode($username . ':' . $password);
        }

        $this->logger->info("GoldPriceService: Sending request to API.", ['url' => $url, 'headers' => $headers]);
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers)
            ]
        ];
        $context = stream_context_create($opts);
        $json = @file_get_contents($url, false, $context);
        if (!$json) {
            $this->logger->error("GoldPriceService: No response from API.", ['url' => $url]);
            return null;
        }
        $this->logger->info("GoldPriceService: API response received.", ['response' => mb_substr($json,0,500)]);
        $data = json_decode($json, true);
        if (is_array($data) && isset($data['gold'])) {
            foreach ($data['gold'] as $item) {
                if (isset($item['symbol']) && $item['symbol'] === 'IR_GOLD_18K' && isset($item['price'])) {
                    $price = (float)$item['price'];
                    $unit = isset($item['unit']) ? trim($item['unit']) : '';
                    if ($unit === 'تومان') {
                        $price = $price * 10;
                        $this->logger->info("GoldPriceService: Converted price from toman to rial.", ['rial_price' => $price]);
                    }
                    $this->logger->info("GoldPriceService: Found IR_GOLD_18K price.", ['price' => $price]);
                    return $price;
                }
            }
            $this->logger->warning("GoldPriceService: IR_GOLD_18K not found in gold array.");
        }
        if (is_array($data)) {
            foreach ($data as $item) {
                if (isset($item['slug']) && $item['slug'] === '18ayar' && isset($item['sell'])) {
                    $this->logger->info("GoldPriceService: Found 18ayar price.", ['price' => $item['sell']]);
                    return (float)$item['sell'];
                }
            }
        }
        $this->logger->warning("GoldPriceService: No valid price found in API response.");
        return null;
    }

    /**
     * دریافت قیمت آخرین سکه‌ها از API (قیمت‌ها به ریال)
     * @return array ['coin_emami'=>int, 'coin_bahar_azadi_new'=>int, ...]
     */
    public function fetchLatestCoinPrices(): array {
        $url = $this->settingsRepository->get('gold_price_api_url') ?? '';
        $params = $this->settingsRepository->get('gold_price_api_params') ?? '';
        $apiKey = $this->settingsRepository->get('gold_price_api_key') ?? '';
        $username = $this->settingsRepository->get('gold_price_api_username') ?? '';
        $password = $this->settingsRepository->get('gold_price_api_password') ?? '';

        if (!$url) {
            $this->logger->warning("GoldPriceService: API URL is empty (coin prices).");
            return [];
        }
        if ($params) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . $params;
        }
        $headers = [];
        if ($apiKey) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        if ($username && $password) {
            $headers[] = 'Authorization: Basic ' . base64_encode($username . ':' . $password);
        }
        $this->logger->info("GoldPriceService: Sending request to API for coin prices.", ['url' => $url, 'headers' => $headers]);
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers)
            ]
        ];
        $context = stream_context_create($opts);
        $json = @file_get_contents($url, false, $context);
        if (!$json) {
            $this->logger->error("GoldPriceService: No response from API (coin prices).", ['url' => $url]);
            return [];
        }
        $this->logger->info("GoldPriceService: API response received (coin prices).", ['response' => mb_substr($json,0,500)]);
        $data = json_decode($json, true);
        $result = [];
        if (is_array($data) && isset($data['gold'])) {
            foreach ($data['gold'] as $item) {
                if (!isset($item['symbol']) || !isset($item['price'])) continue;
                $price = (float)$item['price'];
                $unit = isset($item['unit']) ? trim($item['unit']) : '';
                if ($unit === 'تومان') $price = $price * 10;
                switch ($item['symbol']) {
                    case 'IR_COIN_EMAMI':
                        $result['coin_emami'] = $price; break;
                    case 'IR_COIN_BAHAR':
                        $result['coin_bahar_azadi_new'] = $price; break;
                    case 'IR_COIN_HALF':
                        $result['coin_half'] = $price; break;
                    case 'IR_COIN_QUARTER':
                        $result['coin_quarter'] = $price; break;
                    case 'IR_COIN_1G':
                        $result['coin_gerami'] = $price; break;
                }
            }
        }
        $this->logger->info("GoldPriceService: Coin prices extracted.", $result);
        return $result;
    }

    /**
     * متد مربوط به دریافت آخرین قیمت طلای 750 (از قیمت 18 عیار)
     * 
     * @return float|null قیمت طلای 750 برحسب ریال یا null در صورت عدم موفقیت
     */
    public function fetchLatestPricePer750Gram(): ?float 
    {
        // از قیمت 18 عیار استفاده می‌کنیم و با نسبت 750/750 تبدیل می‌کنیم (عملاً نیازی به تبدیل نیست)
        $price18K = $this->fetchLatestGoldPrice();
        
        if ($price18K === null || $price18K <= 0) {
            $this->logger->warning("GoldPriceService: Failed to get 750 price - no valid 18K price available.");
            return null;
        }
        
        // قیمت 18 عیار همان 750 است، فقط متد جداگانه برای استفاده در کلاس های دیگر ساخته شده
        return $price18K;
    }
} 