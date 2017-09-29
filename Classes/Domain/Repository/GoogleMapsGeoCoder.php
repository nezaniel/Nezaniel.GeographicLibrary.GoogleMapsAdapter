<?php

namespace Nezaniel\GeographicLibrary\GoogleMapsAdapter\Domain\Repository;

/*                                                                                                 *
 * This script belongs to the Neos Flow package "Nezaniel.GeographicLibrary.GoogleMapsAdapter".   *
 *                                                                                                 */
use Nezaniel\GeographicLibrary\Application\Value\CountryCode;
use Nezaniel\GeographicLibrary\Application\Value\GeoCoordinates;
use Nezaniel\GeographicLibrary\Domain\Exception\NoSuchCoordinatesException;
use Nezaniel\GeographicLibrary\Domain\Repository\GeoCoderInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n;

/**
 * @Flow\Scope("singleton")
 */
class GoogleMapsGeoCoder implements GeoCoderInterface
{
    /**
     * @Flow\Inject
     * @var I18n\Service
     */
    protected $localizationService;

    /**
     * @Flow\InjectConfiguration(path="api.key")
     * @var string
     */
    protected $apiKey;


    /**
     * {@inheritdoc}
     */
    public function fetchCoordinatesByAddress(string $address): GeoCoordinates
    {
        $requestUri = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&sensor=false';
        if ($this->apiKey) {
            $requestUri .= '&key=' . $this->apiKey;
        }
        if ($this->localizationService->getConfiguration()->getCurrentLocale()->getLanguage()) {
            $requestUri .= '&language=' . $this->localizationService->getConfiguration()->getCurrentLocale()->getLanguage();
        }
        $request = curl_init($requestUri);
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
    public function fetchCoordinatesByPostalCode(string $zip, string $countryCode): GeoCoordinates
    {
        $requestUri = 'https://maps.googleapis.com/maps/api/geocode/json?components=postal_code:' . $zip . '|country:' . $countryCode . '|locale:de&sensor=false';
        if ($this->apiKey) {
            $requestUri .= '&key=' . $this->apiKey;
        }
        if ($this->localizationService->getConfiguration()->getCurrentLocale()->getLanguage()) {
            $requestUri .= '&language=' . $this->localizationService->getConfiguration()->getCurrentLocale()->getLanguage();
        }
        $request = curl_init($requestUri);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $response = json_decode(curl_exec($request));

        if (empty($response)) {
            throw new NoSuchCoordinatesException();
        }

        return $this->getCoordinatesFromResponse($response);
    }

    /**
     * @param \stdClass $response
     * @return GeoCoordinates
     * @throws NoSuchCoordinatesException
     */
    protected function getCoordinatesFromResponse(\stdClass $response): GeoCoordinates
    {
        if (empty($response->results)) {
            throw new NoSuchCoordinatesException();
        } else {
            $primaryLocation = $response->results[0];
            $coordinates = $primaryLocation->geometry->location;
            $address = null;
            $addressLocality = null;
            $postalCode = null;
            $countryCode = null;

            foreach ($primaryLocation->address_components as $addressComponent) {
                switch (reset($addressComponent->types)) {
                    case 'country':
                        $countryCode = new CountryCode($addressComponent->short_name);
                        break;
                    case 'locality':
                        $addressLocality = $addressComponent->short_name;
                        break;
                    case 'postal_code':
                        $postalCode = $addressComponent->short_name;
                        break;
                    default:
                }
            }

            return new GeoCoordinates(
                $coordinates->lat,
                $coordinates->lng,
                null,
                $postalCode . ' ' . $addressLocality,
                $postalCode,
                $countryCode
            );
        }
    }
}
