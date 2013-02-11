<?php

App::uses('Controller', 'Controller');
App::uses('Component', 'Controller');
App::uses('CakeSession', 'Model/Datasource');
App::uses('TransitionComponent', 'Transition.Controller/Component');
App::uses('Router', 'Routing');

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


if (!class_exists('RedirectException')) {
/**
 * @throws RedirectException
 */
	class RedirectException extends CakeException {
	}
}

class TransitionComponentTestController extends Controller {

	public $name = 'TransitionComponentTest';

	public $components = array('TransitionComponent.Transition');

	public $uses = array('TransitionModel');

	public function redirect($url, $status = null, $exit = true) {
		throw new RedirectException(Router::url($url));
	}

}

class TransitionComponentAppModelController extends TransitionComponentTestController {

	public $name = 'TransitionComponentAppModel';

	public $uses = array('TransitionPost');

}

class TransitionModelBase extends CakeTestModel {

	public $useTable = false;

	public $validationSuccess = true;

	public function validates($options = array()) {
		return $this->validationSuccess;
	}

}

class TransitionModel extends TransitionModelBase {

	public $name = 'TransitionModel';
}

class ValidationSuccess extends TransitionModelBase {

	public $name = 'ValidationSuccess';
}

class ValidationFail extends TransitionModelBase {

	public $name = 'ValidationFail';

	public $validationSuccess = false;

}

class NormalValidation extends CakeTestModel {

	public $name = 'NormalValidation';

	public $useTable = false;

	public $validate = array(
		'max25char' => array(
			'rule' => array(
				'maxLength',
				25
			)
		)
	);

	public function triggerError($data) {
		foreach ($data[$this->name] as $key => $val) {
			$this->invalidate($key, $val);
		}

		return false;
	}

}

class ObjectValidation {

	public function validates($options = array()) {
		return true;
	}

}

if (!function_exists('validationFail')) {

	function validationFail() {
		return false;
	}

}

if (!function_exists('validationSuccess')) {

	function validationSuccess() {
		return true;
	}

}

class TransitionComponentTest extends CakeTestCase {

	public $Controller = null;

	public $fixtures = array('plugin.TransitionComponent.transition_post');

	public $sessionBaseKey = '';

	protected $_CRConfig;

	protected $_server;

/**
 * reset environment.
 *
 * @return void
 */
	public function setUp() {
		App::objects('plugin', null, false);
		App::build();
		Router::reload();

		$this->_CRConfig = ClassRegistry::config('Model');
		ClassRegistry::config('Model', array('table' => false));
		$this->_server = $_SERVER;
		parent::setUp();
	}

/**
 * teardown
 *
 * @access public
 * @return void
 */
	public function teardown() {
		App::build();

		ClassRegistry::config('Model', $this->_CRConfig);
		$_SERVER = $this->_server;
	}

	public function startTest($method = null) {
		parent::startTest($method);
		$this->__loadController();
		$_SERVER['REQUEST_METHOD'] = 'POST';
	}

	public function endTest($method = null) {
		$this->__shutdownController();
		parent::endTest($method);
	}

	private function __loadController($params = array()) {
		if ($this->Controller !== null) {
			$this->__shutdownController();
			unset($this->Controller);
		}

		$controllerName = 'Test';
		if (!empty($params['controller'])) {
			$controllerName = $params['controller'];
			unset($params['controller']);
		}

		$Request = new CakeRequest(null, false);
		$Request->addParams(array(
			'controller' => $controllerName,
			'action' => 'test_action',
		))->addParams($params);

		$controllerName = 'TransitionComponent' . $controllerName . 'Controller';
		$Controller = new $controllerName($Request);
		$Controller->constructClasses();
		$Controller->Components->trigger('initialize', array($Controller));
		$this->Controller = $Controller;

		$this->sessionBaseKey = "Transition." . Inflector::underscore($Controller->name);
	}

