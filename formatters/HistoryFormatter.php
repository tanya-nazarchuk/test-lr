<?php

namespace App\DataProviders\Cryptocompare;

use App\DataProviders\Exceptions\UnprocessableDataException;
use App\DataProviders\Interfaces\IHistoryCurrencyFormatter;
use App\Repositories\Eloquent\Models\CryptoAPI\CryptocomparePrice;

/**
 * Class HistoryFormatter
 * @package App\DataProviders\Cryptocompare
 */
class HistoryFormatter implements IHistoryCurrencyFormatter
{
    /**
     * @param array $rawData
     * @param int|null $aggregate
     * @return array
     */
    protected function aggregateData(array $rawData, int $aggregate = null): array
    {
        if (!$aggregate) {
            return $rawData;
        }

        $rawData = array_reverse($rawData);

        $aggrData = [];
        foreach ($rawData as $key => $value) {
            if ($key % $aggregate === 0) {
                $aggrData[] = $value;
            }
        }

        return array_reverse($aggrData);
    }

    /**
     * @param $low
     * @param $high
     * @return float
     */
    protected function getAveragePrice($low, $high): float
    {
        return ((float) $high + (float) $low) / 2;
    }

    /**
     * @param $rawData
     * @param int|null $aggregate
     * @return array
     */
    public function format($rawData, int $aggregate = null): array
    {
        $responseStatus = $rawData[BaseProvider::RESPONSE_FIELD_RESPONSE] ?? BaseProvider::RESPONSE_SUCCESS;

        if (
            $responseStatus === BaseProvider::RESPONSE_SUCCESS
            && array_key_exists(BaseProvider::RESPONSE_FIELD_DATA, $rawData)
            && is_array($rawData[BaseProvider::RESPONSE_FIELD_DATA])
        ) {
            $rawData = $rawData[BaseProvider::RESPONSE_FIELD_DATA];
        } else {
            throw new UnprocessableDataException('Unprocessable raw data or bad response status.');
        }

        $rawData = (array) $rawData;
        $data = $this->aggregateData($rawData, $aggregate);

        $formatData = [];

        foreach ($data as $priceData) {
            $priceModel = new CryptocomparePrice($priceData);

            $averagePrice = $this->getAveragePrice($priceModel->low, $priceModel->high);
            $time = $priceModel->time;

            $formatData[] = [
                'price' => $averagePrice,
                'time' => $time,
            ];
        }

        return $formatData;
    }
}
