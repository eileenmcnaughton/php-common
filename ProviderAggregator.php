<?php

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder;

use Geocoder\Exception\ProviderNotRegistered;
use Geocoder\Model\Coordinates;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\Provider\Provider;

/**
 * @author William Durand <william.durand1@gmail.com>
 */
class ProviderAggregator implements Geocoder
{
    /**
     * @var Provider[]
     */
    private $providers = [];

    /**
     * @var Provider
     */
    private $provider;

    /**
     * @var int
     */
    private $limit;

    /**
     * A callable that decided what provider to use.
     *
     * @var callable
     */
    private $decider;

    /**
     * @param callable|null $decider
     * @param int           $limit
     */
    public function __construct(callable $decider = null, $limit = Geocoder::DEFAULT_RESULT_LIMIT)
    {
        $this->limit = $limit;
        $this->decider = !empty($decider) ? $decider :  __CLASS__.'::getProvider';
    }

    /**
     * {@inheritdoc}
     */
    public function geocodeQuery(GeocodeQuery $query)
    {
        if (null === $query->getLimit()) {
            $query = $query->withLimit($this->limit);
        }

        return call_user_func($this->decider, $query, $this->providers, $this->provider)->geocodeQuery($query);
    }

    /**
     * {@inheritdoc}
     */
    public function reverseQuery(ReverseQuery $query)
    {
        if (null === $query->getLimit()) {
            $query = $query->withLimit($this->limit);
        }

        return call_user_func($this->decider, $query, $this->providers, $this->provider)->reverseQuery($query);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'provider_aggregator';
    }

    /**
     * {@inheritdoc}
     */
    public function geocode($value)
    {
        return $this->geocodeQuery(GeocodeQuery::create($value)
            ->withLimit($this->limit));
    }

    /**
     * {@inheritdoc}
     */
    public function reverse($latitude, $longitude)
    {
        return $this->reverseQuery(ReverseQuery::create(new Coordinates($latitude, $longitude))
            ->withLimit($this->limit));
    }

    /**
     * Registers a new provider to the aggregator.
     *
     * @param Provider $provider
     *
     * @return ProviderAggregator
     */
    public function registerProvider(Provider $provider)
    {
        $this->providers[$provider->getName()] = $provider;

        return $this;
    }

    /**
     * Registers a set of providers.
     *
     * @param Provider[] $providers
     *
     * @return ProviderAggregator
     */
    public function registerProviders(array $providers = [])
    {
        foreach ($providers as $provider) {
            $this->registerProvider($provider);
        }

        return $this;
    }

    /**
     * Sets the default provider to use.
     *
     * @param string $name
     *
     * @return ProviderAggregator
     */
    public function using($name)
    {
        if (!isset($this->providers[$name])) {
            throw ProviderNotRegistered::create(!empty($name) ? $name : '', $this->providers);
        }

        $this->provider = $this->providers[$name];

        return $this;
    }

    /**
     * Returns all registered providers indexed by their name.
     *
     * @return Provider[]
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /**
     * Get a provider to use for this query.
     *
     * @param GeocodeQuery|ReverseQuery $query
     * @param Provider[]                $providers
     * @param Provider                  $currentProvider
     *
     * @return Provider
     *
     * @throws ProviderNotRegistered
     */
    private static function getProvider($query, array $providers, Provider $currentProvider = null)
    {
        if (null !== $currentProvider) {
            return $currentProvider;
        }

        if (0 === count($providers)) {
            throw ProviderNotRegistered::noProviderRegistered();
        }

        // Take first
        $key = key($providers);

        return $providers[$key];
    }
}
