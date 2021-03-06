<?php
use STS\Web\Security\AclFactory;
use STS\Core;
use STS\Web\Controller\SecureBaseController;
use STS\Core\Api\ApiException;
use STS\Domain\Member;
use STS\Domain\User;
use STS\Core\Member\MemberDto;
use STS\Core\User\UserDTO;
use STS\Domain\Location\Area;
use STS\Domain\Location\Region;
use STS\Core\Api\DefaultPresentationFacade;

/**
 * Class Admin_MemberController
 *
 * @property STS\Core\Api\DefaultMemberFacade $memberFacade
 * @property STS\Core\Api\UserFacade $userFacade
 * @property STS\Core\Api\LocationFacade $locationFacade
 * @property STS\Core\Api\AuthFacade $authFacade
 * @property STS\Core\Api\MailerFacade $mailerFacade
 */
class Admin_MemberController extends SecureBaseController
{
    /**
     * @var STS\Core\Api\MemberFacade MemberFacade
     */
    protected $memberFacade;
    /**
     * @var STS\Core\Api\UserFacade
     */
    protected $userFacade;
    /**
     * @var STS\Core\Api\LocationFacade
     */
    protected $locationFacade;
    /**
     * @var STS\Core\Api\AuthFacade
     */
    protected $authFacade;

    /**
     * @var DefaultPresentationFacade
     */
    protected $presentationFacade;

    /**
     * @var STS\Core\Api\MailerFacade
     */
    protected $mailerFacade;

    /**
     * @var \Zend_Session_Namespace
     */
    protected $session;

    public function init()
    {
        parent::init();
        $core                     = Core::getDefaultInstance();
        $this->memberFacade       = $core->load('MemberFacade');
        $this->userFacade         = $core->load('UserFacade');
        $this->locationFacade     = $core->load('LocationFacade');
        $this->authFacade         = $core->load('AuthFacade');
        $this->presentationFacade = $core->load('PresentationFacade');
        $this->mailerFacade       = $core->load('MailerFacade');
        $this->session            = new \Zend_Session_Namespace('admin');
    }

    public function indexAction()
    {
        // setup filters
        $criteria  = array();
        $form_opts = array();
        $params    = $this->getRequest()->getParams();

        $this->session->criteria = $criteria;

        /** @var STS\Core\User\UserDTO $user */
        $user = $this->getAuth()->getIdentity();

        $page['title'] = 'Members';
        if (User::ROLE_COORDINATOR == $user->getRole()) {
            // limit filter options to regions they coordinate for
            $member               = $this->memberFacade->getMemberById($user->getAssociatedMemberId());
            $regions              = $member->getCoordinatesForRegions();
            $form_opts['regions'] = array_merge(array('0' => ''), $regions);
            if (! empty($params['region'])) {
                // ensure they only request items from their regions in filter
                $params['region'] = array_intersect($params['region'], array_values($regions));
            } else {
                // default to only their regions
                $params['region'] = array_values($regions);
                $this->filterParams('region', $params, $criteria);
                $this->session->criteria = $criteria;
            }
        } else {
            $page['add']      = 'Add New Member';
            $page['addRoute'] = '/admin/member/new';
        }

        // set page header
        $this->view->layout()->pageHeader = $this->view->partial('partials/page-header.phtml',
            $page);

        // get our form
        $form = $this->getFilterForm($form_opts);

        if (array_key_exists('reset', $params)) {
            return $this->_helper->redirector('index');
        }
        if (array_key_exists('update', $params)) {
            $form->setDefaults($params);
            $this->filterParams('role', $params, $criteria);
            $this->filterParams('status', $params, $criteria);
            $this->filterParams('region', $params, $criteria);
            $this->filterParams('presents_for', $params, $criteria);
            $this->filterParams('is_volunteer', $params, $criteria);
            $this->session->criteria = $criteria;
        }

        // load all the members to display
        // TODO add pagination?
        $members    = $this->memberFacade->getMembersMatching($criteria);
        $memberDtos = $this->getMembersArray($members);
        if (empty($memberDtos) && array_key_exists('update', $params)) {
            $this->setFlashMessageAndRedirect('No members matched your selected filter criteria!',
            'warning', array(
                'module'     => 'admin',
                'controller' => 'member',
                'action'     => 'index'
            ));
        }

        // pass permissions to view
        $this->view->can_view          = $this->getAcl()->isAllowed($user->getRole(),
            AclFactory::RESOURCE_MEMBER, 'view');
        $this->view->can_edit          = $this->getAcl()->isAllowed($user->getRole(),
            AclFactory::RESOURCE_MEMBER, 'edit');
        $this->view->can_delete        = $this->getAcl()->isAllowed($user->getRole(),
            AclFactory::RESOURCE_MEMBER, 'delete');
        $this->view->can_view_training = $this->getAcl()->isAllowed($user->getRole(),
            AclFactory::RESOURCE_MEMBER, 'trainingReport');
        $this->view->members           = $memberDtos;
        $this->view->form              = $form;
    }

    /**
     * excelAction
     */
    public function excelAction()
    {
        $criteria     = $this->session->criteria;
        $members      = $this->memberFacade->getMembersMatching($criteria);
        $member_array = $this->getMembersArray($members);

        $headers = array(
            'First Name',
            'Last Name',
            'Email',
            'Deceased?',
            'Areas',
            'Address',
            'Status',
            'Volunteer?',
            'Notes',
            'Can Be Deleted?',
            'Date Trained',
            'Role'
        );

        $csv = array();

        foreach ($member_array as $member) {
            if (1 == $member['canBeDeleted']) {
                $member['canBeDeleted'] = 'Yes';
            }
            if (1 == $member['deceased']) {
                $member['deceased'] = 'Yes';
            }
            if (1 == $member['is_volunteer']) {
                $member['is_volunteer'] = 'Yes';
            }
            $date = '';
            if ($member['dateTrained']) {
                $date = $member['dateTrained']->format("m/d/Y");
            }
            $member['dateTrained'] = $date;
            unset($member['roleClass']);
            unset($member['hasNotes']);

            if (is_array($member['area'])) {
                $member['area'] = implode(', ', $member['area']);
            }

            $csv[] = $member;
        }

        $this->outputCSV('members', $csv, $headers);
    }

