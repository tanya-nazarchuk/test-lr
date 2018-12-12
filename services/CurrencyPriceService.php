<?php

namespace App\Services;

use App\DataProviders\Cryptocompare\BaseProvider;
use App\DataProviders\Cryptocompare\ExchangeProvider;
use App\DataProviders\Cryptocompare\HistoryDayProvider;
use App\DataProviders\Cryptocompare\HistoryFormatter;
use App\DataProviders\Cryptocompare\HistoryHourProvider;
use App\DataProviders\Cryptocompare\HistoryMinuteProvider;
use App\DataProviders\Cryptocompare\HistoryProvider;
use App\DataProviders\InsensitiveArrayFormatter;
use App\DataProviders\Cryptocompare\MultiPriceProvider;
use App\DataProviders\Cryptocompare\MultiPricesFormatter;
use App\DataProviders\Exceptions\ConfigException;
use App\DataProviders\Exceptions\CurlException;
use App\DataProviders\Exceptions\DataProviderNotFoundException;
use App\DataProviders\Interfaces\IAPIProvider;
use App\DataProviders\Interfaces\IHistoryCurrencyFormatter;
use App\Helpers\ArrayHelper;
use App\Repositories\Eloquent\Models\Blacklist;
use App\Repositories\Eloquent\Models\CryptoAPI\CryptocomparePrice;

class CurrencyPriceService
{
    const DEFAULT_COLUMNS_COUNT = 12;

    const YEAR_DURATION = 31536000 ;

    /**
     * @param IAPIProvider $dataProvider
     * @param IHistoryCurrencyFormatter $formatter
     * @param string $periodType
     * @return mixed
     * @codeCoverageIgnore
     */
    protected function getHistoryChartData(
        IAPIProvider $dataProvider,
        IHistoryCurrencyFormatter $formatter,
        string $periodType
    ) {
        $columnsCount = (array) $dataProvider->getConfigValue(BaseProvider::CONFIG_PROP_COLUMNS_COUNT, []);
        $columnCount = $columnsCount[$periodType] ?? self::DEFAULT_COLUMNS_COUNT;

        $historyData = $dataProvider->getData();

        $aggregate = (int) ($dataProvider->getAPIParam(BaseProvider::API_PARAMETER_LIMIT) / $columnCount);
        $chartData = $formatter->format($historyData, $aggregate);

        return $chartData;
    }

    /**
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param string $periodType
     * @throws DataProviderNotFoundException
     * @return HistoryDayProvider|HistoryHourProvider|HistoryMinuteProvider
     */
    public function getHistoryDataProviderByPeriodType(string $fromCurrency, string $toCurrency, string $periodType): HistoryProvider
    {
        switch ($periodType) {
            case CryptocomparePrice::PERIOD_TYPE_HOUR:
                return new HistoryMinuteProvider($fromCurrency, $toCurrency, CryptocomparePrice::PERIOD_TIME_HOUR);

            case CryptocomparePrice::PERIOD_TYPE_DAY:
                return new HistoryHourProvider($fromCurrency, $toCurrency, CryptocomparePrice::PERIOD_TIME_DAY);

            case CryptocomparePrice::PERIOD_TYPE_WEEK:
                return new HistoryDayProvider($fromCurrency, $toCurrency, CryptocomparePrice::PERIOD_TIME_WEEK);

            case CryptocomparePrice::PERIOD_TYPE_MONTH:
                return new HistoryDayProvider($fromCurrency, $toCurrency, CryptocomparePrice::PERIOD_TIME_MONTH);

            case CryptocomparePrice::PERIOD_TYPE_3MONTH:
                return new HistoryDayProvider($fromCurrency, $toCurrency, CryptocomparePrice::PERIOD_TIME_3MONTH);

            case CryptocomparePrice::PERIOD_TYPE_6MONTH:
                return new HistoryDayProvider($fromCurrency, $toCurrency, CryptocomparePrice::PERIOD_TIME_6MONTH);

            case CryptocomparePrice::PERIOD_TYPE_YEAR:
                return new HistoryDayProvider($fromCurrency, $toCurrency, CryptocomparePrice::PERIOD_TIME_YEAR);
        }

        throw new DataProviderNotFoundException('Can\'t found history data provider for this period type: [' . $periodType . ']');
    }

