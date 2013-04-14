<?php
App::uses('Hash', 'Utility');
App::uses('Inflector', 'Utility');
App::uses('CakeSession', 'Model/Datasource');

/**
 * TransitionComponent. Among form pages , auto validation and auto redirect.
 *
 * PHP versions 5 , CakePHP => 2.2
 *
 * @copyright     Copyright 2010, hiromi
 * @copyright     Copyright 2013, zaininnari
 *
 * @package       transition
 * @subpackage    transition.controllers.components
 * @version       2.2.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

class TransitionComponent extends Component {

	const PREF = 'prev';
	const NEXT = 'next';

/**
 * current step.
 *
 * @var string
 */
	public $step = null;

/**
 * current value.
 *
 * @var string
 */
	public $stepValue = null;

/**
 * Turns on or off automation on startup.
 * When Array given, This automate within these key as actions.
 *
 * Example.
 * // beforeFilter  in controller
 * $this->Transition->automation = array(
 *   'action' => array(
 *     'nextStep'          => 'nextAction',
 *     'models'            => array('Model1', 'Model2'),
 *     'prev'              => 'prevAction',
 *     'validationMethod'  => array(&$this->Model3, 'behaviorMethod'),
 *     'messages'          => array(
 *       'invalid' => __('your input was wrong.'),
 *       'prev'    => __('wrong transition.'),
 *     ),
 *   )
 * );
 *
 * @var mixed array or false
 */
	public $automation = false;

/**
 * Messages set with Session::setFlash().
 * "invalid" key , When it cannot pass validation.
 * "prev"    key , When session has no data for previous action.
 *
 * @var array default messages with key
 */
	public $messages = array();

/**
 * Parametors set with Session::setFlash().
 * "element" key Element to wrap flash message in.
 * "params"  key , Parameters to be sent to layout as view variables.
 * "key"     key , Message key, default is 'flash'.
 *
 * @var array default messages with key
 */
	public $flashParams = array(
		'element' => 'default',
		'params' => array(),
		'key' => 'flash',
	);

/**
 * Turns on or off auto loading session data to Controller::data.
 *
 * @var boolean auto loading data
 */
	public $autoComplete = true;

/**
 * Turns on or off auto redirect when data passes validation or session data of previous action is empty.
 *
 * @var boolean auto redirection
 */
	public $autoRedirect = true;

/**
 * Default models.
 *
 * @var array models
 */
	public $models = null;

/**
 * Default validation method.
 *
 * @var callback validation method
 */
	public $validationMethod = null;

/**
 * Holds the reference of current controller
 *
 * @var object controller
 */
	public $Controller;

/**
 * Base of session key
 *
 * @var string session base name
 */
	public $sessionBaseKey = 'Transition';

/**
 * Inflection method for controller name.
 * if controller name was detected from current controller, this method was applied to controller name.
 *
 * @var string method name for Inflector
 */
	public $controllerInflection = 'underscore';

/**
 * Constructor
 *
 * @param ComponentCollection $collection A ComponentCollection this component can use to lazy load its components
 * @param array $settings Array of configuration settings.
 */
	public function __construct(ComponentCollection $collection, $settings = array()) {
		$settings = array_merge($this->settings, (array)$settings);

		// set default
		$this->messages = array(
			'invalid' => __d('transition', 'Input Data was not able to pass validation. Please, try again.', true),
			'prev' => __d('transition', 'Session timed out.', true),
		);
		// configure.
		$this->_set($settings);
		$this->Controller = $collection->getController();

		parent::__construct($collection, $settings);
	}

