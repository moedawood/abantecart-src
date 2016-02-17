<?php
/*------------------------------------------------------------------------------
  $Id$

  AbanteCart, Ideal OpenSource Ecommerce Solution
  http://www.AbanteCart.com

  Copyright © 2011-2015 Belavier Commerce LLC

  This source file is subject to Open Software License (OSL 3.0)
  License details is bundled with this package in the file LICENSE.txt.
  It is also available at this URL:
  <http://www.opensource.org/licenses/OSL-3.0>

 UPGRADE NOTE:
   Do not edit or add to this file if you wish to upgrade AbanteCart to newer
   versions in the future. If you wish to customize AbanteCart for your
   needs please refer to http://www.AbanteCart.com for more information.
------------------------------------------------------------------------------*/
if (!defined('DIR_CORE') || !IS_ADMIN) {
	header('Location: static_pages/');
}

if (defined('IS_DEMO') && IS_DEMO) {
	header('Location: static_pages/demo_mode.php');
}

/**
 * Class ControllerResponsesSaleContact
 * @property ModelSaleContact $model_sale_contact
 */
class ControllerResponsesSaleContact extends AController {
	public $data = array();
	public $errors = array();

	public function buildTask(){
		$this->data['output'] = array();
		//init controller data
		$this->extensions->hk_InitData($this,__FUNCTION__);

		if ($this->request->is_POST() && $this->_validate()) {
			$this->loadModel('sale/contact');
			$task_details = $this->model_sale_contact->createTask('send_now', $this->request->post);

			if(!$task_details){
				$this->errors = array_merge($this->errors,$this->model_sale_contact->errors);
				$error = new AError("Mail/Notification Sending Error: \n ".implode(' ', $this->errors));
				return $error->toJSONResponse('APP_ERROR_402',
										array( 'error_text' => implode(' ', $this->errors),
												'reset_value' => true
										));
			}else{
				$this->data['output']['task_details'] = $task_details;
			//	$this->data['output']['task_details']['backup_name'] = "manual_backup_".date('Ymd_His');
			}

		}else{
			$error = new AError(implode('<br>', $this->errors));
			return $error->toJSONResponse('VALIDATION_ERROR_406',
									array( 'error_text' => implode('<br>', $this->errors),
											'reset_value' => true
									));
		}

		//update controller data
    	$this->extensions->hk_UpdateData($this,__FUNCTION__);

		$this->load->library('json');
		$this->response->addJSONHeader();
		$this->response->setOutput( AJson::encode($this->data['output']) );

	}

	public function complete(){
		//init controller data
		$this->extensions->hk_InitData($this,__FUNCTION__);

		$task_id = (int)$this->request->post['task_id'];
		if(!$task_id){
			return null;
		}

		//check task result
		$tm = new ATaskManager();
		$task_info = $tm->getTaskById($task_id);
		$task_result = $task_info['last_result'];
		if($task_result){
			$tm->deleteTask($task_id);
			$result_text = 'Messages was sent successfully';
			if(has_value($this->session->data['sale_contact_presave'])){
				unset($this->session->data['sale_contact_presave']);
			}
		}else{
			$result_text = 'Some errors occured during task process. Please see log for details or restart this task.';
		}




		//update controller data
        $this->extensions->hk_UpdateData($this,__FUNCTION__);

		$this->load->library('json');
		$this->response->addJSONHeader();
		$this->response->setOutput( AJson::encode(array(
													'result' => $task_result,
													'result_text' => $result_text ))
		);
	}

	public function abort(){
		//init controller data
		$this->extensions->hk_InitData($this,__FUNCTION__);

		$task_id = (int)$this->request->post['task_id'];
		if(!$task_id){
			return null;
		}

		//check task result
		$tm = new ATaskManager();
		$task_info = $tm->getTaskById($task_id);

		if($task_info['name']=='send_now'){
			$tm->deleteTask($task_id);
			$result_text = 'Task aborted successfully.';
		}else{
			$error_text = 'Task #'.$task_id.' not found!';
			$error = new AError($error_text);
			return $error->toJSONResponse('APP_ERROR_402',
						array( 'error_text' => $error_text,
								'reset_value' => true
						));
		}


		//update controller data
        $this->extensions->hk_UpdateData($this,__FUNCTION__);

		$this->load->library('json');
		$this->response->addJSONHeader();
		$this->response->setOutput( AJson::encode(array(
													'result' => true,
													'result_text' => $result_text ))
		);
	}


