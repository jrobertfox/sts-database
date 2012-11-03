<?php
namespace STS\TestUtilities;
use STS\Domain\Presentation;
use STS\Core\Presentation\PresentationDto;
use STS\TestUtilities\Location\AreaTestCase;

class PresentationTestCase extends \PHPUnit_Framework_TestCase
{
    const ENTERED_BY_USER_ID = 'muser';
    const ID = '50234bc4fe65f50a9579a8cd';
    const TYPE = 'MED';
    const DATE = '2012-05-10 11:55:23';
    const NOTES = 'The presentation went quite well I must say.';
    const PARTICIPANTS = 203;
    const FORMS = 198;
    protected function getValidObject()
    {
        $school = SchoolTestCase::createValidSchool();
        $members = array(
            MemberTestCase::createValidMember()
        );
        $survey = $this->getMockBuilder('STS\Domain\Survey')->disableOriginalConstructor()->getMock();
        $presentation = new Presentation();
        $presentation->setEnteredByUserId(self::ENTERED_BY_USER_ID)->setId(self::ID)->setLocation($school)->setType(self::TYPE)->setDate(self::DATE)
            ->setNotes(self::NOTES)->setMembers($members)->setNumberOfParticipants(self::PARTICIPANTS)
            ->setNumberOfFormsReturned(self::FORMS)->setSurvey($survey);
        return $presentation;
    }

    public static function createValidObject()
    {
        $presentationTestCase = new PresentationTestCase();
        return $presentationTestCase->getValidObject();
    }

    protected function getValidPresentationDto()
    {
        return new PresentationDto(self::ID, SchoolTestCase::NAME, AreaTestCase::CITY, self::PARTICIPANTS, self::DATE, self::TYPE);
    }
    protected function assertValidObject($presentation)
    {
        $this->assertEquals(self::ID, $presentation->getId());
        $this->assertEquals(self::ENTERED_BY_USER_ID, $presentation->getEnteredByUserId());
        $this->assertEquals(self::TYPE, $presentation->getType());
        $this->assertEquals(self::DATE, $presentation->getDate());
        $this->assertEquals(self::NOTES, $presentation->getNotes());
        $this->assertEquals(self::PARTICIPANTS, $presentation->getNumberOfParticipants());
        $this->assertEquals(self::FORMS, $presentation->getNumberOfFormsReturned());
        $this->assertInstanceOf('STS\Domain\School', $presentation->getLocation());
        $this->assertInstanceOf('STS\Domain\Survey', $presentation->getSurvey());
        $this->assertTrue(is_array($presentation->getMembers()));
        $this->assertInstanceOf('STS\Domain\Member', array_pop($presentation->getMembers()));
    }

    protected function assertValidPresentationDto($dto)
    {
        $this->assertInstanceOf('STS\Core\Presentation\PresentationDto', $dto);
        $this->assertTrue(is_string($dto->getId()));
        $this->assertEquals(self::ID, $dto->getId());
        $this->assertEquals(SchoolTestCase::NAME, $dto->getSchoolName());
        $this->assertEquals(AreaTestCase::CITY, $dto->getSchoolAreaCity());
        $this->assertEquals(self::PARTICIPANTS, $dto->getNumberOfParticipants());
        $this->assertEquals(self::TYPE, $dto->getType());
    }
}
