<?php

namespace App\DataProviders\Cryptocompare;

use App\DataProviders\BaseAPIProvider;
use App\DataProviders\Exceptions\ConfigException;
use App\DataProviders\Exceptions\CurlException;

/**
 * Class BaseProvider
 * @package App\DataProviders\Cryptocompare
 */
abstract class BaseProvider extends BaseAPIProvider
{
    const CONFIG_FILENAME = 'currency-apis';
    const CONFIG_NAMESPACE = 'cryptocompare';

    const CONFIG_PROP_BASE_URL = 'base_url';
    const CONFIG_PROP_COLUMNS_COUNT = 'columns_count';
    const CONFIG_PROP_PRICE_CACHE_ENABLE = 'price_cache_enable';
    const CONFIG_PROP_PRICE_CACHE_PERIOD = 'price_cache_period';
    const CONFIG_PROP_FUNCTION = 'function';

    const MAX_LIMIT = 2000;
    const DEFAULT_EXCHANGE_NAME = 'CCCAGG';

    const RESPONSE_SUCCESS = 'Success';
    const RESPONSE_ERROR = 'Error';
    const RESPONSE_MESSAGE = 'Message';

    const RESPONSE_FIELD_DATA = 'Data';
    const RESPONSE_FIELD_RESPONSE = 'Response';
    const RESPONSE_FIELD_DISPLAY = 'DISPLAY';
    const RESPONSE_FIELD_RAW = 'RAW';

    const API_PARAMETER_EXCHANGE_NAME = 'e';
    const API_PARAMETER_EXTRA_PARAMS = 'extraParams';
    const API_PARAMETER_SIGN = 'sign';
    const API_PARAMETER_TRY_CONVERSION = 'tryConversion';

    const API_PARAMETER_FROM_SYMBOL = 'fsym';
    const API_PARAMETER_TO_SYMBOL = 'tsym';
    const API_PARAMETER_FROM_SYMBOLS = 'fsyms';
    const API_PARAMETER_TO_SYMBOLS = 'tsyms';
    const API_PARAMETER_AGGREGATE = 'aggregate';
    const API_PARAMETER_LIMIT = 'limit';
    const API_PARAMETER_TO_TIMESTAMP = 'toTs';

    const API_FUNCTION_HISTORY_MINUTE = 'histominute';
    const API_FUNCTION_HISTORY_HOUR = 'histohour';
    const API_FUNCTION_HISTORY_DAY = 'histoday';
    const API_FUNCTION_ALL_EXCHANGES = 'all-exchanges';
    const API_FUNCTION_PRICE_MULTI_FULL = 'pricemultifull';

    const PAIR_PRICES_LAST_UPDATE = 'LASTUPDATE';

    /**
     * @return string
     */
    public function getConfigPath(): string
    {
        return self::CONFIG_FILENAME . '.' . self::CONFIG_NAMESPACE;
    }

    /**
     * @return null
     */
    public function init()
    {
        parent::init();
        $this->config = \Config::get($this->getConfigPath());

        $this
            ->setCacheEnable((bool) ($this->config[self::CONFIG_PROP_PRICE_CACHE_ENABLE] ?? false))
            ->setCachePeriod((int) ($this->config[self::CONFIG_PROP_PRICE_CACHE_PERIOD] ?? 0))
        ;
    }

    /**
     * @return array
     */
    protected function getAvailableParams(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        $availableParams = $this->getAvailableParams();
        $urlParams = empty($availableParams) ? '' : '?' . $this->buildGetParams($availableParams);

        $apiUrl = $this->getAPIUrl() . $urlParams;
        $rawData = $this->sendCurl($apiUrl);
        $data = json_decode($rawData, true);

        if (!is_array($data)) {
            throw new CurlException('Unprocessable response, try connect to: ' . $apiUrl);
        }

        $responseStatus = $data[self::RESPONSE_FIELD_RESPONSE] ?? self::RESPONSE_SUCCESS;

        if ($responseStatus === self::RESPONSE_ERROR) {
            return $data = [];
        }

        return $data;
    }

    /**
     * @param string $functionName
     * @throws ConfigException
     * @return string
     */
    public function getAPIUrlByFunction(string $functionName): string
    {
        if (empty($this->config['function'])) {
            throw new ConfigException('Cryptocompare config not found.');
        }

        if (empty($this->config['function'][$functionName])) {
            throw new ConfigException('Cryptocompare API function [' . $functionName . '] not found.');
        }

        return $this->config[self::CONFIG_PROP_BASE_URL] . '/' . $this->config[self::CONFIG_PROP_FUNCTION][$functionName];
    }
}
