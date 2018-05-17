<?php
namespace Nezaniel\GeographicLibrary\GoogleMapsAdapter\Service;

/*                                                                                                 *
 * This script belongs to the TYPO3 Flow package "Nezaniel.GeographicLibrary.GoogleMapsAdapter".   *
 *                                                                                                 *
 * It is free software; you can redistribute it and/or modify it under                             *
 * the terms of the GNU General Public License, either version 3 of the                            *
 * License, or (at your option) any later version.                                                 *
 *                                                                                                 *
 * The TYPO3 project - inspiring people to share!                                                  *
 *                                                                                                 */
use Nezaniel\GeographicLibrary\Service\Exception\NoSuchCoordinatesException;
use Nezaniel\GeographicLibrary\Service\GeoCodingAdapterInterface;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class GeoCodingAdapter implements GeoCodingAdapterInterface
{
    /**
     * @Flow\InjectConfiguration(path="GeoCodingService.api.key")
     * @var string
     */
    protected $apiKey;

    /**
     * {@inheritdoc}
     * @param string $address
     * @return array The coordinates
     */
    public function fetchCoordinatesByAddress($address)
    {
        $request = curl_init('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . $this->getApiKey());
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $response = json_decode(curl_exec($request));

        if (empty($response)) {
            throw new NoSuchCoordinatesException();
        }

        return $this->getCoordinatesFromResponse($response);
    }

    /**
     * {@inheritdoc}
     * @param string $zip
     * @param string $countryCode The two character ISO 3166-1 country code
     * @return array The coordinates
     */
    public function fetchCoordinatesByPostalCode($zip, $countryCode)
    {
        $request = curl_init('https://maps.googleapis.com/maps/api/geocode/json?components=postal_code:' . $zip . '|country:' . $countryCode . $this->getApiKey());
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $response = json_decode(curl_exec($request));

        if (empty($response)) {
            throw new NoSuchCoordinatesException();
        }

        return $this->getCoordinatesFromResponse($response);
    }

    /**
     * @param \stdClass $response
     * @return array
     * @throws NoSuchCoordinatesException
     */
    protected function getCoordinatesFromResponse(\stdClass $response)
    {
        if (empty($response->results)) {
            throw new NoSuchCoordinatesException();
        } else {
            $location = $response->results[0]->geometry->location;

            return [
                'latitude' => $location->lat,
                'longitude' => $location->lng
            ];
        }
    }

    /**
     * @return string
     */
    protected function getApiKey() {
        return $this->apiKey ? '&key=' . $this->apiKey : '';
    }
}