	public function restartTask(){
		$this->data['output'] = array();
		//init controller data
		$this->extensions->hk_InitData($this,__FUNCTION__);

		$task_id = (int)$this->request->get_or_post('task_id');

		if ($task_id) {
			$tm= new ATaskManager();

			$steps = $tm->getTaskSteps($task_id);
			foreach($steps as $step){
				if(!$step['settings']['to']){
					$tm->deleteStep($step['step_id']);
				}else{
					$tm->updateStep($step['step_id'], array ('status' => 1));
					$etas[$step['step_id']] = $step['max_execution_time'];
				}
			}

			$task_details = $tm->getTaskById($task_id);
			if(!$task_details || !$task_details['steps']){
				$error_text = "Mail/Notification Sending Error: Cannot to restart task #".$task_id;
				$error = new AError( $error_text );
				return $error->toJSONResponse('APP_ERROR_402',
										array( 'error_text' => $error_text,
												'reset_value' => true
										));
			}


			foreach ($etas as $step_id => $eta){
				$task_details['steps'][$step_id]['eta'] = $eta;
			}
			$this->data['output']['task_details'] = $task_details;

		}else{
			$error = new AError(implode('<br>', $this->errors));
			return $error->toJSONResponse('VALIDATION_ERROR_406',
									array( 'error_text' => 'Unknown task ID.',
											'reset_value' => true
									));
		}

		//update controller data
    	$this->extensions->hk_UpdateData($this,__FUNCTION__);

		$this->load->library('json');
		$this->response->addJSONHeader();
		$this->response->setOutput( AJson::encode($this->data['output']) );

	}


	public function presave(){
		//init controller data
		$this->extensions->hk_InitData($this,__FUNCTION__);

		$this->session->data['sale_contact_presave'] = array();
		$this->session->data['sale_contact_presave'] = $this->request->post;

		//update controller data
		$this->extensions->hk_UpdateData($this,__FUNCTION__);
	}

	public function incompleted(){
		//init controller data
		$this->extensions->hk_InitData($this,__FUNCTION__);
		$this->loadModel('user/user');
		$this->data = $this->language->getASet('sale/contact');

		$tm = new ATaskManager();
		$incompleted = $tm->getTasks(array(
				'filter' => array(
						'name' => 'send_now'
				)
		));

		$k = 0;
		foreach($incompleted as $incm_task){
			//show all incompleted tasks for Top Administrator user group
			if($this->user->getUserGroupId() != 1){
				if ($incm_task['starter'] != $this->user->getId()){
					continue;
				}
			}
			//define incompleted tasks by last time run
			$max_exec_time = (int)$incm_task['max_execution_time'];
			if(!$max_exec_time){
				//if no limitations for execution time for task - think it's 2 hours
				//$max_exec_time = 7200;
				$max_exec_time = 7200;
			}
			if( time() - dateISO2Int($incm_task['last_time_run']) > $max_exec_time ){

				//get some info about task, for ex message-text and subject
				$steps = $tm->getTaskSteps($incm_task['task_id']);
				if(!$steps){
					$tm->deleteTask($incm_task['task_id']);
				}
				$user_info = $this->model_user_user->getUser($incm_task['starter']);
				$incm_task['starter_name'] = $user_info['username']. ' '.$user_info['firstname']. ' '.$user_info['lastname'];
				$step = current($steps);
				$step_settings = $step['settings'];
				$incm_task['subject'] = $step_settings['subject'];
				$incm_task['message'] = mb_substr($step_settings['message'],0, 300);
				$incm_task['date_added'] = dateISO2Display($incm_task['date_added'], $this->language->get('date_format_short').' '.$this->language->get('time_format'));
				$incm_task['last_time_run'] = dateISO2Display($incm_task['last_time_run'], $this->language->get('date_format_short').' '.$this->language->get('time_format'));

				$this->data['tasks'][$k] = $incm_task;
			}

			$k++;
		}

		$this->data['restart_task_url'] = $this->html->getSecureURL('r/sale/contact/restartTask');
		$this->data['complete_task_url'] = $this->html->getSecureURL('r/sale/contact/complete');
		$this->data['abort_task_url'] = $this->html->getSecureURL('r/sale/contact/abort');

		$this->view->batchAssign($this->data);
		$this->processTemplate('responses/sale/contact_incompleted.tpl');
		//update controller data
		$this->extensions->hk_UpdateData($this,__FUNCTION__);


	}

	private function _validate() {
		if (!$this->user->canModify('sale/contact')) {
			$this->errors['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['subject']) {
			$this->errors['subject'] = $this->language->get('error_subject');
		}

		if (!$this->request->post['message']) {
			$this->errors['message'] = $this->language->get('error_message');
		}

		if (!$this->request->post['recipient'] && !$this->request->post['to'] && !$this->request->post['products']) {
			$this->errors['recipient'] = $this->language->get('error_recipients');
		}

		$this->extensions->hk_ValidateData( $this );

		if (!$this->errors) {
			return TRUE;
		} else {
			return FALSE;
		}
	}


}