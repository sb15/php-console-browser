<?php

namespace Sb\Browser\Cache;

interface CacheInterface
{
    public function load($key);
    public function save($key, $value);
}