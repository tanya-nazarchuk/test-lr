<?php

namespace App\DataProviders\Cryptocompare;

use App\DataProviders\Exceptions\ConfigException;

class HistoryDayProvider extends HistoryProvider
{
    /**
     * @throws ConfigException
     * @return string
     */
    public function getAPIUrl(): string
    {
        return $this->getAPIUrlByFunction(self::API_FUNCTION_HISTORY_DAY);
    }
}
