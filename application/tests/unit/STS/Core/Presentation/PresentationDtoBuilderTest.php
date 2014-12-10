<?php
namespace STS\Core\Presentation;

use STS\Core\Presentation\PresentationDtoBuilder;
use STS\TestUtilities\PresentationTestCase;

class PresentationDtoBuilderTest extends PresentationTestCase
{
    /**
     * @test
     */
    public function shouldReturnNullPresentationDtoOnNullBuild()
    {
        $builder = new PresentationDtoBuilder();
        $dto = $builder->build();
        $this->assertInstanceOf('STS\Core\Presentation\PresentationDto', $dto);
        $this->assertNull($dto->getId());
        $this->assertNull($dto->getDate());
        $this->assertNull($dto->getNumberOfParticipants());
        $this->assertNull($dto->getSchoolAreaCity());
        $this->assertNull($dto->getSchoolName());
	    $this->assertNull($dto->getProfessionalGroupName());
        $this->assertNull($dto->getType());
        $this->assertNull($dto->getId());
        $this->assertNull($dto->getId());
        $this->assertNull($dto->getId());
        $this->assertNull($dto->getNumberOfFormsReturnedPre());
        $this->assertNull($dto->getNumberOfFormsReturnedPost());
        $this->assertNull($dto->getId());
        $this->assertNull($dto->getSurveyId());
        $this->assertNull($dto->getSchoolId());
        $this->assertEmpty($dto->getMembersArray());
        $this->assertNull($dto->getNotes());
	    $this->assertNull($dto->getCorrectBeforePercentage());
	    $this->assertNull($dto->getCorrectAfterPercentage());
	    $this->assertNull($dto->getEffectivenessPercentage());
    }

    /**
     * @test
     */
    public function shouldReturnValidDtoOnBuild()
    {
        $validDto = $this->getValidPresentationDto();
        $builder = new PresentationDtoBuilder();
        $builder->withId($validDto->getId())
                ->withSchoolName($validDto->getSchoolName())
                ->withSchoolAreaCity($validDto->getSchoolAreaCity())
                ->withNumberOfParticipants($validDto->getNumberOfParticipants())
                ->withType($validDto->getType())
                ->withDate($validDto->getDate())
                ->withNumberOfFormsReturnedPost($validDto->getNumberOfFormsReturnedPost())
                ->withNumberOfFormsReturnedPre($validDto->getNumberOfFormsReturnedPre())
                ->withSurveyId($validDto->getSurveyId())
                ->withSchoolId($validDto->getSchoolId())
	            ->withProfessionalGroupName($validDto->getProfessionalGroupName())
                ->withMembersArray($validDto->getMembersArray())
                ->withNotes($validDto->getNotes())
	            ->withCorrectBeforePercentage($validDto->getCorrectBeforePercentage())
	            ->withCorrectAfterPercentage($validDto->getCorrectAfterPercentage())
	            ->withEffectivenessPercentage($validDto->getEffectivenessPercentage());
        $dto = $builder->build();
        $this->assertValidPresentationDto($dto);
    }
}
