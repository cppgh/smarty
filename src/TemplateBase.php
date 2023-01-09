<?php
/**
 * Smarty Internal Plugin Smarty Template  Base
 * This file contains the basic shared methods for template handling
 *


 * @author     Uwe Tews
 */

namespace Smarty;

use Smarty\Cacheresource\Base;
use Smarty\Data;
use Smarty\Smarty;
use Smarty\Template;
use Smarty\DataObject;
use Smarty\Exception;

/**
 * Class with shared smarty/template methods
 *
 * @property int $_objType
 *
 */
abstract class TemplateBase extends Data {

	/**
	 * Set this if you want different sets of cache files for the same
	 * templates.
	 *
	 * @var string
	 */
	public $cache_id = null;

	/**
	 * Set this if you want different sets of compiled files for the same
	 * templates.
	 *
	 * @var string
	 */
	public $compile_id = null;

	/**
	 * caching enabled
	 *
	 * @var int
	 */
	public $caching = \Smarty\Smarty::CACHING_OFF;

	/**
	 * check template for modifications?
	 *
	 * @var int
	 */
	public $compile_check = \Smarty\Smarty::COMPILECHECK_ON;

	/**
	 * cache lifetime in seconds
	 *
	 * @var integer
	 */
	public $cache_lifetime = 3600;

	/**
	 * Array of source information for known template functions
	 *
	 * @var array
	 */
	public $tplFunctions = [];

	/**
	 * When initialized to an (empty) array, this variable will hold a stack of template variables.
	 *
	 * @var null|array
	 */
	public $_var_stack = null;

	/**
	 * fetches a rendered Smarty template
	 *
	 * @param string $template the resource handle of the template file or template object
	 * @param mixed $cache_id cache id to be used with this template
	 * @param mixed $compile_id compile id to be used with this template
	 * @param object $parent next higher level of Smarty variables
	 *
	 * @return string rendered template output
	 * @throws Exception
	 * @throws Exception
	 */
	public function fetch($template = null, $cache_id = null, $compile_id = null, $parent = null) {
		$result = $this->_execute($template, $cache_id, $compile_id, $parent, 0);
		return $result === null ? ob_get_clean() : $result;
	}

	/**
	 * displays a Smarty template
	 *
	 * @param string $template the resource handle of the template file or template object
	 * @param mixed $cache_id cache id to be used with this template
	 * @param mixed $compile_id compile id to be used with this template
	 * @param object $parent next higher level of Smarty variables
	 *
	 * @throws \Exception
	 * @throws \Smarty\Exception
	 */
	public function display($template = null, $cache_id = null, $compile_id = null, $parent = null) {
		// display template
		$this->_execute($template, $cache_id, $compile_id, $parent, 1);
	}

	/**
	 * test if cache is valid
	 *
	 * @param null|string|\Smarty\Template $template the resource handle of the template file or template
	 *                                                          object
	 * @param mixed $cache_id cache id to be used with this template
	 * @param mixed $compile_id compile id to be used with this template
	 * @param object $parent next higher level of Smarty variables
	 *
	 * @return bool cache status
	 * @throws \Exception
	 * @throws \Smarty\Exception
	 * @link https://www.smarty.net/docs/en/api.is.cached.tpl
	 *
	 * @api  Smarty::isCached()
	 */
	public function isCached($template = null, $cache_id = null, $compile_id = null, $parent = null) {
		return $this->_execute($template, $cache_id, $compile_id, $parent, 2);
	}