	private function __shutdownController() {
		CakeSession::delete($this->sessionBaseKey);
		CakeSession::delete('Message');
		$this->Controller->shutdownProcess();
	}

	public function testSetStep() {
		$c = $this->Controller;
		$t = $c->Transition;

		// case
		$c->request->data = array();
		$t->step = null;
		$t->stepValue = null;
		$t->setStep();
		$this->assertIdentical($t->step, null);
		$this->assertIdentical($t->stepValue, null);

		// case
		$c->request->data = array(
			'Transition' => '',
		);
		$t->step = null;
		$t->stepValue = null;
		$this->assertIdentical($t->step, null);
		$this->assertIdentical($t->stepValue, null);
		$this->assertIdentical($c->request->data, array('Transition' => ''));

		// case
		$c->request->data = array(
			'Transition' => array('prev' => 'prev_value'),
			// nextStep
		);
		$t->step = null;
		$t->stepValue = null;
		$t->setStep();
		$this->assertIdentical($t->step, TransitionComponent::PREF);
		$this->assertIdentical($t->stepValue, 'prev_value');
		$this->assertIdentical($c->request->data, array());

		// case
		$c->request->data = array(
			'Transition' => array('nextStep' => 'nextStep'),
		);
		$t->step = null;
		$t->stepValue = null;
		$t->setStep();
		$this->assertIdentical($t->step, TransitionComponent::NEXTSTEP);
		$this->assertIdentical($t->stepValue, 'nextStep');
		$this->assertIdentical($c->request->data, array());

		// case
		$c->request->data = array(
			'Transition' => array('invalid_key' => 'nextStep'),
		);
		$t->step = null;
		$t->stepValue = null;
		$t->setStep();
		$this->assertIdentical($t->step, null);
		$this->assertIdentical($t->stepValue, null);
		$this->assertIdentical($c->request->data, array('Transition' => array('invalid_key' => 'nextStep')));

		// case
		$c->request->data = array(
			'Transition' => array('nextStep' => 'invalid_value'),
		);
		$t->step = null;
		$t->stepValue = null;
		$t->setStep();
		$this->assertIdentical($t->step, TransitionComponent::NEXTSTEP);
		$this->assertIdentical($t->stepValue, 'invalid_value');
		$this->assertIdentical($c->request->data, array());

		// case
		$c->request->data = array(
			'Transition' => array('nextStep' => ''),
		);
		$t->step = null;
		$t->stepValue = null;
		$t->setStep();
		$this->assertIdentical($t->step, TransitionComponent::NEXTSTEP);
		$this->assertIdentical($t->stepValue, '');
		$this->assertIdentical($c->request->data, array());
	}

	public function testStartup() {
		$c = $this->Controller;
		$t = $c->Transition;

		$t->automation = true;
		$this->assertTrue($t->startup($c));
		$t->automation = array();
		$this->assertTrue($t->startup($c));
		$t->automation = array('test_action' => array());
		$this->assertTrue($t->startup($c));
		$t->automation = array('test_action' => array('prev' => 'prev_action'));

		try {
			$t->startup($c);
		} catch (RedirectException $expected) {
			$this->assertEqual(CakeSession::read('Message.flash.message'), $t->messages['prev']);
			$this->assertEqual($expected->getMessage(), '/prev_action');
			return;
		}
		$this->fail('An expected exception has not been raised.');
	}