/**
 * Called before the Controller::beforeFilter().
 * Initialize the TransitionComponent
 *
 * @param Controller $controller Controller with components to initialize
 * @return void
 * @link http://book.cakephp.org/2.0/en/controllers/components.html#Component::initialize
 */
	public function initialize(Controller $controller) {
		$this->setStep();
	}

	public function setStep() {
		// response
		$controller = $this->Controller;
		if (Hash::check($controller->request->data, $this->sessionBaseKey)) {
			if (Hash::check($controller->request->data, $this->sessionBaseKey . '.' . static::PREF)) {
				$this->step = static::PREF;
				$this->stepValue = Hash::get($controller->request->data, $this->sessionBaseKey . '.' . static::PREF);
				unset($controller->request->data[$this->sessionBaseKey][static::PREF]);
			} elseif (Hash::check($controller->request->data, $this->sessionBaseKey . '.' . static::NEXT)) {
				$this->step = static::NEXT;
				$this->stepValue = Hash::get($controller->request->data, $this->sessionBaseKey . '.' . static::NEXT);
				unset($controller->request->data[$this->sessionBaseKey][static::NEXT]);
			}
			if (count($controller->request->data[$this->sessionBaseKey]) === 0) {
				unset($controller->request->data[$this->sessionBaseKey]);
			}
		}
		return true;
	}

/**
 * Called after the Controller::beforeFilter() and before the controller action
 * Component startup. Given automation options , It will automate.
 *
 * @param Controller $controller Controller with components to startup
 * @return void
 * @link http://book.cakephp.org/2.0/en/controllers/components.html#Component::startup
 */
	public function startup(Controller $controller) {
		$controller->set('transitionSessionBaseKey', $this->sessionBaseKey);

		if ($this->automation !== false) {
			$doAutomate = is_array($this->automation) && array_key_exists($this->Controller->request->action, $this->automation);

			if ($doAutomate) {
				$automation = $this->automation[$this->Controller->request->action];
				$defaults = array(
					'nextStep' => null,
					'models' => $this->models,
					'prev' => null,
					'validationMethod' => $this->validationMethod,
					'messages' => $this->messages,
				);
				$automation = array_merge($defaults, $automation);
				$this->automate($automation['prev'], $automation['nextStep'], $automation);
			}
		}
		return true;
	}

/**
 * filters null.
 *
 * @param array $params parameters to be filtered
 * @return array filtered parameters
 */
	protected function _filter(Array $params) {
		foreach ($params as $key => $value) {
			if ($value === null) {
				unset($params[$key]);
			}
		}

		return $params;
	}

/**
 * Automation method.
 *
 * @param mixed $nextStep Next step url (will be given Controller::redirect())
 * @param mixed $prev Previous action for check
 * @param array $options automate options. only 'models', 'validationMethod' and 'messages' are expected.
 * @return boolean Success
 */
	public function automate($prev, $nextStep, $options = array(), $validationMethod = null, $messages = null) {
		// mostly compatible
		if (is_string($options)) {
			$options = array('models' => $options);
		} elseif ($options !== array() && empty($options)) {
			$options = array();
		}

		$options += $this->_filter(compact('validationMethod', 'messages'));

		$defaults = array(
			'messages' => $this->messages,
			'models' => $this->models,
			'validationMethod' => $this->validationMethod,
		);

		extract($options = Hash::merge($defaults, $options));

		if ($prev !== null) {
			$prevOption = array_merge($options, array('message' => $messages['prev']));
			if (!$this->checkPrev($prev, $prevOption)) {
				return false;
			}
		}

		if ($nextStep !== null) {
			$nextOption = array_merge($options, array('message' => $messages['invalid']));
			if (!$this->checkData($nextStep, $nextOption)) {
				return false;
			}
		}

		return true;
	}

/**
 * Check previous session data.
 *
 * @param mixed $prev Previous action for check
 * @param array $options
 * @param string $prevAction Previous action to Redirect.
 * @return boolean Success
 */
	public function checkPrev($prev, $options = array(), $prevAction = null) {
		// mostly compatible
		if (is_string($options)) {
			$options = array('message' => $options);
		} elseif ($options !== array() && empty($options)) {
			$options = array();
		}
		$options += $this->_filter(compact('prevAction'));

		$defaults = array(
			'message' => $this->messages['prev'],
		);
		extract($options = array_merge($defaults, $options));

		if (is_array($prev) && Hash::numeric(array_keys($prev))) {

			foreach ($prev as $p) {
				if (!$this->checkPrev($p, $options)) {
					return false;
				}
			}

			return true;

		}

		if (!is_array($prev)) {
			$prev = array('action' => $prev);
		}

		if ($prevAction === null) {
			$prevAction = $prev;
		}

		if (!CakeSession::check($this->sessionKey($prev))) {

			if ($message !== false) {
				CakeSession::write('Message.' . $this->flashParams['key'], array(
					'message' => $message,
					'element' => $this->flashParams['element'],
					'params' => $this->flashParams['params']
				));
			}

			if ($this->autoRedirect) {
				return $this->Controller->redirect($prevAction);
			}

			return false;

		}

		return true;
	}