	/**
	 * fetches a rendered Smarty template
	 *
	 * @param string $template the resource handle of the template file or template object
	 * @param mixed $cache_id cache id to be used with this template
	 * @param mixed $compile_id compile id to be used with this template
	 * @param object $parent next higher level of Smarty variables
	 * @param string $function function type 0 = fetch,  1 = display, 2 = isCache
	 *
	 * @return mixed
	 * @throws \Exception
	 * @throws \Smarty\Exception
	 */
	private function _execute($template, $cache_id, $compile_id, $parent, $function) {
		$smarty = $this->_getSmartyObj();
		$saveVars = true;
		if ($template === null) {
			if (!$this->_isTplObj()) {
				throw new Exception($function . '():Missing \'$template\' parameter');
			} else {
				$template = $this;
			}
		} elseif (is_object($template)) {
			/* @var Template $template */
			if (!isset($template->_objType) || !$template->_isTplObj()) {
				throw new Exception($function . '():Template object expected');
			}
		} else {
			// get template object
			$saveVars = false;
			$template = $smarty->createTemplate($template, $cache_id, $compile_id, $parent ? $parent : $this, false);
			if ($this->_objType === 1) {
				// set caching in template object
				$template->caching = $this->caching;
			}
		}
		// make sure we have integer values
		$template->caching = (int)$template->caching;
		// fetch template content
		$level = ob_get_level();
		try {
			$_smarty_old_error_level =
				isset($smarty->error_reporting) ? error_reporting($smarty->error_reporting) : null;

			if ($smarty->isMutingUndefinedOrNullWarnings()) {
				$errorHandler = new \Smarty\ErrorHandler();
				$errorHandler->activate();
			}

			if ($this->_objType === 2) {
				/* @var Template $this */
				$template->tplFunctions = $this->tplFunctions;
				$template->inheritance = $this->inheritance;
			}
			/* @var Template $parent */
			if (isset($parent->_objType) && ($parent->_objType === 2) && !empty($parent->tplFunctions)) {
				$template->tplFunctions = array_merge($parent->tplFunctions, $template->tplFunctions);
			}
			if ($function === 2) {
				if ($template->caching) {
					// return cache status of template
					if (!isset($template->cached)) {
						$template->loadCached();
					}
					$result = $template->cached->isCached($template);
				} else {
					return false;
				}
			} else {
				if ($saveVars) {
					$savedTplVars = $template->tpl_vars;
					$savedConfigVars = $template->config_vars;
				}
				ob_start();

				$template->_mergeVars();
				$template->tpl_vars = array_merge($this->_getSmartyObj()->getAllGlobalTemplateVars(), $template->tpl_vars);

				$result = $template->render(false, $function);
				$template->_cleanUp();
				if ($saveVars) {
					$template->tpl_vars = $savedTplVars;
					$template->config_vars = $savedConfigVars;
				}
			}

			if (isset($errorHandler)) {
				$errorHandler->deactivate();
			}

			if (isset($_smarty_old_error_level)) {
				error_reporting($_smarty_old_error_level);
			}
			return $result;
		} catch (\Throwable $e) {
			while (ob_get_level() > $level) {
				ob_end_clean();
			}
			if (isset($errorHandler)) {
				$errorHandler->deactivate();
			}

			if (isset($_smarty_old_error_level)) {
				error_reporting($_smarty_old_error_level);
			}
			throw $e;
		}
	}

	/**
	 * Registers object to be used in templates
	 *
	 * @param string $object_name
	 * @param object $object the referenced PHP object to register
	 * @param array $allowed_methods_properties list of allowed methods (empty = all)
	 * @param bool $format smarty argument format, else traditional
	 * @param array $block_methods list of block-methods
	 *
	 * @return \Smarty|\Smarty\Template
	 * @throws \Smarty\Exception
	 * @link https://www.smarty.net/docs/en/api.register.object.tpl
	 *
	 * @api  Smarty::registerObject()
	 */
	public function registerObject(
		$object_name,
		$object,
		$allowed_methods_properties = [],
		$format = true,
		$block_methods = []
	) {
		$smarty = $this->_getSmartyObj();
		// test if allowed methods callable
		if (!empty($allowed_methods_properties)) {
			foreach ((array)$allowed_methods_properties as $method) {
				if (!is_callable([$object, $method]) && !property_exists($object, $method)) {
					throw new Exception("Undefined method or property '$method' in registered object");
				}
			}
		}
		// test if block methods callable
		if (!empty($block_methods)) {
			foreach ((array)$block_methods as $method) {
				if (!is_callable([$object, $method])) {
					throw new Exception("Undefined method '$method' in registered object");
				}
			}
		}
		// register the object
		$smarty->registered_objects[$object_name] =
			[$object, (array)$allowed_methods_properties, (boolean)$format, (array)$block_methods];
		return $this;
	}

