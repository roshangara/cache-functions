<?php
namespace Roshangara\CacheFunctions;

use BadMethodCallException;
use Illuminate\Cache\CacheManager;

trait CacheFunctions
{
	protected $cacheClient;

	/**
	 * Handel cache
	 *
	 * @param $method
	 * @param $arguments
	 * @return mixed
	 */
	public function __call($method, $arguments)
	{
		if ($this->cacheEnabled() and $this->isCacheableFunction($method)) {

			// generate unique cache id from class, method and arguments
			$cacheId = $this->getFunctionCacheId($method, $arguments);

			// check cache exist
			if ($this->cacheExist($cacheId))

				// return cache result
				return $this->getCache($cacheId);

			// get data from cacheable function
			$result = call_user_func_array([$this, substr($method, 1)], $arguments);

			// cache function result
			if ($result) {
				$this->cacheFunctionResult($method, $cacheId, $result);
			}

			return $result;
		}

		throw new BadMethodCallException("Method [{$method}] does not exist.");
	}

	/**
	 * Check cache enabled
	 *
	 * @return bool
	 */
	protected function cacheEnabled()
	{
		return true;
	}

	/**
	 * Check function name for cache
	 *
	 * @param $method
	 * @return bool
	 */
	protected function isCacheableFunction($method)
	{
		return substr($method, 0, 1) == "_";
	}

	/**
	 * generate unique cache id from class, method and arguments
	 *
	 * @param $method
	 * @param $arguments
	 * @return string
	 */
	protected function getFunctionCacheId($method, $arguments)
	{
		foreach ($arguments as $index => $argument) {
			if (is_object($argument) and method_exists($argument, 'toArray')) {
				$arguments[ $index ] = $argument->toArray();
			}

		}
		$params = base64_encode(json_encode($arguments));

		return md5(get_called_class() . '->' . $method . "({$params})");
	}

	/**
	 * Check cache exist
	 *
	 * @param $id
	 * @return mixed
	 */
	protected function cacheExist($id)
	{
		return $this->cache()->has($id);
	}

	/**
	 * Cache manager instance
	 *
	 * @return CacheManager
	 */
	protected function cache()
	{
		// crate local instance
		if (!$this->cacheClient) {
			$this->cacheClient = app('cache');
		}

		return $this->cacheClient;
	}

	/**
	 * Get data from cache
	 *
	 * @param $id
	 * @return \Illuminate\Contracts\Cache\Repository
	 */
	protected function getCache($id)
	{
		return $this->cache()->get($id);
	}

	/**
	 * Cache function result
	 *
	 * @param $functionName
	 * @param $key
	 * @param $value
	 */
	protected function cacheFunctionResult($functionName, $key, $value)
	{
		$this->setCache($key, $value, $this->getFunctionCacheTime(substr($functionName, 1)));
	}

	/**
	 * Store value in cache
	 *
	 * @param $key
	 * @param $value
	 * @param $time
	 */
	protected function setCache($key, $value, $time)
	{
		$this->cache()->put($key, $value, $time);
	}

	/**
	 * Get function cache time
	 *
	 * @param $functionName
	 * @param float $default
	 * @return mixed
	 */
	protected function getFunctionCacheTime($functionName, $default = 1.1)
	{
		return (collect($this->functionCacheTime ?? []))->get($functionName, $default);
	}
}