/**
 * Checking data of current controller with auto validation , auto redirection , auto setFlash() , and auto restoring data
 *
 * @param mixed $nextStep Next step url (will be passed to Controller::redirect())
 * @param mixed $models Models for validation
 * @param callback $validationMethod Method to validate
 * @param string $messages Messages to Controller::setFlash()
 * @param string $sessionKey Session key to store
 * @return boolean Success
 */
	public function checkData($nextStep = null, $options = array(), $validationMethod = null, $message = null, $sessionKey = null) {
		if (!is_array($options)) {
			$options = array('models' => $options);
		}
		$options += $this->_filter(compact('validationMethod', 'message', 'sessionKey'));

		$defaults = array(
			'message' => $this->messages['invalid'],
			'models' => $this->models,
			'validationMethod' => $this->validationMethod,
			'sessionKey' => $this->Controller->request->action,
		);
		extract($options = array_merge($defaults, $options));

		$models = $this->autoLoadModels($models);

		if ($this->Controller->request->is('post') || $this->Controller->request->is('put')) {

			$this->setData($sessionKey, (array)$this->Controller->request->data);

			if (is_array($models)) {
				$result = true;
				foreach ($models as $model) {
					if (!$this->validateModel($model, $validationMethod)) {
						$result = false;
					}
				}
			} else {
				$result = $this->validateModel($models, $validationMethod);
			}

			if ($result) {
				if ($nextStep !== null && $this->autoRedirect) {
					$nextStep = !is_array($nextStep) ? array('action' => $nextStep) : $nextStep;
					return $this->Controller->redirect($nextStep);
				}
			} else {
				if ($message !== false) {
					CakeSession::write('Message.' . $this->flashParams['key'], array(
						'message' => $message,
						'element' => $this->flashParams['element'],
						'params' => $this->flashParams['params']
					));
				}
				return false;
			}
		} elseif ($this->autoComplete && CakeSession::check($this->sessionKey($sessionKey))) {
			$this->Controller->request->data = $this->data($sessionKey);
		}

		return true;
	}

/**
 * Set session data with key, and redirect
 *
 * @param mixed $url A string or array-based URL pointing to another location within the app,
 *       or an absolute URL
 * @param integer $status Optional HTTP status code (eg: 404)
 * @param boolean $exit If true, exit() will be called after the redirect
 */
	public function redirect($url, $status = null, $exit = true) {
		$sessionKey = $this->Controller->action;
		$this->setData($sessionKey, array());
		return $this->Controller->redirect($url, $status, $exit);
	}

/**
 * Validation with model name.
 *
 * @param mixed $model Model for validation
 * @param callback $validationMethod Method to validate
 * @return boolean Success
 */
	public function validateModel($model, $validationMethod = null) {
		if ($validationMethod === null) {
			$validationMethod = $this->validationMethod;
		}

	/**
	 * Loading Model object.
	 */
		if (!is_object($model) && $model !== null) {
			$modelName = Inflector::classify($model);

			if (isset($this->Controller->{$modelName})) {
				$model = $this->Controller->{$modelName};
			} elseif (isset($this->Controller->{$this->Controller->modelClass}->{$modelName})) {
				$model = $this->Controller->{$this->Controller->modelClass}->{$modelName};
			} else {
				$model = ClassRegistry::init($modelName);
			}

		}

		$result = true;

		// User method.
		if ($validationMethod !== null) {
			$isModelMethod = is_array($validationMethod) && is_object(current($validationMethod)) && current($validationMethod) instanceof Model;

			if ($isModelMethod || $model === null) {
				$result = call_user_func($validationMethod, $this->Controller->request->data);
			} else {
				$result = call_user_func($validationMethod, $model, $this->Controller->request->data);
			}
		}

		if (!empty($this->Controller->request->data) && is_object($model) && $model instanceof Model) {
			$model->set($this->Controller->request->data);
			if (!$model->validates()) {
				$result = false;
			}
		}

		return $result;
	}