    public function trainingAction()
    {
        $params = $this->getRequest()->getParams();

        $this->view->layout()->pageHeader = $this->view->partial(
            'partials/page-header.phtml',
            array(
                'title' => 'Member Training',
            )
        );

        // load all the members to display
        // TODO add pagination?
        $criteria = array();

        $members    = $this->memberFacade->getMembersMatching($criteria);
        $memberDtos = $this->getMembersArray($members);
        if (empty($memberDtos) && array_key_exists('update', $params)) {
            $this->setFlashMessageAndRedirect('No members matched your selected filter criteria!',
            'warning', array(
                'module'     => 'admin',
                'controller' => 'member',
                'action'     => 'index'
            ));
        }
        $this->view->members = $memberDtos;
    }

    public function trainingExcelAction()
    {
        $params = $this->getRequest()->getParams();


        // load all the members to display
        $criteria = array();

        $members    = $this->memberFacade->getMembersMatching($criteria);
        $memberDtos = $this->getMembersArray($members);
        if (empty($memberDtos) && array_key_exists('update', $params)) {
            $this->setFlashMessageAndRedirect('No members matched your selected filter criteria!',
            'warning', array(
                'module'     => 'admin',
                'controller' => 'member',
                'action'     => 'index'
            ));
        }

        $headers = array('role', 'status', 'name', 'email', 'date trained');
        $csv     = array();
        foreach ($memberDtos as $member) {
            $csv[] = array(
                $member['role'],
                $member['status'],
                sprintf("%s, %s", $member['lastName'], $member['firstName']),
                $member['email'],
                ($member['dateTrained'] ? $member['dateTrained']->format("m/d/Y") : '')
            );
        }

        $this->outputCSV('member_training-' . date('Ymd') . '.csv', $csv, $headers);
    }

    /**
     * @param $key
     * @param array $params
     * @param array $criteria
     */
    private function filterParams($key, &$params, &$criteria)
    {
        if (array_key_exists($key, $params)) {
            $chaff = array_search('0', $params[$key]);
            if ($chaff !== false) {
                unset($params[$key][$chaff]);
            }
            $criteria[$key] = $params[$key];
        }
    }

    /**
     * getFilterForm
     *
     * Return the filter form for list of all members
     *
     * @param array
     * @return Admin_MemberFilter
     */
    private function getFilterForm($form_opts)
    {
        // override regions
        $regions = $this->getRegionsArray();
        if (isset($form_opts['regions'])) {
            $regions = $form_opts['regions'];
        }

        $areas = $this->getAreasArray();
        if (isset($form_opts['presents_for'])) {
            $areas = $form_opts['presents_for'];
        }

        $form = new \Admin_MemberFilter(
            array(
                'roles'          => array_merge(array(
                        0             => '',
                        'ROLE_MEMBER' => 'Member'
                    ),
                    AclFactory::getAvailableRoles()
                ),
                'regions'           => $regions,
                'presentsFor'       => $areas,
                'memberStatuses'    => array_merge(array(''), $this->getMemberStatusesArray())
            )
        );
        return $form;
    }

    private function getAreasArray()
    {
        $areas_array = array('' => 'Presents in: (none)');
        /** @var Area $area */
        foreach ($this->locationFacade->getAllAreas() as $area) {
            $areas_array[$area->getId()] = $area->getName();
        }
        return $areas_array;
    }

    private function getRegionsArray()
    {
        $regionsArray = array('' => 'Regions: (none)');
        /** @var Region $region */
        foreach ($this->locationFacade->getAllRegions() as $region) {
            $regionsArray[$region->getName()] = $region->getName();
        }
        return $regionsArray;
    }

    public function deleteAction()
    {
        $id = $this->getRequest()->getParam('id');
        try {
            $results = $this->memberFacade->deleteMember($id);
            if ($results === true) {
                $this->setFlashMessageAndRedirect('The member has been removed from the system!',
                'success', array(
                    'module'     => 'admin',
                    'controller' => 'member',
                    'action'     => 'index'
                ));
            } else {
                throw new ApiException("An error occurred while deleting member.", 1);
            }
        } catch (ApiException $e) {
            $this->setFlashMessageAndRedirect('An error occurred while deleting member: ' . $e->getMessage(),
            'error', array(
                'module'     => 'admin',
                'controller' => 'member',
                'action'     => 'index'
            ));
        }
    }

    public function viewAction()
    {
        $id     = $this->getRequest()->getParam('id');
        $member = $this->memberFacade->getMemberById($id);
        if ($user = $this->userFacade->findUserById($member->getAssociatedUserId())) {
            $this->view->user = $user;
            $role             = $user->getRole();
        } else {
            $role = 'member';
        }
        if ($member->isDeceased()) {
            $labelTitle = 'Deceased';
            $lableClass = 'label-inverse';
        } else {
            $labelTitle = $this->getRoleTitleForRole($role);
            $lableClass = $this->getRoleClassForRole($role);
        }
        $parameters = array(
            'title' => $member->getFirstName() . ' ' . $member->getLastName(),
            'label' => array(
                'title' => $labelTitle,
                'class' => $lableClass
            )
        );
        if ($member->isDeceased()) {
            $parameters['titleClass'] = 'muted';
        }

        // TODO: move to Member class
        $presentations = $this->presentationFacade->getPresentationsForMemberId($member->getId());

        $this->view->layout()->pageHeader = $this->view->partial('partials/page-header.phtml',
            $parameters);
        $this->view->member               = $member;
        $this->view->presentations        = $presentations;

        /** @var STS\Core\User\UserDTO $user */
        $user = $this->getAuth()->getIdentity();

        // pass permissions to view
        $this->view->can_view   = $this->getAcl()->isAllowed($user->getRole(),
            AclFactory::RESOURCE_MEMBER, 'view');
        $this->view->can_edit   = $this->getAcl()->isAllowed($user->getRole(),
            AclFactory::RESOURCE_MEMBER, 'edit');
        $this->view->can_delete = $this->getAcl()->isAllowed($user->getRole(),
            AclFactory::RESOURCE_MEMBER, 'delete');
    }

