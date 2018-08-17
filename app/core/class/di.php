<?php
/**
 * Elyzin - PHP based free forum software
 * 
 * @since 0.1.0
 * @version 0.1.0
 * @package Model : Dependency Injection
 * @author Mustafa Magdi <https://medium.com/@MustafaMagdi>, Joe Mottershaw <https://medium.com/@joemottershaw>
 * @source https://github.com/elyzin/elyzin Base repository
 * @link http://elyz.in
 * @copyright 2018 Elyzin
 * @license MIT
 * 
 * @todo Namespace
 * @todo Interface
 */
class DI
{
	/**
	 * @var array
	 */
	protected $instances = [];

	/**
	 * @param      $abstract
	 * @param null $concrete
	 */
	public function push($abstract, $concrete = null)
	{
		if ($concrete === null) {
			$concrete = $abstract;
		}
		$this->instances[$abstract] = $concrete;
	}

	/**
	 * @param       $abstract
	 * @param array $values
	 *
	 * @return mixed|null|object
	 * @throws Exception
	 */
	public function pull($abstract, $values = [])
	{
		// if we don't have it, just register it
		if (!isset($this->instances[$abstract])) {
			$this->set($abstract);
		}

		return $this->resolve($this->instances[$abstract], $values);
	}

	/**
	 * resolve single
	 *
	 * @param $concrete
	 * @param $values
	 *
	 * @return mixed|object
	 * @throws Exception
	 */
	public function resolve($concrete, $values = [])
	{
		if ($concrete instanceof Closure) {
			return $concrete($this, $values);
		}

		$reflector = new ReflectionClass($concrete);
		// check if class is instantiable
		if (!$reflector->isInstantiable()) {
			throw new Exception("Class {$concrete} is not instantiable");
		}

		// get class constructor
		$constructor = $reflector->getConstructor();
		if (is_null($constructor)) {
			// get new instance from class
			return $reflector->newInstance();
		}

		// get constructor params
		$parameters = $constructor->getParameters();
		$dependencies = $this->getDependencies($parameters, $values);

		// get new instance with dependencies resolved
		return $reflector->newInstanceArgs($dependencies);
	}

	/**
	 * get all dependencies resolved
	 *
	 * @param $parameters
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getDependencies($parameters, $values)
	{
		$dependencies = [];
		foreach ($parameters as $parameter) {
			// get the type hinted class
			$dependency = $parameter->getClass();
			if ($dependency === null) {
				// check if the constructor parameter name exists as a key in the values array
				if (array_key_exists($parameter->getName(), $values)) {
				  // get default value of parameter
					$dependencies[] = $values[$parameter->getName()];
				} else {
				  // check if default value for a parameter is available
					if ($parameter->isDefaultValueAvailable()) {
					// get default value of parameter
						$dependencies[] = $parameter->getDefaultValue();
					} else {
						throw new Exception("Can not resolve class dependency {$parameter->name}");
					}
				}
			} else {
				// get dependency resolved
				$dependencies[] = $this->get($dependency->name);
			}
		}

		return $dependencies;
	}
}