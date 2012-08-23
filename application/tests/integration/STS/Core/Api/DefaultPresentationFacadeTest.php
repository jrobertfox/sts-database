<?php
use STS\TestUtilities\PresentationTestCase;
use STS\Core;
use STS\Core\Api\DefaultPresentationFacade;

class DefaultPresentationFacadeTest extends PresentationTestCase
{
    /**
     * @test
     */
    public function getDefaultInstance()
    {
        $this->assertInstanceOf('STS\Core\Api\DefaultPresentationFacade', $this->loadFacadeInstance());
    }
    /**
     * @test
     */
    public function savePresentation()
    {
        
        $schoolId='502314eec6464712c1e7060e';
        $typeCode='np';
        $date='08/09/2012';
        $notes ='These are some notes!';
        $memberIds = array('50318d42066b83068e5d9452');
        $enteredByUserId = 'muser';
        $participants = 20;
        $forms = 18;
        $surveyId = '5035af240172cda7d649d477';        
        
        $facade = $this->loadFacadeInstance();
        $presentationId = $facade
            ->savePresentation($enteredByUserId, $schoolId, $typeCode, $date, $notes, $memberIds, $participants, $forms, $surveyId);
        $this->assertNotNull($presentationId);
    }
    private function loadFacadeInstance()
    {
        $core = Core::getDefaultInstance();
        $facade = $core->load('PresentationFacade');
        return $facade;
    }
}
