<?php

declare(strict_types=1);

namespace Nezaniel\GeographicLibrary\GoogleMapsAdapter;

use Nezaniel\GeographicLibrary\CoordinatesCouldNotBeResolved;
use Nezaniel\GeographicLibrary\CountryCode;
use Nezaniel\GeographicLibrary\GeoCoordinates;
use Nezaniel\GeographicLibrary\GeoCoderInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Service as LocalizationService;

#[Flow\Scope('singleton')]
class GoogleMapsGeoCoder implements GeoCoderInterface
{
    #[Flow\InjectConfiguration(path: 'api.key')]
    protected ?string $apiKey = null;

    public function __construct(
        private readonly LocalizationService $localizationService,
    ) {
    }

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
            if ($this->localizationService->getConfiguration()->getCurrentLocale()->getLanguage() !== 'en') {
                $requestUri .= ',en';
            }
        }

        $request = \curl_init($requestUri);
        \curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $response = \json_decode(\curl_exec($request));

        if (empty($response)) {
            throw new CoordinatesCouldNotBeResolved('Got empty response for address ' . $address . ' (' . ($response->error_message ?? 'no message') . ')');
        }

        return $this->getCoordinatesFromResponse($response);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchCoordinatesByPostalCode(string $postalCode, CountryCode $countryCode): GeoCoordinates
    {
        $components = 'postal_code:' . $postalCode . '|country:' . $countryCode->value;
        $requestUri = 'https://maps.googleapis.com/maps/api/geocode/json?components=' . $components . '&sensor=false';
        if ($this->apiKey) {
            $requestUri .= '&key=' . $this->apiKey;
        }
        if ($this->localizationService->getConfiguration()->getCurrentLocale()->getLanguage()) {
            $requestUri .= '&language=' . $this->localizationService->getConfiguration()->getCurrentLocale()->getLanguage();
            if ($this->localizationService->getConfiguration()->getCurrentLocale()->getLanguage() !== 'en') {
                $requestUri .= ',en';
            }
        }
        $request = \curl_init($requestUri);
        \curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $response = \json_decode(\curl_exec($request));

        if (empty($response)) {
            throw new CoordinatesCouldNotBeResolved('Got empty response for components ' . $components . ' (' . ($response->error_message ?? 'no message') . ')');
        }

        return $this->getCoordinatesFromResponse($response);
    }

    /**
     * {@inheritdoc}
     */
    public function enrichGeoCoordinates(GeoCoordinates $coordinates): GeoCoordinates
    {
        $requestCoordinates = $coordinates->latitude . ',' . $coordinates->longitude;
        $requestUri = 'https://maps.googleapis.com/maps/api/geocode/json?latlng=' . $requestCoordinates . '&sensor=false';
        if ($this->apiKey) {
            $requestUri .= '&key=' . $this->apiKey;
        }
        if ($this->localizationService->getConfiguration()->getCurrentLocale()->getLanguage()) {
            $requestUri .= '&language=' . $this->localizationService->getConfiguration()->getCurrentLocale()->getLanguage();
            if ($this->localizationService->getConfiguration()->getCurrentLocale()->getLanguage() !== 'en') {
                $requestUri .= ',en';
            }
        }

        $request = \curl_init($requestUri);
        \curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $response = \json_decode(\curl_exec($request));

        if (empty($response)) {
            throw new CoordinatesCouldNotBeResolved('Got empty response for coordinates ' . $requestCoordinates . ' (' . ($response->error_message ?? 'no message') . ')');
        }

        return $this->getCoordinatesFromResponse($response);
    }

    /**
     * @throws CoordinatesCouldNotBeResolved
     */
    protected function getCoordinatesFromResponse(\stdClass $response): GeoCoordinates
    {
        if (empty($response->results)) {
            throw new CoordinatesCouldNotBeResolved($response->error_message ?? 'Got empty result set for response');
        } else {
            $primaryLocation = $response->results[0];
            $coordinates = $primaryLocation->geometry->location;
            $postalCode = null;
            $countryCode = null;

            foreach ($primaryLocation->address_components as $addressComponent) {
                switch (reset($addressComponent->types)) {
                    case 'postal_code':
                        $postalCode = $addressComponent->short_name;
                        break;
                    case 'country':
                        $countryCode = new CountryCode($addressComponent->short_name);
                        break;
                    default:
                }
            }

            return new GeoCoordinates(
                $coordinates->lat,
                $coordinates->lng,
                null,
                $primaryLocation->formatted_address ?? null,
                $postalCode,
                $countryCode
            );
        }
    }
}
