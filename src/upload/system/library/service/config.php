<?php
namespace Service;

/**
 *
 * @property \Config $config
 */
class Config
{
    private $config;
    public function __construct($registry)
    {
        $this->config = $registry->get('config');
    }

    public function get($key, $default = null)
    {
        if ($this->config->has($key)) {
            return $this->config->get($key);
        }

        return $default;
    }
}