    /**
     * newAction
     *
     * Add a new member. Display the form, process POST data.
     *
     * @access public
     */
    public function newAction()
    {
        $this->view->form = $this->getForm();
        $request          = $this->getRequest();
        $form             = $this->getForm();
        $form->setAction('/admin/member/new');

        // handle POST input
        if ($this->getRequest()->isPost()) {
            $postData = $request->getPost();
            if ($this->formIsValid($form, $postData)) {
                try {
                    if ($postData['role'] != '0') {
                        if ($this->userFacade->findUserById($postData['systemUsername']) != array()) {
                            throw new ApiException("A system user with the username: \"{$postData['systemUsername']}\" already exists. System users must have a unique email and username.");
                        }
                        if ($this->userFacade->findUserByEmail($postData['systemUserEmail']) != array()) {
                            throw new ApiException("A system user with the email address: \"{$postData['systemUserEmail']}\" already exists. System users must have a unique email and username.");
                        }
                    }
                    //save new member
                    try {
                        $newMemberDto   = $this->saveNewMember($postData);
                        $successMessage = "The new member \"{$postData['firstName']} {$postData['lastName']}\" has been successfully saved.";
                        if ($postData['role'] != '0') {
                            //save new system user
                            $tempPassword  = $postData['tempPassword'];
                            $systemUserDto = $this->saveNewUser($postData, $newMemberDto,
                                $tempPassword);
                            //send credentials via email
                            $name = $systemUserDto->getFirstName() . ' ' . $systemUserDto->getLastName();
                            $this->mailerFacade->sendNewAccountNotification($name,
                                $systemUserDto->getId(), $systemUserDto->getEmail(), $tempPassword);
                            //update success message
                            $successMessage .= " The new user with username: \"{$systemUserDto->getId()}\" and password: \"$tempPassword\" may now access the system. This information has been emailed to them.";
                        }

                        $this->setFlashMessageAndRedirect($successMessage, 'success', array(
                            'module'     => 'admin',
                            'controller' => 'member',
                            'action'     => 'index'
                        ));
                    } catch (\Exception $e) {
                        $this->setFlashMessageAndUpdateLayout('An error occurred while saving this information: ' . $e->getMessage(),
                            'error');
                    }
                } catch (ApiException $e) {
                    $this->setFlashMessageAndUpdateLayout('An error occurred while saving this information: ' . $e->getMessage(),
                        'error');
                }
            } else {
                $this->setFlashMessageAndUpdateLayout('It looks like you missed some information, please make the corrections below.',
                    'error');
            }
        }
        $this->view->form = $form;
    }

