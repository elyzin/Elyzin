<?php

namespace Elyzin\Controller;

use Security;
use Config;
use Markup;
use File;

class Control extends App
{
	// Control Panel Dashboard
	function default() {
		foreach (STAKE as $stake) {
			$stats['total_'.$stake] = ($this->dbase->table($stake)->count('id', 'total_'.$stake)->get())->{'total_'.$stake};
		}

		$this->view->render('control.dashboard', $stats)->set();
	}

	function settings() {
		// Define boolean inputs
		$bools = ['debug', 'min_asset', 'error_log', 'error_mail', 'error_screen'];
		$settings = array_change_key_case(Config::fetch('env'), CASE_LOWER);

		if(defined('POST')){
			// Validate form submission
		
			foreach ($bools as $bool) {
				$_POST[$bool] = isset($_POST[$bool]) ? true : false;
			}
			$new_settings = array_intersect_key(array_merge($settings, $_POST), $settings);
			
			if($settings == $new_settings) {
				$this->view->message('settingsnochange', 'info');
			} else {
				$change = 0;
				foreach ($settings as $key => $value) {
					if($settings[$key] != $new_settings[$key]) $change++;
				}
				
				File::write(array_change_key_case($new_settings, CASE_UPPER), Config::path('env', false));
				$this->view->message('settingsupdated', 'success', [$change]);
			}
			$this->view->redirect('control/settings');
		}

		foreach ($bools as $bool) {
			$settings[$bool] = $settings[$bool] ? " checked" : "";
		}

		$settings['language'] = Markup::select([
			'name' => 'language',
			'values' => 'lang',
			'current' => $settings['language']
		]);
		$settings['theme'] = Markup::select([
			'name' => 'theme',
			'values' => ['base' => 'Base Theme'],
			'current' => $settings['theme']
		]);
		$settings['samesite_cookie'] = Markup::select([
			'name' => 'samesite_cookie',
			'values' => ['None'=>'None', 'Lax'=>'Lax', 'Strict'=>'Strict'],
			'current' => $settings['samesite_cookie']
		]);
		$settings['timezone'] = Markup::select([
			'name' => 'timezone',
			'values' => 'timezone',
			'current' => $settings['timezone'],
			'placeholder' => 'Select a timezone...',
		]);
		//$settings['env'] = json_encode($settings, JSON_PRETTY_PRINT);

		$this->view->render('control.settings', $settings)->set();
	}

	public function modify($stake, $id){
	}
	
	public function create($stake){
		$vars = [];
		
		$errors = [];
		if(defined('POST')){
			// Validate form submission
			foreach($_POST as $k => $v){
				$vars['new_'.$k] = $v; // SANITIZE
			}
			$errors = ["This is a test error.","This is another test error."];
		}

		switch ($stake) {
			case 'user':
				//$password = Security::makePass($_POST['password']);
				break;

			case 'organization':
				break;

			case 'project':
				break;
			
			default:
				# error...
				break;
		}

		$this->returnForm($stake, $vars, $errors);
	}

	private function returnForm(string $stake, array $data = [], array $errors = [])
	{
		if(!empty($errors)){
			$inline_error = "<ul>";
			foreach ($errors as $error){
				$inline_error .= '<li>'.$error.'</li>';
			}
			$inline_error .= "</ul>";
			$data['inline_error'] = $this->view->render('inline.error', ['error' => $inline_error])->get();
		}
		$this->view->render('form.'.$stake, $data)->set();
	}
}
