<?php declare(strict_types=1);

namespace Plugin\SberbankPayment\Actions;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use Plugin\SberbankPayment\SberbankPaymentPlugin;

class ResultAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $default = [
            'serial' => '',
            'orderId' => '',
        ];
        $data = array_merge($default, $this->request->getQueryParams());

        $this->logger->debug('SberbankPayment: check', ['data' => $data]);

        $order = $this->catalogOrderService->read(['serial' => $data['serial']]);

        if ($order) {
            /** @var SberbankPaymentPlugin $tp */
            $tp = $this->container->get('SberbankPaymentPlugin');

            $result = $tp->request('payment/rest/getOrderStatus.do', [
                'userName' => $this->parameter('SberbankPaymentPlugin_login'),
                'password' => $this->parameter('SberbankPaymentPlugin_password'),
                'orderId' => $data['orderId'],
                'orderNumber' => $order->serial,
                'language' => 'ru',
            ]);

            if ($result && $result['OrderStatus'] == 2) {
                $this->container->get(\App\Application\PubSub::class)->publish('plugin:order:payment', $order);
            }

            return $this->respondWithRedirect('/cart/done/' . $order->uuid);
        }

        return $this->respondWithRedirect('/');
    }
}