	public function testAutomate() {
		$this->__loadController();
		$c = $this->Controller;
		$t = $c->Transition;

		$c->request->data = array('dummy');

		$this->assertTrue($t->automate(null, null));

		try {
			$t->automate('prev_action', null);
			$this->fail('An expected exception has not been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/prev_action');
		}

		$this->assertFalse($t->automate(null, 'next_action', 'ValidationFail'));

		try {
			$t->automate('prev_action', 'next_action', 'ValidationFail');
			$this->fail('An expected exception has not been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/prev_action');
		}

		CakeSession::destroy();
		$t->setData('prev_action', 'dummy');
		$this->assertTrue($t->automate('prev_action', null));
		$this->assertFalse($t->automate('prev_action', 'next_action', 'ValidationFail'));
		try {
			$t->automate('prev_action', 'next_action', 'ValidationSuccess');
			$this->fail('An expected exception has not been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/next_action');
		}

		$c->request->data = array('NormalValidation' => array('max25char' => 'this will be handled as invalid'));
		$NormalValidation = ClassRegistry::init('NormalValidation');
		try {
			$result = $t->automate('prev_action', 'next_action', 'ValidationSuccess', array(
				$NormalValidation,
				'triggerError'
			), array('invalid' => 'validation failed'));
			$this->assertFalse($result);
			$this->assertEqual(CakeSession::read('Message.flash.message'), 'validation failed');
			$this->assertFalse(empty($NormalValidation->validationErrors));
		} catch (Exception $expected) {
			$this->fail('An unexpected exception has been raised.');
		}

		$result = $t->automate('prev_action', 'next_action', null, array(
			$NormalValidation,
			'triggerError'
		));
		$this->assertFalse($result);

		$t->clearData();

		try {
			$t->automate('prev_action', 'next_action', 'ValidationSuccess', null, array('prev' => 'no previous'));
			$this->fail('An expected exception has not been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/prev_action');
			$this->assertEqual(CakeSession::read('Message.flash.message'), 'no previous');
		}

		$t->clearData();
		$t->setData('prev_action', 'dummy');
		$result = $t->automate('prev_action', 'next_action', array(
			'models' => 'ValidationSuccess',
			'validationMethod' => array(
				new ValidationFail,
				'validates'
			),
		));
		$this->assertFalse($result);

		// test case
		CakeSession::destroy();
		$t->autoRedirect = false;
		try {
			$result = $t->automate('prev_action', 'next_action', 'ValidationSuccess');
			$this->assertFalse($result);
		} catch (RedirectException $expected) {
			$this->fail('An unexpected exception has been raised.');
		}
		$t->autoRedirect = true;

		// test case
		CakeSession::destroy();
		try {
			$t->automate('prev_action', 'next_action', null);
			$this->fail('An expected exception has not been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/prev_action');
		}
	}

	public function testCheckPrev() {
		$c = $this->Controller;
		$t = $c->Transition;

		try {
			$t->checkPrev('unknown');
			$this->fail('An expected exception has not been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/unknown');
		}

		$t->setData('prev_action', 'dummy');
		$result = $t->checkPrev('prev_action');
		$this->assertTrue($result);

		try {
			$t->checkPrev(array(
				'prev_action',
				'unknown'
			));
			$this->fail('An expected exception has not been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/unknown');
		}

		$t->setData('old_action', 'horoharahirehare-');
		$result = $t->checkPrev(array(
			'prev_action',
			'old_action'
		));
		$this->assertTrue($result);

		CakeSession::delete('Message.flash.message');
		try {
			$t->checkPrev('unknown', 'no prev');
			$this->fail('An expected exception has not been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/unknown');
			$this->assertEqual(CakeSession::read('Message.flash.message'), 'no prev');
		}

		try {
			$t->checkPrev('unknown', null, 'index');
			$this->fail('An expected exception has not been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/index');
		}

		$t->setData('current_controller', 'dummy');
		$t->setData('current_controller2', 'dummy');
		$t->setData(array(
			'controller' => 'others',
			'action' => 'other_controller'
		), 'dummy');

		$toCheck = array(
			'current_controller',
			'current_controller2',
			array(
				'controller' => 'others',
				'action' => 'other_controller'
			),
		);

		try {
			$this->assertTrue($t->checkPrev($toCheck));
		} catch (RedirectException $expected) {
			$this->fail('An unexpected exception has been raised.');
		}

		try {
			$toCheck[1] = 'not_exists';
			$t->checkPrev($toCheck);
			$this->fail('An unexpected exception has been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/not_exists');
		}

		// test case
		try {
			$t->checkPrev(array('unknown'));
			$this->fail('An expected exception has not been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/unknown');
		}
		CakeSession::destroy();
		$t->autoRedirect = false;
		try {
			$t->checkPrev(array(
				'prev_action',
				'unknown'
			), 'next_action', null);
		} catch (RedirectException $expected) {
			$this->fail('An expected exception has not been raised.');
			$this->assertEqual($expected->getMessage(), '/prev_action');
		}
	}

