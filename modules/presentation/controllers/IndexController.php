<?php
use STS\Core\Api\ApiException;
use STS\Domain\School\Specification\MemberSchoolSpecification;
use STS\Domain\Presentation;
use STS\Core;
use STS\Web\Controller\SecureBaseController;

class Presentation_IndexController extends SecureBaseController
{

    private $core;
    private $user;
    public function init()
    {
        parent::init();
        $this->core = Core::getDefaultInstance();
        $this->user = $this->getAuth()->getIdentity();
    }
    public function newAction()
    {
        $this->view->form = $this->getForm();
        $request = $this->getRequest();
        $form = $this->getForm();
        if ($this->getRequest()->isPost()) {
            $postData = $request->getPost();
            if (!array_key_exists('membersAttended', $postData) || !is_array($postData['membersAttended'])) {
                $form->getElement('membersAttended[]')
                    ->addErrors(array(
                        'Please enter at least one member.'
                    ))->markAsError();
                $membersValid = false;
            } else {
                $this->view->storedMembers = $postData['membersAttended'];
                $membersValid = true;
            }
            if ($form->isValid($postData) && $membersValid) {
                try {
                    $this->savePresentation($postData);
                    $this
                        ->setFlashMessageAndRedirect('You have successfully completed the presentation and survey entry process!', 'success', array(
                            'module' => 'main', 'controller' => 'home', 'action' => 'index'
                        ));
                } catch (ApiException $e) {
                    $this
                        ->setFlashMessageAndUpdateLayout('An error occured while saving this information: '
                                        . $e->getMessage(), 'error');
                }
            } else {
                $this
                    ->setFlashMessageAndUpdateLayout('It looks like you missed some information, please make the corrections below.', 'error');
            }
        }
        $this->view->form = $form;
    }
    private function getForm()
    {
        $schools = $this->getSchoolsVisableToMember();
        $schoolsArray = array(
            ''
        );
        foreach ($schools as $school) {
            $schoolsArray[$school->getId()] = $school->getName();
        }
        $typesArray = array_merge(array(
            ''
        ), Presentation::getTypes());
        $surveyFacade = $this->core->load('SurveyFacade');
        $surveyTemplate = $surveyFacade->getSurveyTemplate(1);
        $form = new \Presentation_Presentation(
                        array(
                                'schools' => $schoolsArray, 'presentationTypes' => $typesArray,
                                'surveyTemplate' => $surveyTemplate
                        ));
        return $form;
    }
    private function getSchoolsVisableToMember()
    {
        $schoolFacade = $this->core->load('SchoolFacade');
        $schoolSpec = null;
        if ($this->user->getAssociatedMemberId()) {
            $memberFacade = $this->core->load('MemberFacade');
            $schoolSpec = $memberFacade->getMemberSchoolSpecForId($this->user->getAssociatedMemberId());
        }
        return $schoolFacade->getSchoolsForSpecification($schoolSpec);
    }
    private function savePresentation($postData)
    {
        //Get User
        $userId = $this->auth->getIdentity()->getId();
        $templateId = 1;
        //First Save Survey Built
        $surveyFacade = $this->core->load('SurveyFacade');
        $surveyData = array();
        foreach ($postData as $key => $value) {
            if (substr($key, 0, 2) == 'q_') {
                $surveyData[$key] = $value;
            }
        }
        $surveyId = $surveyFacade->saveSurvey($userId, $templateId, $surveyData);
        //Then Save Presentation
        $presentationFacade = $this->core->load('PresentationFacade');
        $members = array_keys($postData['membersAttended']);
        $presentationFacade
            ->savePresentation($userId, $postData['location'], $postData['presentationType'], $postData['dateOfPresentation'], $postData['notes'], $members, $postData['participants'], $postData['formsReturned'], $surveyId);
        return true;
    }
}