    /**
     * editAction
     *
     * @access public
     **/
    public function editAction()
    {
        $id   = $this->getRequest()->getParam('id');
        $form = $this->getForm();

        // for checking edit permissions
        $acl = $this->getAcl();

        // load our member
        $dto = $this->memberFacade->getMemberById($id);

        // make sure form posts back to self
        $form->setAction('/admin/member/edit?' . http_build_query(array('id' => $id)));
        $this->view->member               = $dto;
        $this->view->layout()->pageHeader = $this->view->partial(
            'partials/page-header.phtml', array(
                'title' => 'Edit: ' . $dto->getFirstName() . ' ' . $dto->getLastName()
            )
        );

        // get the member associated user id to see if the user is a member
        $associatedUserId = $dto->getAssociatedUserId();

        // also get the associated user if there is one
        // associatedUser is what holds login credentials
        $associatedUser = $this->userFacade->getUserByMemberId($dto->getId());

        // the user name will always be set
        $username       = null;
        $hiddenUsername = null;

        if (! is_null($associatedUser)) {
            $username       = $associatedUser->getId();
            $hiddenUsername = $username;

            // make sure user can change username
            $role = $this->getAuth()->getIdentity()->getRole();
            if (! $acl->isAllowed($role, AclFactory::RESOURCE_USER, 'change username')) {
                $form->getElement('systemUsername')->setAttrib('disabled', 'disabled');
            }

            $form->getElement('tempPassword')->setRequired(false);
            $form->getElement('tempPassword')->setAttrib('placeholder', 'xxxxxxxxxx');
            $form->getElement('tempPassword')->setDescription('This member has a user account and password. Only change these fields if you want to change their password!');
            $form->getElement('tempPasswordConfirm')->setRequired(false);
            $form->getElement('tempPasswordConfirm')->setAttrib('placeholder', 'xxxxxxxxxx');
        }

        // if the id is null, the member is just a member, so don't show user details
        // TODO this test for if someone is "just a member" should be in the memberFacade or model
        if (is_null($associatedUserId) || empty($associatedUserId) || is_null($associatedUser)) {
            $role = '0';
        } else {
            //else set the role
            $role = $this->userFacade->getUserRoleKey($associatedUser->getRole());
        }

        // populate the form data
        $form->populate(
            array(
                'firstName'            => $dto->getFirstName(),
                'lastName'             => $dto->getLastName(),
                'systemUserEmail'      => $dto->getEmail(),
                'memberType'           => $this->memberFacade->getMemberTypeKey($dto->getType()),
                'memberStatus'         => $this->memberFacade->getMemberStatusKey($dto->getStatus()),
                'is_volunteer'         => $dto->isVolunteer(),
                'memberActivity'       => $dto->getActivities(),
                'dateTrained'          => $dto->getDateTrained(),
                'notes'                => $dto->getNotes(),
                'workPhone'            => $this->getPhoneNumberFromDto('work',
                    $dto->getPhoneNumbers()),
                'homePhone'            => $this->getPhoneNumberFromDto('home',
                    $dto->getPhoneNumbers()),
                'cellPhone'            => $this->getPhoneNumberFromDto('cell',
                    $dto->getPhoneNumbers()),
                'address'       => $dto->getAddress(),
                'diagnosisDate'        => $dto->getDiagnosisDate(),
                'diagnosisStage'       => $dto->getDiagnosisStage(),
                'role'                 => $role,
                'systemUsername'       => $username,
                'hiddenSystemUsername' => $hiddenUsername
            )
        );

        $this->view->storedPresentsFor    = $dto->getPresentsForAreas();
        $this->view->storedCoordinatesFor = $dto->getCoordinatesForRegions();
        $this->view->storedFacilitatesFor = $dto->getFacilitatesForAreas();

        // process any updates if we get any
        if ($this->getRequest()->isPost()) {
            $request  = $this->getRequest();
            $postData = $request->getPost();
            if ($this->formIsValid($form, $postData)) {
                try {
                    // if a member has been upgraded to a system user, check the email
                    // and password to ensure no duplication
                    $is_self = false;
                    if (! empty($postData['systemUsername']) && $postData['role'] != '0') {

                        // test if the username is used by another record
                        $dupe = $this->userFacade->findUserById($postData['systemUsername']);
                        if (! empty($dupe) && ! empty($associatedUser)) {
                            $is_self = ($dupe->getAssociatedMemberId() == $associatedUser->getAssociatedMemberId());
                        }

                        // If there is no associated user but we find one with the same
                        // username, the username is a duplicate and we should trigger
                        // an error.
                        if (! empty($dupe) && empty($associatedUser)) {
                            $msg = sprintf(
                                "A system user with the username: %s already exists. System users must have a unique email and username.",
                                $postData['systemUsername']
                            );

                            throw new ApiException($msg);
                        }

                        if (! empty($dupe) && ! $is_self && $dupe->getAssociatedMemberId()) {
                            $msg = sprintf(
                                "A system user with the username: %s already exists. System users must have a unique email and username.",
                                $postData['systemUsername']
                            );

                            throw new ApiException($msg);
                        }

                        // test if the email is used by another record
                        $dupe = $this->userFacade->findUserByEmail($postData['systemUserEmail']);
                        if (! empty($dupe) && ! empty($associatedUser)) {
                            $is_self = ($dupe->getAssociatedMemberId() == $associatedUser->getAssociatedMemberId());
                        }

                        if (! empty($dupe) && ! $is_self) {
                            throw new ApiException("A system user with the email address: \"{$postData['systemUserEmail']}\" already exists. System users must have a unique email and username.");
                        }
                    }

                    // check if we are changing an existing user's name
                    if ($postData['role'] != '0' && ! empty($postData['hiddenSystemUsername'])
                        && $postData['hiddenSystemUsername'] != $postData['systemUsername']
                    ) {
                        $this->changeUsername($associatedUser, $dto, $postData);

                        // handle other form updates
                        $postData['hiddenSystemUsername'] = $postData['systemUsername'];
                        $updatedMemberDto                 = $this->updateMember($id, $postData);
                    } else {
                        // if a member has be downgraded from a system user to a member
                        // its ok as that is handled by the saving
                        $updatedMemberDto = $this->updateMember($id, $postData);
                        $successMessage   = "The member \"{$postData['firstName']} {$postData['lastName']}\" has been successfully updated.";

                        // if a system user is changed roles
                        // then confirm that and set the username to the hidden value
                        if ($postData['role'] != '0') {
                            if (! $is_self && ! empty($postData['systemUsername'])) {
                                // the user is new, we must add them
                                $tempPassword  = $postData['tempPassword'];
                                $systemUserDto = $this->saveNewUser($postData, $updatedMemberDto,
                                    $tempPassword);
                                // send credentials via email
                                $name = $systemUserDto->getFirstName() . ' ' . $systemUserDto->getLastName();
                                $this->mailerFacade->sendNewAccountNotification($name,
                                    $systemUserDto->getId(), $systemUserDto->getEmail(),
                                    $tempPassword);
                                $successMessage .= " The new user with username: \"{$systemUserDto->getId()}\" and password: \"$tempPassword\" may now access the system. This information has been emailed to them.";
                            } else if (! empty($postData['tempPassword'])) {
                                // the user has changed, we must modify
                                $postData['systemUsername'] = $postData['hiddenSystemUsername'];
                                $tempPassword               = $postData['tempPassword'];
                                $systemUserDto              = $this->updateExistingUser($postData,
                                    $updatedMemberDto, $tempPassword);

                                // send credentials via email
                                $name = $systemUserDto->getFirstName() . ' ' . $systemUserDto->getLastName();
                                $this->mailerFacade->sendNewAccountNotification($name,
                                    $systemUserDto->getId(), $systemUserDto->getEmail(),
                                    $tempPassword);
                                $successMessage .= " The user with username: \"{$systemUserDto->getId()}\" has been updated! Updated information has been emailed to them.";
                            } else {
                                // simply update the user account, login and/or password haven't changed
                                $systemUserDto = $this->updateExistingUser(
                                    $postData,
                                    $updatedMemberDto,
                                    $associatedUser->getPassword(),
                                    false, // don't change the password
                                    $associatedUser->getSalt()
                                );
                                $successMessage .= " The user with username: \"{$systemUserDto->getId()}\" has been updated!";
                            }
                        }
                    }

                    $this->setFlashMessageAndRedirect($successMessage, 'success', array(
                        'module'     => 'admin',
                        'controller' => 'member',
                        'action'     => 'view',
                        'params'     => array('id' => $updatedMemberDto->getId())
                    ));
                } catch (Exception $e) {
                    $previous = $e->getPrevious() ?: new Exception();
                    $this->setFlashMessageAndUpdateLayout('An error occurred while saving this information: ' . $e->getMessage() . ' ' . $previous->getMessage(),
                        'error');
                }
            } else {
                $this->setFlashMessageAndUpdateLayout('It looks like you missed some information, please make the corrections below.',
                    'error');
            }
        }
        $this->view->form = $form;
    }

    /**
     * @param UserDTO $dto
     * @return string
     */
    private function getUserRoleFromDto($dto)
    {
        if (is_null($dto)) {
            return '0';
        } else {
            return $this->userFacade->getUserRoleKey($dto->getRole());
        }
    }

    /**
     * @param UserDTO $dto
     * @return null or int
     */
    private function getUserNameFromDto($dto)
    {
        if (is_null($dto)) {
            return null;
        } else {
            return $dto->getId();
        }
    }

    /**
     * @param $type
     * @param array $numbers
     * @return null|string
     */
    private function getPhoneNumberFromDto($type, $numbers)
    {
        if (! is_null($numbers) && array_key_exists($type, $numbers)) {
            $number = $numbers[$type]['number'];
            return sprintf('%s-%s-%s', substr($number, 0, 3), substr($number, 3, - 4),
                substr($number, - 4));
        } else {
            return null;
        }
    }

    /**
     * @param UserDTO $systemUserDto
     * @param $tempPassword
     */
    private function sendNotificationOfNewAccount(UserDTO $systemUserDto, $tempPassword)
    {
        $name     = $systemUserDto->getFirstName() . ' ' . $systemUserDto->getLastName();
        $username = $systemUserDto->getId();
        $email    = $systemUserDto->getEmail();
        $this->mailerFacade->sendNewAccountNotification($name, $username, $email, $tempPassword);
    }