	public function testCheckData() {
		$c = $this->Controller;
		$t = $c->Transition;
		$c->request->data = array();

		$this->assertTrue($t->checkData());

		$c->request->data = array('TransitionModel' => array('dummy' => 2));
		$this->assertTrue($t->checkData(null, false));
		try {
			$t->checkData(array(
				'controller' => 'tests',
				'action' => 'next_action'
			));
			$this->fail('An unexpected exception has been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/tests/next_action');
			$this->assertIdentical($t->data('test_action'), $c->request->data);
		}

		$t->clearData();
		$t->autoRedirect = false;

		try {
			$t->checkData(array(
				'controller' => 'tests',
				'action' => 'next_action'
			));
		} catch (RedirectException $expected) {
			$this->fail('An unexpected exception has been raised.');
		}

		$t->clearData();
		CakeSession::delete('Message');
		$c->request->data = array('NormalValidation' => array('max25char' => 'This column will be failed because of too long string'));

		$t->checkData(null, 'NormalValidation', null, 'validation was fail');
		$this->assertEqual(CakeSession::read('Message.flash.message'), 'validation was fail');
		$this->assertIdentical($t->data('test_action'), $c->request->data);

		$c->request->data = null;
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$t->setData('test_action', 'test_data');
		$t->checkData();
		$this->assertEqual($c->request->data, 'test_data');
	}

	public function testRedirect() {
		$this->__loadController(array('action' => 'current_action'));
		$c = $this->Controller;
		$t = $c->Transition;

		$c->request->data = array();

		try {
			$t->redirect(array('action' => 'next_action'));
			$this->fail('An unexpected exception has been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/next_action');
		}
		try {
			$this->assertTrue($t->checkPrev(array('current_action')));
		} catch (RedirectException $expected) {
			$this->fail('An unexpected exception has been raised.');
		}

		$c->action = 'next_action';
		$c->request->data = array();

		try {
			$t->redirect(array('action' => 'next_next_action'));
			$this->fail('An unexpected exception has been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/next_next_action');
		}
		try {
			$this->assertTrue($t->checkPrev(array(
				'current_action',
				'next_action'
			)));
		} catch (RedirectException $expected) {
			$this->fail('An unexpected exception has been raised.');
		}

		$c->action = 'next_next_action';
		$c->request->data = array();
		try {
			$t->redirect(array('action' => 'next_next_action'));
			$this->fail('An unexpected exception has been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/next_next_action');
		}
		try {
			$this->assertTrue($t->checkPrev(array(
				'current_action',
				'next_action',
				'next_next_action'
			)));
		} catch (RedirectException $expected) {
			$this->fail('An unexpected exception has been raised.');
		}
	}