	/**
	 * Registers plugin to be used in templates
	 *
	 * @param string $object_name name of object
	 *
	 * @return TemplateBase
	 * @api  Smarty::unregisterObject()
	 * @link https://www.smarty.net/docs/en/api.unregister.object.tpl
	 *
	 */
	public function unregisterObject($object_name) {
		$smarty = $this->_getSmartyObj();
		if (isset($smarty->registered_objects[$object_name])) {
			unset($smarty->registered_objects[$object_name]);
		}
		return $this;
	}

	/**
	 * @return int
	 */
	public function getCompileCheck(): int {
		return $this->compile_check;
	}

	/**
	 * @param int $compile_check
	 */
	public function setCompileCheck($compile_check) {
		$this->compile_check = (int)$compile_check;
	}

	/**
	 * @param int $caching
	 */
	public function setCaching($caching) {
		$this->caching = (int)$caching;
	}

	/**
	 * @param int $cache_lifetime
	 */
	public function setCacheLifetime($cache_lifetime) {
		$this->cache_lifetime = $cache_lifetime;
	}

	/**
	 * @param string $compile_id
	 */
	public function setCompileId($compile_id) {
		$this->compile_id = $compile_id;
	}

	/**
	 * @param string $cache_id
	 */
	public function setCacheId($cache_id) {
		$this->cache_id = $cache_id;
	}

	/**
	 * creates a data object
	 *
	 * @param Data|null $parent next higher level of Smarty
	 *                                                                                     variables
	 * @param null $name optional data block name
	 *
	 * @return DataObject data object
	 * @throws Exception
	 * @api  Smarty::createData()
	 * @link https://www.smarty.net/docs/en/api.create.data.tpl
	 *
	 */
	public function createData(Data $parent = null, $name = null) {
		/* @var Smarty $smarty */
		$smarty = $this->_getSmartyObj();
		$dataObj = new DataObject($parent, $smarty, $name);
		if ($smarty->debugging) {
			\Smarty\Debug::register_data($dataObj);
		}
		return $dataObj;
	}

	/**
	 * return name of debugging template
	 *
	 * @return string
	 * @api Smarty::getDebugTemplate()
	 *
	 */
	public function getDebugTemplate() {
		$smarty = $this->_getSmartyObj();
		return $smarty->debug_tpl;
	}


	/**
	 * return a reference to a registered object
	 *
	 * @param string $object_name object name
	 *
	 * @return object
	 * @throws \Smarty\Exception if no such object is found
	 * @link https://www.smarty.net/docs/en/api.get.registered.object.tpl
	 *
	 * @api  Smarty::getRegisteredObject()
	 */
	public function getRegisteredObject($object_name) {
		$smarty = $this->_getSmartyObj();
		if (!isset($smarty->registered_objects[$object_name])) {
			throw new Exception("'$object_name' is not a registered object");
		}
		if (!is_object($smarty->registered_objects[$object_name][0])) {
			throw new Exception("registered '$object_name' is not an object");
		}
		return $smarty->registered_objects[$object_name][0];
	}

	/**
	 * Get literals
	 *
	 * @return array list of literals
	 * @api Smarty::getLiterals()
	 *
	 */
	public function getLiterals() {
		$smarty = $this->_getSmartyObj();
		return (array)$smarty->literals;
	}

	/**
	 * Add literals
	 *
	 * @param array|string $literals literal or list of literals
	 *                                                                                  to addto add
	 *
	 * @return TemplateBase
	 * @throws \Smarty\Exception
	 * @api Smarty::addLiterals()
	 *
	 */
	public function addLiterals($literals = null) {
		if (isset($literals)) {
			$this->_setLiterals($this->_getSmartyObj(), (array)$literals);
		}
		return $this;
	}

	/**
	 * Set literals
	 *
	 * @param array|string $literals literal or list of literals
	 *                                                                                  to setto set
	 *
	 * @return TemplateBase
	 * @throws \Smarty\Exception
	 * @api Smarty::setLiterals()
	 *
	 */
	public function setLiterals($literals = null) {
		$smarty = $this->_getSmartyObj();
		$smarty->literals = [];
		if (!empty($literals)) {
			$this->_setLiterals($smarty, (array)$literals);
		}
		return $this;
	}