    /**
     * @param array $postData
     * @param Core\Member\MemberDto $newMemberDto
     * @param $tempPassword
     * @return mixed
     */
    private function saveNewUser(
        array $postData,
        Core\Member\MemberDto $newMemberDto,
        $tempPassword
    ) {
        $firstName          = $newMemberDto->getFirstName();
        $lastName           = $newMemberDto->getLastName();
        $email              = $postData['systemUserEmail'];
        $username           = $postData['systemUsername'];
        $password           = $tempPassword;
        $role               = AclFactory::getAvailableRole($postData['role']);
        $associatedMemberId = $newMemberDto->getId();

        return $this->userFacade->createUser($username, $firstName, $lastName, $email, $password,
            $role, $associatedMemberId);
    }

    /**
     * @param array $postData
     * @param MemberDto $memberDto
     * @param $tempPassword
     * @param bool $init_password
     * @param null $salt
     *
     * @return mixed
     */
    private function updateExistingUser(
        array $postData,
        Core\Member\MemberDto $memberDto,
        $tempPassword,
        $init_password = true,
        $salt = null
    ) {
        $firstName          = $memberDto->getFirstName();
        $lastName           = $memberDto->getLastName();
        $email              = $postData['systemUserEmail'];
        $username           = $postData['systemUsername'];
        $password           = $tempPassword;
        $role               = AclFactory::getAvailableRole($postData['role']);
        $associatedMemberId = $memberDto->getId();
        return $this->userFacade->updateUser($username, $firstName, $lastName, $email, $password,
            $role, $associatedMemberId, $init_password, $salt);
    }

    /**
     * @param array $data
     * @return mixed
     */
    private function saveNewMember(array $data)
    {
        if ($data['memberStatus'] == 'STATUS_ACTIVE') {
            if ($data['role'] == 'ROLE_ADMIN') {
                $userId         = $data['systemUsername'];
                $presentsFor    = array();
                $facilitatesFor = array();
                $coordinatesFor = array();
            } elseif ($data['role'] == 'ROLE_FACILITATOR') {
                $userId         = $data['systemUsername'];
                $presentsFor    = array();
                $facilitatesFor = array_keys($data['facilitatesFor']);
                $coordinatesFor = array();
            } elseif ($data['role'] == 'ROLE_COORDINATOR') {
                $userId         = $data['systemUsername'];
                $presentsFor    = array();
                $facilitatesFor = array();
                $coordinatesFor = $this->getAreasForRegionsArray(array_keys($data['coordinatesFor']));
            } else {
                $presentsFor    = array_keys($data['presentsFor']);
                $facilitatesFor = array();
                $coordinatesFor = array();
                $userId         = null;
            }
        } else {
            $presentsFor    = array();
            $facilitatesFor = array();
            $coordinatesFor = array();
            $userId         = null;
        }

        $activities = array();
        if (!empty($data['memberActivity'])) {
            $activities = array_values($data['memberActivity']);
        }

        return $this->memberFacade->saveMember(
            $data['firstName'],
            $data['lastName'],
            Member::getAvailableType($data['memberType']),
            Member::getAvailableStatus($data['memberStatus']),
            $data['is_volunteer'],
            $activities,
            $data['notes'],
            $presentsFor,
            $facilitatesFor,
            $coordinatesFor,
            $userId,
            $data['address'],
            $data['systemUserEmail'],
            $data['dateTrained'],
            array('date' => $data['diagnosisDate'], 'stage' => $data['diagnosisStage']),
            array(
                'work' => $data['workPhone'],
                'cell' => $data['cellPhone'],
                'home' => $data['homePhone']
            )
        );
    }

    /**
     * updateMember
     *
     * @param $id
     * @param $data
     * @return mixed
     */
    private function updateMember($id, $data)
    {
        // persist the username
        if (empty($data['systemUsername'])) {
            $data['systemUsername'] = $data['hiddenSystemUsername'];
        }

        if ($data['memberStatus'] == 'STATUS_ACTIVE') {
            if ($data['role'] == 'ROLE_ADMIN') {
                $userId         = $data['systemUsername'];
                $presentsFor    = array();
                $facilitatesFor = array();
                $coordinatesFor = array();
            } elseif ($data['role'] == 'ROLE_FACILITATOR') {
                $userId         = $data['systemUsername'];
                $presentsFor    = array();
                $facilitatesFor = array_keys($data['facilitatesFor']);
                $coordinatesFor = array();
            } elseif ($data['role'] == 'ROLE_COORDINATOR') {
                $userId         = $data['systemUsername'];
                $presentsFor    = array();
                $facilitatesFor = array();
                $coordinatesFor = $this->getAreasForRegionsArray(array_keys($data['coordinatesFor']));
            } else {
                $presentsFor    = array_keys($data['presentsFor']);
                $facilitatesFor = array();
                $coordinatesFor = array();
                $userId         = null;
            }
        } else {
            $presentsFor    = array();
            $facilitatesFor = array();
            $coordinatesFor = array();
            $userId         = null;
        }

        $activities = array();
        if ($data['memberActivity']) {
            $activities = $data['memberActivity'];
        }

        return $this->memberFacade->updateMember($id,
            $data['firstName'],
            $data['lastName'],
            Member::getAvailableType($data['memberType']),
            Member::getAvailableStatus($data['memberStatus']),
            $data['is_volunteer'],
            $activities,
            $data['notes'],
            $presentsFor,
            $facilitatesFor,
            $coordinatesFor,
            $userId,
            $data['address'],
            $data['systemUserEmail'],
            $data['dateTrained'],
            array('date' => $data['diagnosisDate'], 'stage' => $data['diagnosisStage']),
            array(
                'work' => $data['workPhone'],
                'cell' => $data['cellPhone'],
                'home' => $data['homePhone']
            )
        );
    }