	public function testValidateModel() {
		$this->__loadController();
		$c = $this->Controller;
		$t = $c->Transition;

		$c->request->data = array($c->modelClass => array('dummy' => 2));

		$Success = ClassRegistry::init('ValidationSuccess');
		$Fail = ClassRegistry::init('ValidationFail');
		$NormalValidation = ClassRegistry::init('NormalValidation');

		$this->assertTrue($t->validateModel('NotExistModel'));

		$this->assertTrue($t->validateModel('ValidationSuccess'));
		$this->assertFalse($t->validateModel('ValidationFail'));
		$this->assertTrue($t->validateModel(null, 'validationSuccess'));
		$this->assertFalse($t->validateModel(null, 'validationFail'));
		$this->assertTrue($t->validateModel(null));

		$c->TransitionModel->bindModel(array('belongsTo' => array('AssociatedModel')));
		$this->assertTrue($t->validateModel('AssociatedModel'));

		$this->assertTrue($t->validateModel($Success));
		$this->assertFalse($t->validateModel($Fail));
		$this->assertTrue($t->validateModel(null, array(
			$Success,
			'validates'
		)));
		$this->assertFalse($t->validateModel(null, array(
			$Fail,
			'validates'
		)));

		$this->assertFalse($t->validateModel('ValidationSuccess', array(
			$Fail,
			'validates'
		)));
		$this->assertFalse($t->validateModel('ValidationFail', array(
			$Success,
			'validates'
		)));
		$this->assertFalse($t->validateModel($Success, array(
			$Fail,
			'validates'
		)));
		$this->assertTrue($t->validateModel($Success, array(
			$Success,
			'validates'
		)));

		$this->assertTrue($t->validateModel($Success, array(
			new ObjectValidation,
			'validates'
		)));

		$c->request->data = array('NormalValidation' => array('max25char' => 'This column will be failed because of too long string'));
		$t->validateModel('NormalValidation');
		$this->assertFalse(empty($NormalValidation->validationErrors));

		$NormalValidation->create(false);
		$t->validateModel($NormalValidation);
		$this->assertFalse(empty($NormalValidation->validationErrors));

		$NormalValidation->create(false);
		$c->request->data = array('NormalValidation' => array('maxchar25' => 'this column will be pass'));
		$t->validateModel($NormalValidation);
		$this->assertTrue(empty($NormalValidation->validationErrors));

		$this->__loadController(array('controller' => 'AppModel'));
		$c = $this->Controller;
		$t = $c->Transition;
		$c->request->data = array('NormalValidation' => array('max25char' => 'This column will be failed because of too long string'));

		$this->assertTrue($t->validateModel('TransitionPost'));

		$TransitionPost = ClassRegistry::init('TransitionPost');
		$result = $t->validateModel('NormalValidation', array(
			$TransitionPost,
			'validates'
		));

		$this->assertFalse($result);
		$this->assertFalse(empty($NormalValidation->validationErrors));
	}

	public function testAutoLoadModels() {
		$this->__loadController();
		$c = $this->Controller;
		$t = $c->Transition;

		$this->assertIdentical($t->autoLoadModels(null), array('TransitionModel'));
		$this->assertIdentical($t->autoLoadModels(false), null);
		$this->assertIdentical($t->autoLoadModels('Model'), array('Model'));
		$this->assertIdentical($t->autoLoadModels(array('Model')), array('Model'));
		$this->assertIdentical($t->autoLoadModels(array(
			'Model1',
			'Model2',
			'Model3'
		)), array(
			'Model1',
			'Model2',
			'Model3'
		));

		$this->assertIdentical($t->autoLoadModels(null), array(
			'Model1',
			'Model2',
			'Model3'
		));

		$object = new Object();
		$this->assertIdentical($t->autoLoadModels($object), array($object));
	}