	/**
	 * common setter for literals for easier handling of duplicates the
	 * Smarty::$literals array gets filled with identical key values
	 *
	 * @param Smarty $smarty
	 * @param array $literals
	 *
	 * @throws \Smarty\Exception
	 */
	private function _setLiterals(Smarty $smarty, $literals) {
		$literals = array_combine($literals, $literals);
		$error = isset($literals[$smarty->getLeftDelimiter()]) ? [$smarty->getLeftDelimiter()] : [];
		$error = isset($literals[$smarty->getRightDelimiter()]) ? $error[] = $smarty->getRightDelimiter() : $error;
		if (!empty($error)) {
			throw new Exception(
				'User defined literal(s) "' . $error .
				'" may not be identical with left or right delimiter'
			);
		}
		$smarty->literals = array_merge((array)$smarty->literals, (array)$literals);
	}

	/**
	 * Registers static classes to be used in templates
	 *
	 * @param string $class_name
	 * @param string $class_impl the referenced PHP class to
	 *                                                                                    register
	 *
	 * @return TemplateBase
	 * @throws \Smarty\Exception
	 * @api  Smarty::registerClass()
	 * @link https://www.smarty.net/docs/en/api.register.class.tpl
	 *
	 */
	public function registerClass($class_name, $class_impl) {
		$smarty = $this->_getSmartyObj();
		// test if exists
		if (!class_exists($class_impl)) {
			throw new Exception("Undefined class '$class_impl' in register template class");
		}
		// register the class
		$smarty->registered_classes[$class_name] = $class_impl;
		return $this;
	}

	/**
	 * Register config default handler
	 *
	 * @param callable $callback class/method name
	 *
	 * @return TemplateBase
	 * @throws Exception              if $callback is not callable
	 * @api Smarty::registerDefaultConfigHandler()
	 *
	 */
	public function registerDefaultConfigHandler($callback) {
		$smarty = $this->_getSmartyObj();
		if (is_callable($callback)) {
			$smarty->default_config_handler_func = $callback;
		} else {
			throw new Exception('Default config handler not callable');
		}
		return $this;
	}

	/**
	 * Register template default handler
	 *
	 * @param callable $callback class/method name
	 *
	 * @return TemplateBase
	 * @throws Exception              if $callback is not callable
	 * @api Smarty::registerDefaultTemplateHandler()
	 *
	 */
	public function registerDefaultTemplateHandler($callback) {
		$smarty = $this->_getSmartyObj();
		if (is_callable($callback)) {
			$smarty->default_template_handler_func = $callback;
		} else {
			throw new Exception('Default template handler not callable');
		}
		return $this;
	}

	/**
	 * Registers a resource to fetch a template
	 *
	 * @param string $name name of resource type
	 * @param Smarty\Resource\Base $resource_handler instance of Smarty\Resource\Base
	 *
	 * @return \Smarty|\Smarty\Template
	 * @link https://www.smarty.net/docs/en/api.register.resource.tpl
	 *
	 * @api  Smarty::registerResource()
	 */
	public function registerResource($name, \Smarty\Resource\BasePlugin $resource_handler) {
		$smarty = $this->_getSmartyObj();
		$smarty->registered_resources[$name] = $resource_handler;
		return $this;
	}

	/**
	 * Unregisters a resource to fetch a template
	 *
	 * @param string $type name of resource type
	 *
	 * @return TemplateBase
	 * @api  Smarty::unregisterResource()
	 * @link https://www.smarty.net/docs/en/api.unregister.resource.tpl
	 *
	 */
	public function unregisterResource($type) {
		$smarty = $this->_getSmartyObj();
		if (isset($smarty->registered_resources[$type])) {
			unset($smarty->registered_resources[$type]);
		}
		return $this;
	}

	/**
	 * set the debug template
	 *
	 * @param string $tpl_name
	 *
	 * @return TemplateBase
	 * @throws Exception if file is not readable
	 * @api Smarty::setDebugTemplate()
	 *
	 */
	public function setDebugTemplate($tpl_name) {
		$smarty = $this->_getSmartyObj();
		if (!is_readable($tpl_name)) {
			throw new Exception("Unknown file '{$tpl_name}'");
		}
		$smarty->debug_tpl = $tpl_name;
		return $this;
	}

}