    /**
     * @param array $members
     * @return array
     */
    private function getMembersArray($members)
    {
        $memberData = array();
        if (empty($members)) {
            return $memberData;
        }
        /** @var MemberDto $member */
        foreach ($members as $member) {
            $notes    = $member->getNotes();
            $hasNotes = empty($notes) ? false : true;
            $data     = array(
                'firstName'    => $member->getFirstName(),
                'lastName'     => $member->getLastName(),
                'email'        => $member->getEmail(),
                'deceased'     => $member->isDeceased(),
                'area'         => array_merge(
                    $member->getPresentsForAreas(),
                    $member->getCoordinatesForAreas(),
                    $member->getFacilitatesForAreas(),
                    $member->getCoordinatesForRegions()
                ),
                'address'      => $member->getAddress(),
                'status'       => $member->getStatus(),
                'is_volunteer' => $member->isVolunteer(),
                'hasNotes'     => $hasNotes,
                'Notes'        => $member->getNotes(),
                'canBeDeleted' => $member->canBeDeleted(),
                'dateTrained'  => false,
            );

            if ($member->getDateTrained()) {
                $data['dateTrained'] = new DateTime($member->getDateTrained());
            }

            if ($member->getAssociatedUserId() != null) {
                if ($user = $this->userFacade->findUserById($member->getAssociatedUserId())) {
                    $role = $user->getRole();
                }
            } else {
                $role = 'member';
            }
            if ($member->isDeceased()) {
                $data['role']      = 'Deceased';
                $data['roleClass'] = 'label-inverse';
            } else {
                $data['role']      = $this->getRoleTitleForRole($role);
                $data['roleClass'] = $this->getRoleClassForRole($role);
            }
            $memberData[$member->getId()] = $data;
        }

        return $memberData;
    }

    private function getRoleTitleForRole($role)
    {
        switch ($role) {
            case 'admin':
                $role = "Site Administrator";
                break;

            case 'coordinator':
                $role = "Regional Coordinator";
                break;

            case 'facilitator':
                $role = "Area Facilitator";
                break;

            default:
                $role = "Member";
                break;
        }

        return $role;
    }

    /**
     * @param string $role
     * @return string
     */
    private function getRoleClassForRole($role)
    {
        switch ($role) {
            case 'admin':
                $roleClass = "label-important";
                break;

            case 'coordinator':
                $roleClass = "label-warning";
                break;

            case 'facilitator':
                $roleClass = "label-info";
                break;

            default:
                $roleClass = "";
                break;
        }

        return $roleClass;
    }

    /**
     * @return Admin_Member
     */
    private function getForm()
    {
        // get diagnosis select options
        $diagnosisStagesArray = array_merge(array(''), $this->memberFacade->getDiagnosisStages());

        // get states select options
        $statesArray = array_merge(array('-- Select One --'), $this->locationFacade->getStates());

        // get member types select options
        $memberTypesArray = array_merge(array('-- Select One --'),
            $this->memberFacade->getMemberTypes());

        // get member activities checkbox options
        $vals                  = array_values($this->memberFacade->getMemberActivities());
        $memberActivitiesArray = array_combine($vals, $vals);

        // build the zend form
        $form = new \Admin_Member(array(
            'states'           => $statesArray,
            'roles'            => $this->getRolesArray(),
            'memberTypes'      => $memberTypesArray,
            'memberStatuses'   => $this->getMemberStatusesArray(),
            'memberActivities' => $memberActivitiesArray,
            'diagnosisStages'  => $diagnosisStagesArray,
            'phoneNumberTypes' => $this->memberFacade->getPhoneNumberTypes()
        ));

        return $form;
    }

    /**
     * @return array
     */
    private function getRolesArray()
    {
        return array_merge(array('Member'), AclFactory::getAvailableRoles());
    }

    /**
     * @return mixed
     */
    private function getMemberStatusesArray()
    {
        return $this->memberFacade->getMemberStatuses();
    }

    /**
     * @param array $array
     * @return array
     */
    private function getAreasForRegionsArray($array)
    {
        $dtos = $this->locationFacade->getAreasForRegions($array);
        $keys = array();

        foreach ($dtos as $dto) {
            $keys[] = $dto->getId();
        }

        return $keys;
    }