    /**
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param string $periodType
     * @throws DataProviderNotFoundException
     * @throws CurlException
     * @throws ConfigException
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getCryptocompareHistoryChartData(string $fromCurrency, string $toCurrency, string $periodType)
    {
        $historyDataProvider = $this->getHistoryDataProviderByPeriodType($fromCurrency, $toCurrency, $periodType);
        $formatter = new HistoryFormatter();

        return $this->getHistoryChartData($historyDataProvider, $formatter, $periodType);
    }

    /**
     * @param array $pairs e.g. ['BTC' => ['USD', 'EUR'], 'ETH' => ['EUR', 'BTC']] ...
     * @param string $exchange
     * @return array
     *
     * Response example:
     * {
     *      'exchange' => 'Coinbase',
     *      'pairs' => [
     *          'BTC' => [
     *              'USD' => [
     *                  "TYPE":"5",
     *                  "MARKET":"CCCAGG",
     *                  "FROMSYMBOL":"BTC",
     *                  "TOSYMBOL":"USD",
     *                  "FLAGS":"2",
     *                  "PRICE":1082.13,
     *                  "LASTUPDATE":1483529467,
     *                  "LASTVOLUME":2.31159402,
     *                  "LASTVOLUMETO":2496.5215415999996,
     *                  "LASTTRADEID":12826318,
     *                  "VOLUME24HOUR":72040.63471484324,
     *                  "VOLUME24HOURTO":75043516.07861365,
     *                  "OPEN24HOUR":1020.95,
     *                  "HIGH24HOUR":1097.54,
     *                  "LOW24HOUR":980,
     *                  "LASTMARKET":"Bitstamp",
     *                  "CHANGE24HOUR":61.180000000000064,
     *                  "CHANGEPCT24HOUR":5.992458004799457
     *              ],
     *              'EUR' => [
     *                  //...
     *              ],
     *          ]
     *      ]
     * }
     */
    public function getPairsPriceByExchange(array $pairs, string $exchange): array
    {
        $multiPriceFormatter = new MultiPricesFormatter();
        $insensitiveArrayFormatter = new InsensitiveArrayFormatter();

        $pairs = $insensitiveArrayFormatter->format($pairs);

        $multiprices = [];
        foreach ($pairs as $toSymbol => $fromSymbols) {
            $multiPriceDataProvider = new MultiPriceProvider((array) $fromSymbols, (array) $toSymbol, $exchange);
            $data = $multiPriceDataProvider->getData();
            if (empty($data)) {
                continue;
            }
            $multiprices[] = $data;
        }

        return $multiPriceFormatter->format($multiprices);
    }

    public function getPairsBlacklist(array $pairs, string $exchange): array
    {
        $exchangeProvider = $this->getExchangeProvider();
        $allExchanges = $exchangeProvider->getData();

        $allExchanges = $this->reversePairsInExchanges($allExchanges);

        $exchangePairPrices = [];
        foreach ($allExchanges as $exchangeName => $pairs) {
            if (strtoupper($exchangeName) === strtoupper($exchange)) {
                $exchangePairPrices[$exchangeName] = $this->getPairsPriceByExchange($pairs, $exchangeName);
                break;
            }
        }

        $outdatedPairs = $this->getOutdatedPairs($exchangePairPrices);

        $this->saveDataInBlacklist($outdatedPairs, $exchange);

        return $outdatedPairs;
    }

    public function reversePairsInExchanges(array $exchanges): array
    {
        $reverse = [];
        foreach ($exchanges as  $exchange => $pairs) {
            $reverse[$exchange] = $this->reversePairs($pairs);
        }
        return $reverse;
    }

    public function reversePairs(array $pairs): array
    {
        $reversePairs = [];
        foreach ($pairs as $toSymbol => $fromSymbols) {
            foreach ($fromSymbols as $fromSymbol) {
                $reversePairs[$fromSymbol][] = $toSymbol;
            }
        }
        return $reversePairs;
    }

    public function getOutdatedPairs(array $exchangePairPrices): array
    {
        $lastYearDate = time() - self::YEAR_DURATION;

        $blackList = [];
        $pair = [];
        foreach ($exchangePairPrices as $exchange => $pairs) {
            foreach ($pairs as $fromSymbol => $foSymbols) {
                foreach ($foSymbols as $toSymbol => $data) {
                    if ($data[BaseProvider::PAIR_PRICES_LAST_UPDATE] > $lastYearDate) {
                        continue;
                    }
                    $pair[$fromSymbol][] = $toSymbol;
                }
            }
            $blackList[$exchange] = $pair;
            $pair = [];
        }
        return $blackList;
    }

    public function filterOutdatedPairs(array $pairs, string $exchange, $blacklist): array
    {
        $blackListPairs = $blacklist ? json_decode($blacklist->pairs, true) : $this->getPairsBlacklist($pairs, $exchange);
        $revertBlacklist = $this->reversePairsInExchanges($blackListPairs);
        $reversePairs = $this->getReversePairsByExchanger($exchange, $revertBlacklist);

        return $this->getArrayDifference($pairs, $reversePairs);
    }

    public function saveDataInBlacklist(array $outdatedPairs, string $exchange): bool
    {
        if (empty($outdatedPairs[$exchange])) {
            return false;
        }
        return $this->isSaveData($exchange, $outdatedPairs);
    }

    /**
     * @return ExchangeProvider
     * @codeCoverageIgnore
     */
    public function getExchangeProvider()
    {
        return new ExchangeProvider();
    }

    /**
     * @param array $pairs
     * @param array $reversePairs
     * @return array
     * @codeCoverageIgnore
     */
    public function getArrayDifference(array $pairs, array $reversePairs)
    {
        return ArrayHelper::arrayDiffRecursive($pairs, $reversePairs);
    }

    /**
     * @param string $exchanger
     * @param array $revertBlacklist
     * @return array
     * @codeCoverageIgnore
     */
    public function getReversePairsByExchanger(string $exchanger, array $revertBlacklist)
    {
        return $revertBlacklist[ucfirst($exchanger)];
    }

    /**
     * @param $exchange
     * @param $outdatedPairs
     * @return bool
     * @codeCoverageIgnore
     */
    protected function isSaveData($exchange, $outdatedPairs)
    {
        $blacklist = new Blacklist();
        $blacklist->exchange = $exchange;
        $blacklist->pairs = json_encode($outdatedPairs);
        if ($blacklist->save()) {
            return true;
        }
        return false;
    }
}
