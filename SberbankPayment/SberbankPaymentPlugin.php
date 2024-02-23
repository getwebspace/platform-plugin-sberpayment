<?php declare(strict_types=1);

namespace Plugin\SberbankPayment;

use App\Domain\Entities\Catalog\Order;
use App\Domain\Plugin\AbstractPaymentPlugin;
use Psr\Container\ContainerInterface;

class SberbankPaymentPlugin extends AbstractPaymentPlugin
{
    const AUTHOR = 'Aleksey Ilyin';
    const AUTHOR_SITE = 'https://getwebspace.org';
    const NAME = 'SberbankPaymentPlugin';
    const TITLE = 'SberbankPayment';
    const DESCRIPTION = 'Возможность принимать безналичную оплату товаров и услуг';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->addSettingsField([
            'label' => 'Логин',
            'type' => 'text',
            'name' => 'login',
        ]);

        $this->addSettingsField([
            'label' => 'Пароль',
            'type' => 'text',
            'name' => 'password',
        ]);

        $this->addSettingsField([
            'label' => 'Description',
            'description' => 'В указанной строке <code>{serial}</code> заменится на номер заказа',
            'type' => 'text',
            'name' => 'description',
            'args' => [
                'value' => 'Оплата заказа #{serial}',
            ],
        ]);

        // результат оплаты
        $this
            ->map([
                'methods' => ['get', 'post'],
                'pattern' => '/cart/done/sp/result',
                'handler' => \Plugin\SberbankPayment\Actions\ResultAction::class,
            ])
            ->setName('common:sp:success');
    }

    public function getRedirectURL(Order $order): ?string
    {
        $this->logger->debug('SberbankPayment: register order', ['serial' => $order->getSerial()]);

        // регистрация заказа
        $result = $this->request('payment/rest/register.do', [
            'userName' => $this->parameter('SberbankPaymentPlugin_login'),
            'password' => $this->parameter('SberbankPaymentPlugin_password'),
            'orderNumber' => $order->getSerial(),
            'amount' => intval($order->getTotalPrice() * 100), // копейки
            'description' => str_replace('{serial}', $order->getSerial(), $this->parameter('SberbankPaymentPlugin_description', '')),
            'language' => 'ru',
            'returnUrl' => $this->parameter('common_homepage') . 'cart/done/sp/result?serial=' . $order->getSerial(),
        ]);

        if ($result) {
            return $result['formUrl'];
        }

        return null;
    }

    public function request(string $method, array $data): mixed
    {
        $url = 'https://securepayments.sberbank.ru/';
        $url = "{$url}{$method}";

        $result = file_get_contents($url, false, stream_context_create([
            'ssl' => [
                "verify_peer" => false,
                "verify_peer_name" => false,
            ],
            'http' => [
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($data),
                'timeout' => 15,
            ],
        ]));

        $this->logger->debug('SberbankPayment: request', ['url' => $url, 'data' => $data]);
        $this->logger->debug('SberbankPayment: response', ['headers' => $http_response_header, 'response' => $result]);

        if ($result) {
            $json = json_decode($result, true);

            if (empty($json['errorCode'])) {
                return $json;
            }
        }

        return false;
    }
}