    /**
     * @param Admin_Member $form
     * @param $postData
     * @return bool
     */
    private function formIsValid(Admin_Member &$form, $postData)
    {
        $validations   = array();
        $validations[] = $form->getElement('firstName')->isValid($postData['firstName']);
        $validations[] = $form->getElement('lastName')->isValid($postData['lastName']);
        $validations[] = $form->getElement('systemUserEmail')->isValid($postData['systemUserEmail']);
        $validations[] = $form->getElement('memberType')->isValid($postData['memberType']);
        $validations[] = $form->getElement('role')->isValid($postData['role']);
        $validations[] = $form->getElement('memberStatus')->isValid($postData['memberStatus']);
        $validations[] = $form->getElement('dateTrained')->isValid($postData['dateTrained']);

        $validations[] = $form->getElement('workPhone')->isValid($postData['workPhone']);
        $validations[] = $form->getElement('cellPhone')->isValid($postData['cellPhone']);
        $validations[] = $form->getElement('homePhone')->isValid($postData['homePhone']);

        $validations[] = $form->getElement('address')->isValid($postData['address']);

        $validations[] = $form->getElement('diagnosisDate')->isValid($postData['diagnosisDate']);
        $validations[] = $form->getElement('diagnosisStage')->isValid($postData['diagnosisStage']);

        if ($postData['memberStatus'] == 'STATUS_ACTIVE') {
            //if member has been marked active, validate any relevant system user information
            if ($postData['role'] != '0') {
                //if role is not member, validate that a username and email has been entered and that they are unique
                if (array_key_exists('systemUsername', $postData)) {
                    $validations[] = $form->getElement('systemUsername')->isValid($postData['systemUsername']);
                } else {
                    $validations[] = $form->getElement('systemUsername')->isValid($postData['hiddenSystemUsername']);
                }
                $validations[] = $form->getElement('tempPassword')->isValid($postData['tempPassword']);
                $validations[] = $form->getElement('tempPasswordConfirm')->isValid($postData['tempPasswordConfirm']);
                if ($postData['tempPassword'] != $postData['tempPasswordConfirm']) {
                    $form->getElement('tempPasswordConfirm')->addErrors(array(
                        'The two passwords do not match!'
                    ))->markAsError();
                    $validations[] = false;
                }
            } else {
                //else validate presents for
                if (! array_key_exists('presentsFor',
                        $postData) || ! is_array($postData['presentsFor'])
                ) {
                    $form->getElement('presentsFor[]')->addErrors(array(
                        'Please enter at least one area.'
                    ))->markAsError();
                    $validations[] = false;
                } else {
                    $this->view->storedPresentsFor = $postData['presentsFor'];
                    $validations[]                 = true;
                }
            }

            if ($postData['role'] == 'ROLE_COORDINATOR') {
                //if role is coordinator, validate regions
                if (! array_key_exists('coordinatesFor',
                        $postData) || ! is_array($postData['coordinatesFor'])
                ) {
                    $form->getElement('coordinatesFor[]')->addErrors(array(
                        'Please enter at least one region.'
                    ))->markAsError();
                    $validations[] = false;
                } else {
                    $this->view->storedCoordinatesFor = $postData['coordinatesFor'];
                    $validations[]                    = true;
                }
            }
            if ($postData['role'] == 'ROLE_FACILITATOR') {
                //if role is facilitator, validate areas
                if (! array_key_exists('facilitatesFor',
                        $postData) || ! is_array($postData['facilitatesFor'])
                ) {
                    $form->getElement('facilitatesFor[]')->addErrors(array(
                        'Please enter at least one area.'
                    ))->markAsError();
                    $validations[] = false;
                } else {
                    $this->view->storedFacilitatesFor = $postData['facilitatesFor'];
                    $validations[]                    = true;
                }
            }
        }

        if (! in_array(false, $validations)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * changeUsername
     *
     * Changes a username and updates references to it.
     *
     * @param $srcUser
     * @param $dto
     * @param $postData
     */
    private function changeUsername(UserDTO $srcUser, $dto, $postData)
    {
        // create user w/new username from old user
        // use data coming from the form in case its been edited
        if (! empty($postData['tempPassword'])) {
            $password      = $postData['tempPassword'];
            $salt          = null;
            $init_password = true;
        } else {
            $password      = $srcUser->getPassword();
            $salt          = $srcUser->getSalt();
            $init_password = false;
        }

        $user = $this->userFacade->createUser(
            $postData['systemUsername'],
            $postData['firstName'],
            $postData['lastName'],
            $postData['systemUserEmail'], // in case they update the form
            $password,
            AclFactory::getAvailableRole($postData['role']),
            $srcUser->getAssociatedMemberId(),
            $init_password,
            $salt
        );

        $old_user_id = $srcUser->getId();

        // change all references in presentation collection (entered_by_user_id)
        $core               = Core::getDefaultInstance();
        $presentationFacade = $core->load('PresentationFacade');
        $presentationFacade->updateEnteredBy($old_user_id, $user->getId());

        // change all references in survey collection (entered_by_user_id)
        $surveyFacade = $core->load('SurveyFacade');
        $surveyFacade->updateEnteredBy($old_user_id, $user->getId());

        // finally, delete old user
        $this->userFacade->deleteUser($old_user_id);
    }

    public function dashboardAction()
    {
        // set title
        $this->view->layout()->pageHeader = $this->view->partial(
            'partials/page-header.phtml', array(
                'title' => 'Member Dashboard'
            )
        );

        /** @var STS\Core\User\UserDTO $user */
        $user = $this->getAuth()->getIdentity();
        list($criteria, $options) = $this->getDefaultUserCriteria($user);
        $summary             = $this->getMemberSummary($criteria, $options);
        $this->view->summary = $summary;
    }

    /**
     * @param User $user
     *
     * @return array
     */
    protected function getDefaultUserCriteria($user)
    {
        $criteria = array();
        $options  = array();
        if (User::ROLE_COORDINATOR == $user->getRole()) {
            // limit filter options to regions they coordinate for
            $member                     = $this->memberFacade->getMemberById($user->getAssociatedMemberId());
            $criteria['region']         = $member->getCoordinatesForRegions();
            $options['allowed_regions'] = $criteria['region'];
            $areas                      = $this->locationFacade->getAreasForRegions($criteria['region']);
            /** @var Area $area */
            foreach ($areas as $area) {
                $options['allowed_areas'][$area->getID()] = $area->getName();
            }
        }

        return array($criteria, $options);
    }

    public function getMemberSummary($criteria = array(), $options = array())
    {
        $members = $this->memberFacade->getMembersMatching($criteria);

        $summary          = new StdClass;
        $summary->count   = 0;
        $summary->regions = array();
        $summary->areas   = array();
        $summary->status  = array();
        /** @var MemberDto $member */
        foreach ($members as $member) {
            $summary->count ++;

            // by status
            if (! isset($summary->status[$member->getStatus()])) {
                $summary->status[$member->getStatus()] = 0;
            }
            $summary->status[$member->getStatus()] ++;

            if (Member::STATUS_ACTIVE != $member->getStatus()) {
                continue;
            }

            // by region
            if ($coord = $member->getCoordinatesForRegions()) {
                foreach ($coord as $region) {
                    if ($region) {
                        // check if allowed
                        if (! empty($options['allowed_regions']) && ! isset($options['allowed_regions'][$region])) {
                            continue;
                        }

                        if (! isset($summary->regions[$region]['coordinates'])) {
                            $summary->regions[$region]['coordinates'] = 0;
                        }

                        $summary->regions[$region]['coordinates'] ++;
                        $summary->regions[$region]['raw'][$member->getID()] = 1;

                        // track unique for totals
                        $summary->region_total['coordinates'][$member->getID()] = 1;
                    }
                }
            }

            // area coordinators
            if ($areas = $member->getCoordinatesForAreas()) {
                foreach ($areas as $id => $area) {
                    // check if allowed
                    if (! empty($options['allowed_areas']) && ! isset($options['allowed_areas'][$id])) {
                        continue;
                    }

                    if (! isset($summary->areas[$area]['coordinates'])) {
                        $summary->areas[$area]['coordinates'] = 0;
                    }

                    $summary->areas[$area]['coordinates'] ++;
                    $summary->areas[$area]['raw'][$member->getID()] = 1;

                    // track unique for totals
                    $summary->area_total['coordinates'][$member->getID()] = 1;
                }
            }

            // area facilitators
            if ($areas = $member->getFacilitatesForAreas()) {
                foreach ($areas as $id => $area) {

                    // check if allowed
                    if (! empty($options['allowed_areas']) && ! isset($options['allowed_areas'][$id])) {
                        continue;
                    }

                    if (! isset($summary->areas[$area]['facilitates'])) {
                        $summary->areas[$area]['facilitates'] = 0;
                    }

                    // increment area facilitator count
                    $summary->areas[$area]['facilitates'] ++;
                    $summary->areas[$area]['raw'][$member->getID()] = 1;

                    // add to list of region facilitators (track uniques to prevent double counting)
                    /** @var STS\Core\Location\AreaDto $areaDto */
                    $areaDto = $this->locationFacade->getAreaById($id);

                    // check if allowed
                    if (empty($options['allowed_regions']) || isset($options['allowed_regions'][$areaDto->getRegionName()])) {
                        $summary->regions[$areaDto->getRegionName()]['facilitates'][$member->getID()] = 1;

                        // track raw count
                        $summary->regions[$areaDto->getRegionName()]['raw'][$member->getID()] = 1;
                    }

                    // track unique for totals
                    $summary->region_total['facilitates'][$member->getID()] = 1;

                    // track unique for totals
                    $summary->area_total['facilitates'][$member->getID()] = 1;
                }
            }

            // area presenters
            if ($areas = $member->getPresentsForAreas()) {
                foreach ($areas as $id => $area) {

                    // check if allowed
                    if (! empty($options['allowed_areas']) && ! isset($options['allowed_areas'][$id])) {
                        continue;
                    }

                    if (! isset($summary->areas[$area]['presents'])) {
                        $summary->areas[$area]['presents'] = 0;
                    }

                    $summary->areas[$area]['presents'] ++;
                    $summary->areas[$area]['raw'][$member->getID()] = 1;

                    // add to list of region facilitators (track uniques to prevent double counting)
                    /** @var STS\Core\Location\AreaDto $areaDto */
                    $areaDto = $this->locationFacade->getAreaById($id);

                    if (empty($options['allowed_regions']) || isset($options['allowed_regions'][$areaDto->getRegionName()])) {
                        $summary->regions[$areaDto->getRegionName()]['presents'][$member->getID()] = 1;

                        // track raw count
                        $summary->regions[$areaDto->getRegionName()]['raw'][$member->getID()] = 1;
                    }

                    // track unique for totals
                    $summary->region_total['presents'][$member->getID()] = 1;

                    // track unique for totals
                    $summary->area_total['presents'][$member->getID()] = 1;
                }
            }
        }

        // total up each region row by member type
        foreach ($summary->regions as $region => $totals) {
            $summary->regions[$region]['facilitates'] = array_sum($summary->regions[$region]['facilitates']);
            $summary->regions[$region]['presents']    = array_sum($summary->regions[$region]['presents']);
            $summary->regions[$region]['raw']         = array_sum($summary->regions[$region]['raw']);
        }

        // add summary totals to region columns
        ksort($summary->regions);
        $summary->regions['Total']['presents']    = array_sum($summary->region_total['presents']);
        $summary->regions['Total']['facilitates'] = array_sum($summary->region_total['facilitates']);
        $summary->regions['Total']['coordinates'] = array_sum($summary->region_total['coordinates']);
        $summary->regions['Total']['raw']         = '-';

        // total up each area by member type
        foreach ($summary->areas as $area => $totals) {
            $summary->areas[$area]['raw'] = array_sum($summary->areas[$area]['raw']);
        }

        ksort($summary->areas);
        $summary->areas['Total']['presents']    = array_sum($summary->area_total['presents']);
        $summary->areas['Total']['facilitates'] = array_sum($summary->area_total['facilitates']);
        $summary->areas['Total']['coordinates'] = array_sum($summary->area_total['coordinates']);
        $summary->areas['Total']['raw']         = '-';

        ksort($summary->status);

        return $summary;
    }

    public function excelBystatusAction()
    {
        /** @var STS\Core\User\UserDTO $user */
        $user     = $this->getAuth()->getIdentity();
        $criteria = $this->getDefaultUserCriteria($user);
        $summary  = $this->getMemberSummary($criteria);

        $header = array('status', 'count');
        $csv    = array();
        foreach ($summary->status as $status => $count) {
            $csv[] = array($status, $count);
        }

        $this->outputCSV('MemberByStatus-' . date('Y-m-d') . '.csv', $csv, $header);
    }

    public function excelByregionAction()
    {
        /** @var STS\Core\User\UserDTO $user */
        $user     = $this->getAuth()->getIdentity();
        $criteria = $this->getDefaultUserCriteria($user);
        $summary  = $this->getMemberSummary($criteria);

        $header = array('region', 'presenter', 'facilitator', 'coordinator', 'unique');
        $csv    = array();
        foreach ($summary->regions as $region => $values) {
            $csv[] = array(
                $region,
                $values['presents'],
                $values['facilitates']
                ,
                $values['coordinates'],
                $values['raw']
            );
        }

        $this->outputCSV('MemberByRegion-' . date('Y-m-d') . '.csv', $csv, $header);
    }


    public function excelByareaAction()
    {
        /** @var STS\Core\User\UserDTO $user */
        $user     = $this->getAuth()->getIdentity();
        $criteria = $this->getDefaultUserCriteria($user);
        $summary  = $this->getMemberSummary($criteria);

        $header = array('area', 'presenters', 'facilitators', 'coordinators', 'unique');
        $csv    = array();

        foreach ($summary->areas as $area => $values) {
            $csv[] = array(
                $area,
                $values['presents'],
                $values['facilitates']
                ,
                $values['coordinates'],
                $values['raw']
            );
        }

        $this->outputCSV('MemberByArea-' . date('Y-m-d') . '.csv', $csv, $header);
    }

    public function coordinatorsAction()
    {
        $criteria = array();
        $members  = $this->memberFacade->getMembersMatching($criteria);

        // set title
        $this->view->layout()->pageHeader = $this->view->partial(
            'partials/page-header.phtml', array(
                'title' => 'Regional Coordinators'
            )
        );

        // get only coordinators
        $members = array_filter($members, function ($member) {

            /** @var \STS\Core\Member\MemberDto $member */
            if ($member->getCoordinatesForRegions()) {
                return true;
            }

            if ($member->getCoordinatesForAreas()) {
                return true;
            }

            return false;
        });

        $this->view->members = $members;
    }
}