/**
 * Loading Default/UserSetting Model names.
 * Given $models as null, try to load model name from Controller::modelClass
 *
 * @param mixed $models a name or array of names
 * @return mixed Session data or null
 */
	public function autoLoadModels($models) {
		if ($models === null) {
			if (!empty($this->models)) {
				return $this->models;
			}

			$c = $this->Controller;
			if ($c->modelClass !== null && $c->{$c->modelClass}) {
				$models = $c->modelClass;
			}

		} elseif ($models === false) {
			$models = null;
		}

		if ($models !== null && !is_array($models)) {
			$models = array($models);
		}

		return $this->models = $models;
	}

/**
 * Get session data from key.
 *
 * @param string $key Key name
 * @return mixed Session data or null
 */
	public function data($key) {
		$key = $this->sessionKey($key);
		if (CakeSession::check($key)) {
			return CakeSession::read($key);
		}

		return null;
	}

/**
 * Set session data with key.
 *
 * @param string $key Key name
 * @param mixed $data data to set
 * @return boolean Success
 */
	public function setData($key, $data) {
		return CakeSession::write($this->sessionKey($key), $data);
	}

/**
 * Get all of session data.
 *
 * @return mixed Session data or null
 */
	public function allData() {
		return CakeSession::read($this->sessionBaseKey);
	}

/**
 * Get merged session data.
 *
 * @param string $callback Callback method to merging. valid callback type or Sring like "Hash::merge" can be accepted.(optional)
 * @return mixed Merged session data or null
 */
	public function mergedData($callback = 'Hash::merge') {
		$allData = $this->allData();
		if (empty($allData)) {
			return $allData;
		}

		if (!is_array($callback)) {
			list($class, $func) = explode('::', $callback . '::');
			$callback = !empty($func) ? array(
				$class,
				$func
			) : $class;
		}

		$merged = array();
		foreach ($allData as $actions) {
			foreach ($actions as $data) {
				$merged = call_user_func($callback, $merged, $data);
			}
		}

		return $merged;
	}

/**
 * Get Session key.
 *
 * @param mixed $key Key name or url parameter array
 * @param string $controllerName controller name(optional)
 * @return string Session key
 */
	public function sessionKey($key, $controllerName = null) {
		$action = null;
		if (is_array($key)) {
			$controllerName = Hash::get($key, 'controller');
			$action = Hash::get($key, 'action');
		} else if (is_string($key)) {
			$action = $key;
		}

		if ($controllerName === null) {
			$method = $this->controllerInflection;
			$controllerName = Inflector::$method($this->Controller->name);
		}
		$keys = array(
			$this->sessionBaseKey,
			$controllerName
		);
		if ($action !== null) {
			$keys[] = $action;
		}
		return implode('.', $keys);
	}

/**
 * Delete Session data from key.
 *
 * @param string $key Key name
 * @return boolean Success
 */
	public function deleteData($key) {
		$key = $this->sessionKey($key);
		return CakeSession::delete($key);
	}

/**
 * Alias for deleteData().
 *
 * @param string $key Key name
 * @return boolean Success
 */
	public function delData($key) {
		return $this->deleteData($key);
	}

/**
 * Clear Session data.
 *
 * @param string $key Key name
 * @return boolean Success
 */
	public function clearData() {
		if (CakeSession::check($this->sessionBaseKey)) {
			return CakeSession::delete($this->sessionBaseKey);
		}

		return true;
	}

/**
 * Check current step if `prev`
 *
 * @return bool
 */
	public function isPrevStep() {
		return $this->step === static::PREF;
	}

/**
 * Check current step if `nextstep`
 *
 * @return bool
 */
	public function isNextStep() {
		return $this->step === static::NEXT;
	}

}
