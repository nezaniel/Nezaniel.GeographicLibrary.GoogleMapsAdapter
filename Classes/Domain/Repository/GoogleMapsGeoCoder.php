<?php

namespace Nezaniel\GeographicLibrary\GoogleMapsAdapter\Domain\Repository;

/*                                                                                                 *
 * This script belongs to the Neos Flow package "Nezaniel.GeographicLibrary.GoogleMapsAdapter".   *
 *                                                                                                 */
use Nezaniel\GeographicLibrary\Application\Value\Coordinates;
use Nezaniel\GeographicLibrary\Domain\Exception\NoSuchCoordinatesException;
use Nezaniel\GeographicLibrary\Domain\Repository\GeoCoderInterface;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class GoogleMapsGeoCoder implements GeoCoderInterface
{
    /**
     * @Flow\InjectConfiguration(path="api.key")
     * @var string
     */
    protected $apiKey;


    /**
     * {@inheritdoc}
     */
    public function fetchCoordinatesByAddress(string $address): Coordinates
    {
        $request = curl_init('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&sensor=false' . ($this->apiKey ? '&key=' . $this->apiKey : ''));
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $response = json_decode(curl_exec($request));

        if (empty($response)) {
            throw new NoSuchCoordinatesException();
        }

        return $this->getCoordinatesFromResponse($response);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchCoordinatesByPostalCode(string $zip, string $countryCode): Coordinates
    {
        $request = curl_init('https://maps.googleapis.com/maps/api/geocode/json?components=postal_code:' . $zip . '|country:' . $countryCode . '&sensor=false' . ($this->apiKey ? '&key=' . $this->apiKey : ''));
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $response = json_decode(curl_exec($request));

        if (empty($response)) {
            throw new NoSuchCoordinatesException();
        }

        return $this->getCoordinatesFromResponse($response);
    }

    /**
     * @param \stdClass $response
     * @return Coordinates
     * @throws NoSuchCoordinatesException
     */
    protected function getCoordinatesFromResponse(\stdClass $response): Coordinates
    {
        if (empty($response->results)) {
            throw new NoSuchCoordinatesException();
        } else {
            $location = $response->results[0]->geometry->location;

            return new Coordinates($location->lat, $location->lng);
        }
    }
}
