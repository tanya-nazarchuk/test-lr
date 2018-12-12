<?php

namespace App\Http\Controllers;

use App;
use Request;
use Exception;
use App\Repositories\Eloquent\Models\Blacklist;

/**
 * Class CurrencyAPIController
 * @package App\Http\Controllers
 */
class CurrencyAPIController extends Controller
{
    /**
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param string $periodType
     * @return mixed
     */
    public function getHistoryChartData(string $fromCurrency, string $toCurrency, string $periodType)
    {
        $currencyPriceService = new App\Services\CurrencyPriceService();

        try {
            $chartData = $currencyPriceService->getCryptocompareHistoryChartData(
                $fromCurrency,
                $toCurrency,
                $periodType
            );

            return $this->getSuccessResponse([
                'chartData' => $chartData,
                'chartPeriodType' => $periodType,
            ]);
        } catch (Exception $e) {
            return $this->getFailResponseByException($e);
        }
    }

    /**
     * @param string|null $exchange
     * @return mixed
     */
    public function getPairsByExchange(string $exchange = null)
    {
        $exchangeService = new App\Services\CurrencyExchangeService();
        $currencyPriceService = new App\Services\CurrencyPriceService();

        try {
            $pairs = $exchangeService->getPairsByExchange($exchange);
            $blacklist = Blacklist::getPairsByExchangeName($exchange);
            if ($blacklist) {
                $pairs = $currencyPriceService->reversePairs($pairs);
                $pairs = $currencyPriceService->filterOutdatedPairs($pairs, $exchange, $blacklist);
                $pairs = $currencyPriceService->reversePairs($pairs);
            }
            return $this->getSuccessResponse(['pairs' => $pairs]);
        } catch (Exception $e) {
            return $this->getFailResponseByException($e);
        }
    }

    /**
     * @return mixed
     */
    public function getPairsPriceByExchange()
    {
        $pairs = (array) Request::post('pairs', []);
        $exchange = (string) ucfirst(Request::post('exchange'));
        $currencyPriceService = new App\Services\CurrencyPriceService();

        $blacklist = Blacklist::getPairsByExchangeName($exchange);
        $pairs = $currencyPriceService->filterOutdatedPairs($pairs, $exchange, $blacklist);

        try {
            $prices = $currencyPriceService->getPairsPriceByExchange($pairs, $exchange);

            return $this->getSuccessResponse([
                'prices' => $prices,
                'exchange' => $exchange,
            ]);
        } catch (Exception $e) {
            return $this->getFailResponseByException($e);
        }
    }

    /**
     * @return mixed
     */
    public function getLastTrades()
    {
        $pairs = (array) Request::post('pairs', []);
        $exchange = (string) Request::post('exchange');
        $tradesCount = (int) Request::post('tradesCount');
        $currencyPriceService = new App\Services\CurrencyPriceService();

        $exchangeService = new App\Services\CurrencyExchangeService();
        $exchange = (string) $exchangeService->searchSensitiveExchangeName($exchange);

        $blacklist = Blacklist::getPairsByExchangeName($exchange);
        $pairs = $currencyPriceService->filterOutdatedPairs($pairs, $exchange, $blacklist);

        $tradeService = new App\Services\TradeService();

        try {
            $tradeData = $tradeService->getPairsDataByExchange($pairs, $exchange, $tradesCount);

            return $this->getSuccessResponse([
                'trades' => $tradeData,
                'exchange' => $exchange,
            ]);
        } catch (Exception $e) {
            return $this->getFailResponseByException($e);
        }
    }
}