	public function testSessionKey() {
		$this->__loadController();
		$c = $this->Controller;
		$t = $c->Transition;

		$this->assertEqual($t->sessionKey(null), $this->sessionBaseKey);
		$this->assertEqual($t->sessionKey('my_key'), 'Transition.transition_component_test.my_key');
		$this->assertEqual($t->sessionKey('my_key', 'my_controller_key'), 'Transition.my_controller_key.my_key');
		$this->assertEqual($t->sessionKey(array('controller' => 'my_controller')), 'Transition.my_controller');
		$this->assertEqual($t->sessionKey(array('controller' => 'MyController')), 'Transition.MyController');
		$this->assertEqual($t->sessionKey(array('controller' => '')), 'Transition.');
		$this->assertEqual($t->sessionKey(array('controller' => ' ')), 'Transition. ');
		$this->assertEqual($t->sessionKey(array(
			'controller' => 'my_controller',
			'action' => 'my_action'
		)), 'Transition.my_controller.my_action');

		$t->sessionBaseKey = 'my_sessionkey';
		$this->assertEqual($t->sessionKey(null), 'my_sessionkey.transition_component_test');

		$c->name = 'my_controller';
		$result = $t->sessionKey(null);
		$this->assertEqual($result, 'my_sessionkey.my_controller');

		$t->sessionBaseKey = 'Transition';
		$c->name = 'TransitionComponentTest';
	}

	public function testDataMethods() {
		$this->__loadController();
		$c = $this->Controller;
		$t = $c->Transition;

		$t->setData('param1', array('testdata' => 'hoge'));
		$expected = array('testdata' => 'hoge');

		$this->assertEqual(CakeSession::read($this->sessionBaseKey . '.param1'), $expected);
		$this->assertEqual($t->data('param1'), $expected);
		$this->assertEqual($t->data('param2'), null);
		$this->assertEqual($t->allData(), array('transition_component_test' => array('param1' => $expected)));

		$this->assertTrue($t->setData('param2', array(
			'User' => array(
				'id' => 1,
				'name' => 'user1',
				'age' => 46
			)
		)));
		$this->assertTrue($t->setData('param3', array(
			'User' => array(
				'id' => 2,
				'name' => 'user2'
			)
		)));

		$expected = array(
			'testdata' => 'hoge',
			'User' => array(
				'id' => 2,
				'name' => 'user2',
				'age' => 46
			)
		);
		$this->assertEqual($t->mergedData(), $expected);
		$this->assertEqual($t->mergedData(array(
			'Set',
			'merge'
		)), $expected);
		$expected = array(
			'testdata' => 'hoge',
			'User' => array(
				'id' => 1,
				'name' => 'user1',
				'age' => 46
			)
		);
		$this->assertEqual($t->mergedData('Set::pushDiff'), $expected);
		$this->assertEqual($t->mergedData(array(
			'Set',
			'pushDiff'
		)), $expected);
		$expected = array(
			'testdata' => 'hoge',
			'User' => array(
				'id' => array(
					1,
					2
				),
				'name' => array(
					'user1',
					'user2'
				),
				'age' => 46
			)
		);
		$this->assertEqual($t->mergedData('array_merge_recursive'), $expected);

		$this->assertTrue($t->deleteData('param2'));
		$this->assertFalse(CakeSession::check($this->sessionBaseKey . '.param2'));
		$this->assertNull($t->data('param2'));

		$this->assertTrue($t->clearData());
		$this->assertFalse(CakeSession::check($this->sessionBaseKey . '.param1'));
		$this->assertFalse(CakeSession::check($this->sessionBaseKey . '.param3'));
		$this->assertNull($t->mergedData());
		$this->assertTrue($t->clearData());
		$this->assertFalse($t->delData(null));
		$this->assertFalse($t->deleteData('param2'));
	}

	public function testChangeActionByController() {
		$c = $this->Controller;
		$t = $c->Transition;

		$c->params['action'] = 'mobile_index';
		$t->initialize($c);
		$c->beforeFilter();
		$c->params['action'] = 'index';
		$t->startup($c);
		$c->request->data = 'dummy';

		try {
			$t->checkData('next');
			$this->fail('An unexpected exception has been raised.');
		} catch (RedirectException $expected) {
			$this->assertEqual($expected->getMessage(), '/next');
		}

		$check = $t->allData();
		$this->assertEqual(array('index'), array_keys($check['transition_component_test']));
		$this->assertNotEqual(array('mobile_index'), array_keys($check['transition_component_test']));
	}

}