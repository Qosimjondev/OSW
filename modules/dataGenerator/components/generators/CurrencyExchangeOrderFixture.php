<?php

namespace app\modules\dataGenerator\components\generators;

use Yii;
use app\components\helpers\ArrayHelper;
use app\models\Currency;
use app\models\CurrencyExchangeOrder;
use app\models\PaymentMethod;
use app\models\User;
use app\modules\dataGenerator\helpers\LatLonHelper;
use app\services\CurrencyExchangeService;
use yii\base\Event;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\helpers\Console;

class CurrencyExchangeOrderFixture extends ARGenerator
{
    private CurrencyExchangeService $service;

    public function __construct($config = [])
    {
        $this->service = new CurrencyExchangeService();

        parent::__construct($config);
    }

    /**
     * @throws ARGeneratorException
     */
    public function init()
    {
        if (!Currency::find()->exists()) {
            throw new ARGeneratorException('Impossible to create Exchange Order - there are no Currency in DB!');
        }

        parent::init();
    }

    protected function factoryModel(): ?ActiveRecord
    {
        $user = $this->findUser();

        [$sellCurrencyId, $buyCurrencyId] = $this->getRandCurrenciesPair();

        if (!$user || !$sellCurrencyId || !$buyCurrencyId) {
            return null;
        }

        $londonCenter = [51.509865, -0.118092];
        [$orderSellingLat, $orderSellingLon] = LatLonHelper::generateRandomPoint($londonCenter, 100);
        [$orderBuyingLat, $orderBuyingLon] = LatLonHelper::generateRandomPoint($londonCenter, 200);

        $crossRateOn = (int)$this->faker->boolean();
        $sellingCashOn = (int)$this->faker->boolean();
        $buyingCashOn = (int)$this->faker->boolean();

        $sellingPaymentMethodsIds = $this->getPaymentMethodsIds($sellCurrencyId);
        $buyingPaymentMethodsIds = $this->getPaymentMethodsIds($buyCurrencyId);

        if ($sellingPaymentMethodsIds) {
            $orderSellingPaymentMethodsIds = $this->faker->randomElements(
                $sellingPaymentMethodsIds,
                $this->faker->numberBetween(1, count($sellingPaymentMethodsIds))
            );
        } else {
            $orderSellingPaymentMethodsIds = [];
            $sellingCashOn = CurrencyExchangeOrder::CASH_ON;
        }

        if ($buyingPaymentMethodsIds) {
            $orderBuyingPaymentMethodsIds = $this->faker->randomElements(
                $buyingPaymentMethodsIds,
                $this->faker->numberBetween(1, count($buyingPaymentMethodsIds))
            );
        } else {
            $orderBuyingPaymentMethodsIds = [];
            $buyingCashOn = CurrencyExchangeOrder::CASH_ON;
        }

        $sellingRate = $crossRateOn ? null :
            $this->faker->valid(static function ($v) {
                return (bool)$v;
            })->randomFloat(1, 0.01, 10);
        $buyingRate = $crossRateOn ? null : 1 / $sellingRate;

        $model = new CurrencyExchangeOrder([
            'selling_currency_id' => $sellCurrencyId,
            'buying_currency_id' => $buyCurrencyId,
            'user_id' => $user->id,
            'selling_rate' => $sellingRate,
            'buying_rate' => $buyingRate,
            'selling_currency_min_amount' => $min_amount = $this->faker->boolean() ? $this->faker->randomNumber(2) : null,
            'selling_currency_max_amount' => $this->faker->boolean() ? $min_amount + $this->faker->randomNumber(2) : null,
            'status' => CurrencyExchangeOrder::STATUS_ON,
            'selling_delivery_radius' => $this->faker->boolean() ? $this->faker->randomNumber(3) : null,
            'buying_delivery_radius' => $this->faker->boolean() ? $this->faker->randomNumber(3) : null,
            'selling_location_lat' => $orderSellingLat,
            'selling_location_lon' => $orderSellingLon,
            'buying_location_lat' => $orderBuyingLat,
            'buying_location_lon' => $orderBuyingLon,
            'selling_cash_on' => $sellingCashOn,
            'buying_cash_on' => $buyingCashOn,
            'cross_rate_on' => $crossRateOn,
        ]);

        if (!$model->save()) {
            throw new ARGeneratorException("Can't save " . static::classNameModel() . "!\r\n");
        }

        $this->service->updatePaymentMethods($model, $orderSellingPaymentMethodsIds, $orderBuyingPaymentMethodsIds);

        return $model;
    }

    /**
     * @throws ARGeneratorException
     */
    public function load(): ActiveRecord
    {
        return $this->factoryModel();
    }

    /**
     * @param int $currencyId
     * @return int[] array
     */
    private function getPaymentMethodsIds(int $currencyId): array
    {
        return array_map('intval',
            ArrayHelper::getColumn(
                PaymentMethod::find()->joinWith('currencies c')
                    ->where(['c.id' => $currencyId])
                    ->select('{{%payment_method}}.id')
                    ->limit(8)
                    ->asArray()
                    ->all(),
                'id'
            )
        );
    }

    /**
     * @return int[]
     */
    private function getRandCurrenciesPair(): array
    {
        $currenciesPairIds = Currency::find()
            ->select('id')
            ->where(['in', 'code', ['USD', 'EUR', 'RUB']])
            ->orderByRandAlt(2)
            ->asArray()
            ->all();

        if (!$currenciesPairIds || count($currenciesPairIds) !== 2) {
            $class = self::classNameModel();
            $message = "\n$class: creation skipped. There is no Currencies yet.\n";
            $message .= "It's not error - few iterations later new ExchangeOrder will be generated.\n";
            Yii::$app->controller->stdout($message, Console::BG_GREY);

            return [];
        }

        return [$currenciesPairIds[0]['id'], $currenciesPairIds[1]['id']];
    }

    private function findUser(): ?User
    {
        $user = User::find()
            ->orderByRandAlt(1)
            ->one();

        if (!$user) {
            $class = self::classNameModel();
            $message = "\n$class: creation skipped. There is no Users\n";
            $message .= "It's not error - few iterations later new ExchangeOrder will be generated.\n";
            Yii::$app->controller->stdout($message, Console::BG_GREY);
        }

        return $user;
    }
}
