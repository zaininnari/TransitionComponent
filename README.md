# Transition Component #

## Version ##

Origin version created by hiromi2424.
Forked https://github.com/hiromi2424/TransitionComponent

This was versioned as 2.2.0.

## Introduction ##

Transition component is a CakePHP component to help your transitional pages logic.

- For instance, this bears most wizard parts.
- In almost every case, your method for action can be one-liner as like following codes:
		public function action(){
			$this->Transition->automate('previous_action', 'next_action');
		}

## Requirements ##

- CakePHP >= 2.0
- PHP >= 5.2.8

Recommended:

- CakePHP >= 2.2
- PHP >= 5.3.2

## Setup ##

	cd /path/to/root/app/Plugin # or /path/to/root/plugins
	git clone git://github.com/zaininnari/TransitionComponent.git TransitionComponent

Or:

	cd /path/to/your_repository
	git submodule add git://github.com/zaininnari/TransitionComponent.git plugins/TransitionComponent

## Summary ##

- checkData() is to check data(if given) with model validation and auto redirecting
- checkPrev() is to check previous page's session data exists.
- automate() is convenient method for checkData() and checkPrev().

## Sample ##

1. Simple Wizard Form

		class UsersController extends AppController{

			public $components = array('Transition.Transition');

			// base of user information
			public function register() {

				// give a next action name
				$this->Transition->checkData('register_enquete');

			}

			// input enquete
			public function register_enquete() {

				$this->Transition->automate(
					'register', // previous action to check
					'register_confirm', // next action
					'Enquete' // model name to validate
				);

			}

			// confirm inputs
			public function register_confirm() {

				$this->Transition->automate(
					'register_enquete', // prev
					'register_save', // next
					array(
						'validationMethod' => 'validateCaptcha', // virtual function to validate with captcha
					)
				 );

				$this->set('data', $this->Transition->allData());
				$this->set('captcha', createCaptcha()); // virtual function to create a captcha

			}

			// stroring inputs
			public function register_save() {

				// As like this, multi action name can be accepted
				$this->Transition->checkPrev(array(
					'register',
					'register_enquete',
					'register_confirm'
				));

				// mergedData() returns all session data saved on the actions merged
				if ($this->User->saveAll($this->Transition->mergedData()) {

					// Clear all of session data TransitionComponent uses
					$this->Transition->clearData();
					$this->Session->setFlash(__('Registration complete !!', true));
					$this->redirect(array('action' => 'index'));

				} else {

					$this->Session->setFlash(__('Registration failed ...', true));
					$this->redirect(array('action' => 'register'));

				}

			}

		}


2. Transition among two Controllers

		class FirstController extends AppContoller {

			public $components = array('Transition.Transition');

			public function one() {
				$this->Transition->checkData(array('controller' => 'second', 'action' => 'two'));
			}

			public function three() {
				$this->Transition->checkPrev(array(
					'one',
					array('controller' => 'second', 'action' => 'two')
				));
			}

		}

		class SecondController extends AppContoller {

			public $components = array('Transition.Transition');

			public function two() {
				$this->Transition->automate(
					array('controller' => 'first', 'action' => 'one'),
					array('controller' => 'first', 'action' => 'three')
				);
			}
		}
